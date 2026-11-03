<?php

namespace App\Rules;

use App\Services\Settings\SettingsService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Upload guard: size limit from settings, extension allowlist, sniffed MIME type
 * must match the extension, and markup/script content is refused outright.
 */
class SafeUpload implements ValidationRule
{
    /** extension => accepted sniffed MIME types */
    public const TYPES = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/CDFV2', 'application/x-ole-storage', 'application/vnd.ms-office'],
        'xls' => ['application/vnd.ms-excel', 'application/CDFV2', 'application/x-ole-storage', 'application/vnd.ms-office'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/CDFV2', 'application/x-ole-storage', 'application/vnd.ms-office'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
        'txt' => ['text/plain'],
        'csv' => ['text/plain', 'text/csv', 'application/csv'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'rar' => ['application/x-rar', 'application/vnd.rar', 'application/x-rar-compressed'],
        '7z' => ['application/x-7z-compressed'],
    ];

    private const MARKERS = ['<?php', '<?=', '<script', '<html', '<svg', '<!doctype html', '<iframe', '<object'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('validation.uploaded')->translate();

            return;
        }

        $maxMb = (int) app(SettingsService::class)->get('files.max_upload_mb');
        if ((int) $value->getSize() > $maxMb * 1024 * 1024) {
            $fail('validation.max.file')->translate(['max' => $maxMb * 1024]);

            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());
        $mime = self::sniff($value);
        if (! isset(self::TYPES[$extension]) || ! in_array($mime, self::TYPES[$extension], true)) {
            $fail('messages.file_type_not_allowed')->translate(['types' => implode(', ', array_keys(self::TYPES))]);

            return;
        }

        if ($this->containsMarkup((string) $value->getRealPath())) {
            $fail('messages.file_type_not_allowed')->translate(['types' => implode(', ', array_keys(self::TYPES))]);
        }
    }

    /** MIME type detected from the file's bytes (never from the client or the name). */
    public static function sniff(UploadedFile $file): string
    {
        return (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file->getRealPath());
    }

    private function containsMarkup(string $path): bool
    {
        $head = strtolower((string) file_get_contents($path, false, null, 0, 4096));
        foreach (self::MARKERS as $marker) {
            if (str_contains($head, $marker)) {
                return true;
            }
        }

        return false;
    }
}
