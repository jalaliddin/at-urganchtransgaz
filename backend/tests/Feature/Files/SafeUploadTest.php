<?php

namespace Tests\Feature\Files;

use App\Rules\SafeUpload;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

class SafeUploadTest extends TestCase
{
    use RefreshDatabase;

    public const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    public static function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
    }

    public static function docx(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="x"/>');
        $zip->close();

        return (string) file_get_contents($path);
    }

    private function passes(UploadedFile $file): bool
    {
        return Validator::make(['file' => $file], ['file' => [new SafeUpload]])->passes();
    }

    /** @return array<string, array{string, string}> */
    public static function accepted(): array
    {
        return [
            'pdf' => ['scan.pdf', self::pdf()],
            'png' => ['photo.PNG', (string) base64_decode(self::PNG)],
            'docx' => ['letter.docx', self::docx()],
            'txt' => ['log.txt', "Printer error 0x0001\n"],
            'csv' => ['list.csv', "a;b\n1;2\n"],
        ];
    }

    #[DataProvider('accepted')]
    public function test_accepts_allowed_files(string $name, string $content): void
    {
        $this->assertTrue($this->passes(UploadedFile::fake()->createWithContent($name, $content)));
    }

    /** @return array<string, array{string, string}> */
    public static function rejected(): array
    {
        return [
            'php disguised as pdf' => ['invoice.pdf', "<?php system(\$_GET['c']);"],
            'php extension' => ['shell.php', '<?php echo 1;'],
            'svg' => ['logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
            'html as txt' => ['note.txt', '<html><body><script>alert(1)</script></body></html>'],
            'html extension' => ['page.html', '<p>x</p>'],
            'exe' => ['setup.exe', "MZ\x90\x00\x03\x00\x00\x00"],
            'png content named jpg' => ['photo.jpg', (string) base64_decode(self::PNG)],
            'pdf named docx' => ['x.docx', self::pdf()],
            'no extension' => ['README', 'hello'],
            'double extension' => ['report.pdf.php', self::pdf()],
        ];
    }

    #[DataProvider('rejected')]
    public function test_rejects_dangerous_or_mismatched_files(string $name, string $content): void
    {
        $this->assertFalse($this->passes(UploadedFile::fake()->createWithContent($name, $content)));
    }

    public function test_size_limit_comes_from_settings(): void
    {
        app(SettingsService::class)->update(['files.max_upload_mb' => 1]);
        $big = self::pdf().str_repeat('0', 1024 * 1024);

        $this->assertFalse($this->passes(UploadedFile::fake()->createWithContent('big.pdf', $big)));
        $this->assertTrue($this->passes(UploadedFile::fake()->createWithContent('small.pdf', self::pdf())));
    }

    public function test_non_file_value_is_rejected(): void
    {
        $this->assertFalse(Validator::make(['file' => 'abc'], ['file' => [new SafeUpload]])->passes());
    }
}
