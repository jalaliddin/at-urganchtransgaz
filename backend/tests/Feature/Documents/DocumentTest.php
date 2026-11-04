<?php

namespace Tests\Feature\Documents;

use App\Models\Document;
use App\Models\Organization;
use App\Models\User;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTickets;
use Tests\Concerns\InteractsWithAccess;
use Tests\Feature\Files\SafeUploadTest;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use CreatesTickets, InteractsWithAccess, RefreshDatabase;

    private Organization $org;

    private Organization $other;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seedHelpdesk();
        $this->org = Organization::factory()->create();
        $this->other = Organization::factory()->create();
        $this->manager = $this->makeStaff(Roles::ORG_IT_RESPONSIBLE, $this->org);
    }

    private function pdf(string $name = 'shartnoma.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, SafeUploadTest::pdf());
    }

    private function createDocument(array $overrides = []): array
    {
        return $this->actingAs($this->manager)->post('/api/v1/documents', [
            'organization_id' => $this->org->id,
            'type' => 'contract',
            'number' => 'SH-2026/15',
            'title' => 'Internet xizmati shartnomasi',
            'document_date' => '2026-10-01',
            'file' => $this->pdf(),
            ...$overrides,
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');
    }

    public function test_create_with_first_version_and_list(): void
    {
        $doc = $this->createDocument();

        $this->assertSame('contract', $doc['type']);
        $this->assertSame(1, $doc['current_version']['version']);
        $this->assertSame('shartnoma.pdf', $doc['current_version']['file']['name']);

        $this->getJson('/api/v1/documents?filter[type]=contract')->assertOk()->assertJsonPath('data.0.id', $doc['id']);
        $this->getJson('/api/v1/documents?search=SH-2026')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/documents?filter[type]=act')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_validation_and_permissions(): void
    {
        $this->actingAs($this->manager)->post('/api/v1/documents', ['type' => 'secret', 'organization_id' => $this->other->id], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors(['type', 'title', 'organization_id']);
        $this->actingAs($this->manager)->post('/api/v1/documents', [
            'organization_id' => $this->org->id, 'type' => 'act', 'title' => 'Dalolatnoma', 'file' => UploadedFile::fake()->createWithContent('a.php', '<?php'),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('file');

        $this->actingAs($this->makeStaff(Roles::AUDITOR, $this->org));
        $this->postJson('/api/v1/documents', ['organization_id' => $this->org->id, 'type' => 'act', 'title' => 'X'])->assertForbidden();
        $this->getJson('/api/v1/documents')->assertOk();

        $this->actingAs($this->makeStaff(Roles::EMPLOYEE, $this->org));
        $this->getJson('/api/v1/documents')->assertForbidden();
    }

    public function test_versions_and_scoped_download(): void
    {
        $doc = $this->createDocument();
        $v2 = $this->post("/api/v1/documents/{$doc['id']}/versions", ['file' => $this->pdf('shartnoma-v2.pdf'), 'note' => 'Qo\'shimcha kelishuv'], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.version', 2)->json('data');

        $show = $this->getJson("/api/v1/documents/{$doc['id']}")->assertOk()->json('data');
        $this->assertSame(2, $show['current_version']['version']);
        $this->assertCount(2, $show['versions']);

        $this->get("/api/v1/files/{$v2['file']['uuid']}")->assertOk();
        $this->actingAs($this->makeStaff(Roles::ORG_IT_RESPONSIBLE, $this->other));
        $this->get("/api/v1/files/{$v2['file']['uuid']}")->assertNotFound();
        $this->getJson("/api/v1/documents/{$doc['id']}")->assertNotFound();
        $this->actingAs($this->makeStaff(Roles::EMPLOYEE, $this->org));
        $this->get("/api/v1/files/{$v2['file']['uuid']}")->assertNotFound();
    }

    public function test_update_and_delete(): void
    {
        $doc = $this->createDocument();

        $this->putJson("/api/v1/documents/{$doc['id']}", ['title' => 'Yangilangan', 'type' => 'contract'])->assertOk()->assertJsonPath('data.title', 'Yangilangan');
        $this->deleteJson("/api/v1/documents/{$doc['id']}")->assertOk();
        $this->assertSoftDeleted('documents', ['id' => $doc['id']]);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'document', 'auditable_id' => $doc['id'], 'event' => 'deleted']);
    }

    public function test_link_to_visible_ticket_only(): void
    {
        $doc = $this->createDocument();
        $ticket = $this->ticketBy($this->makeStaff(Roles::EMPLOYEE, $this->org));
        $foreign = $this->ticketBy($this->makeStaff(Roles::EMPLOYEE, $this->other));
        $this->actingAs($this->manager);

        $this->postJson("/api/v1/documents/{$doc['id']}/links", ['type' => 'ticket', 'id' => $foreign->id])->assertNotFound();
        $this->postJson("/api/v1/documents/{$doc['id']}/links", ['type' => 'asset', 'id' => 1])->assertStatus(422);
        $linkId = $this->postJson("/api/v1/documents/{$doc['id']}/links", ['type' => 'ticket', 'id' => $ticket->id])
            ->assertCreated()->assertJsonPath('data.target.number', $ticket->number)->json('data.id');
        $this->postJson("/api/v1/documents/{$doc['id']}/links", ['type' => 'ticket', 'id' => $ticket->id])->assertStatus(409);

        $this->getJson("/api/v1/helpdesk/tickets/{$ticket->id}/documents")->assertOk()->assertJsonPath('data.0.id', $doc['id']);
        $this->getJson("/api/v1/documents/{$doc['id']}")->assertJsonPath('data.links.0.target.id', $ticket->id);

        $this->deleteJson("/api/v1/documents/{$doc['id']}/links/{$linkId}")->assertOk();
        $this->assertSame(0, Document::findOrFail($doc['id'])->links()->count());
    }
}
