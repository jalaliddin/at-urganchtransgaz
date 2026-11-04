<?php

namespace Tests\Feature\Exports;

use App\Models\Organization;
use App\Models\ReportExport;
use App\Models\User;
use App\Notifications\ExportReadyNotification;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Concerns\CreatesTickets;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class TicketExportTest extends TestCase
{
    use CreatesTickets, InteractsWithAccess, RefreshDatabase;

    private Organization $a;

    private User $handler;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seedHelpdesk();
        $this->a = Organization::factory()->create();
        $this->handler = $this->makeStaff(Roles::ORG_IT_RESPONSIBLE, $this->a);
        $requester = $this->makeStaff(Roles::EMPLOYEE, $this->a);
        $this->ticketBy($requester, ['title' => 'Принтер не печатает']);
        $this->ticketBy($requester, ['title' => 'Internet yoʻq', 'priority' => 'high']);
        $this->ticketBy($this->makeStaff(Roles::EMPLOYEE, Organization::factory()->create()), ['title' => 'Begona tashkilot']);
    }

    /** @return list<list<mixed>> */
    private function readXlsx(string $contents): array
    {
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        file_put_contents($path, $contents);
        $reader = new Reader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            break;
        }
        $reader->close();

        return $rows;
    }

    public function test_xlsx_export_is_generated_in_queue_with_requesters_scope(): void
    {
        Notification::fake();
        $this->actingAs($this->handler);

        $uuid = $this->postJson('/api/v1/exports', ['type' => 'helpdesk_tickets', 'format' => 'xlsx', 'filters' => []])
            ->assertStatus(202)->json('data.uuid');

        $export = $this->getJson("/api/v1/exports/{$uuid}")->assertOk()
            ->assertJsonPath('data.status', 'done')->assertJsonPath('data.row_count', 2)->json('data');
        $file = $this->get("/api/v1/files/{$export['file']['uuid']}")->assertOk();

        $rows = $this->readXlsx($file->streamedContent());
        $this->assertCount(3, $rows);
        $titles = array_column(array_slice($rows, 1), 2);
        $this->assertEqualsCanonicalizing(['Принтер не печатает', 'Internet yoʻq'], $titles);
        Notification::assertSentTo($this->handler, ExportReadyNotification::class);
    }

    public function test_filters_are_applied_and_validated(): void
    {
        $this->actingAs($this->handler);

        $uuid = $this->postJson('/api/v1/exports', ['type' => 'helpdesk_tickets', 'format' => 'xlsx', 'filters' => ['priority' => 'high']])
            ->assertStatus(202)->json('data.uuid');
        $this->assertSame(1, ReportExport::where('uuid', $uuid)->value('row_count'));

        $this->postJson('/api/v1/exports', ['type' => 'helpdesk_tickets', 'format' => 'xlsx', 'filters' => ['password' => 'x']])
            ->assertStatus(422)->assertJsonValidationErrors('filters');
        $this->postJson('/api/v1/exports', ['type' => 'unknown', 'format' => 'csv'])
            ->assertStatus(422)->assertJsonValidationErrors(['type', 'format']);
    }

    public function test_pdf_export(): void
    {
        $this->actingAs($this->handler);

        $uuid = $this->postJson('/api/v1/exports', ['type' => 'helpdesk_tickets', 'format' => 'pdf', 'filters' => []])->json('data.uuid');
        $fileUuid = $this->getJson("/api/v1/exports/{$uuid}")->assertJsonPath('data.status', 'done')->json('data.file.uuid');

        $this->assertStringStartsWith('%PDF', $this->get("/api/v1/files/{$fileUuid}")->streamedContent());
    }

    public function test_only_the_owner_sees_and_downloads_an_export(): void
    {
        $this->actingAs($this->handler);
        $uuid = $this->postJson('/api/v1/exports', ['type' => 'helpdesk_tickets', 'format' => 'xlsx'])->json('data.uuid');
        $fileUuid = ReportExport::where('uuid', $uuid)->firstOrFail()->file->uuid;

        $other = $this->makeStaff(Roles::ORG_IT_RESPONSIBLE, $this->a);
        $this->actingAs($other);
        $this->getJson("/api/v1/exports/{$uuid}")->assertNotFound();
        $this->get("/api/v1/files/{$fileUuid}")->assertNotFound();
        $this->assertSame([], $this->getJson('/api/v1/exports')->assertOk()->json('data'));
    }

    public function test_export_permission_required(): void
    {
        $this->actingAs($this->makeStaff(Roles::EMPLOYEE, $this->a));

        $this->postJson('/api/v1/exports', ['type' => 'helpdesk_tickets', 'format' => 'xlsx'])->assertForbidden();
    }

    public function test_old_exports_are_pruned_with_their_files(): void
    {
        $this->actingAs($this->handler);
        $uuid = $this->postJson('/api/v1/exports', ['type' => 'helpdesk_tickets', 'format' => 'xlsx'])->json('data.uuid');
        $path = ReportExport::where('uuid', $uuid)->firstOrFail()->file->path;

        $this->travel(8)->days();
        $this->artisan('exports:prune')->assertSuccessful();

        $this->assertDatabaseMissing('report_exports', ['uuid' => $uuid]);
        Storage::disk('local')->assertMissing($path);
    }
}
