<?php

namespace Tests\Feature\Helpdesk;

use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesTickets;
use Tests\Concerns\InteractsWithAccess;
use Tests\Feature\Files\SafeUploadTest;
use Tests\TestCase;

class TicketAttachmentTest extends TestCase
{
    use CreatesTickets, InteractsWithAccess, RefreshDatabase;

    private Organization $org;

    private User $requester;

    private User $handler;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seedHelpdesk();
        $this->org = Organization::factory()->create();
        $this->requester = $this->makeStaff(Roles::EMPLOYEE, $this->org);
        $this->handler = $this->makeStaff(Roles::ORG_IT_RESPONSIBLE, $this->org);
    }

    private function pdf(string $name = 'scan.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, SafeUploadTest::pdf());
    }

    private function createWithFiles(array $files): TestResponse
    {
        return $this->actingAs($this->requester)->post('/api/v1/helpdesk/tickets', [
            'title' => 'Monitor miltillayapti',
            'description' => 'Monitor har 5 daqiqada o\'chib yonadi.',
            'category_id' => $this->category()->id,
            'files' => $files,
        ], ['Accept' => 'application/json']);
    }

    public function test_files_uploaded_with_new_ticket_are_downloadable_by_participants_only(): void
    {
        $id = $this->createWithFiles([$this->pdf(), UploadedFile::fake()->createWithContent('photo.png', (string) base64_decode(SafeUploadTest::PNG))])
            ->assertCreated()->json('data.id');

        $attachments = $this->getJson("/api/v1/helpdesk/tickets/{$id}/attachments")->assertOk()->json('data');
        $this->assertCount(2, $attachments);
        $uuid = $attachments[0]['file']['uuid'];
        $this->assertSame('scan.pdf', $attachments[0]['file']['name']);

        $this->get("/api/v1/files/{$uuid}")->assertOk();
        $this->actingAs($this->handler)->get("/api/v1/files/{$uuid}")->assertOk();
        $this->actingAs($this->makeStaff(Roles::EMPLOYEE, $this->org))->get("/api/v1/files/{$uuid}")->assertNotFound();
        $this->actingAs($this->makeStaff(Roles::ORG_IT_RESPONSIBLE, Organization::factory()->create()))->get("/api/v1/files/{$uuid}")->assertNotFound();
    }

    public function test_bad_or_too_many_files_reject_the_whole_ticket(): void
    {
        $this->createWithFiles([UploadedFile::fake()->createWithContent('x.pdf', '<?php echo 1;')])
            ->assertStatus(422)->assertJsonValidationErrors('files.0');
        $this->createWithFiles(array_map(fn ($i) => $this->pdf("f{$i}.pdf"), range(1, 6)))
            ->assertStatus(422)->assertJsonValidationErrors('files');

        $this->assertSame(0, Ticket::count());
    }

    public function test_files_on_internal_comments_stay_internal(): void
    {
        $ticket = $this->ticketBy($this->requester);
        $this->actingAs($this->handler);
        $commentId = $this->postJson("/api/v1/helpdesk/tickets/{$ticket->id}/comments", ['body' => 'Server logi', 'is_internal' => true])->json('data.id');

        $uuid = $this->post("/api/v1/helpdesk/tickets/{$ticket->id}/attachments", ['files' => [$this->pdf('log.pdf')], 'comment_id' => $commentId], ['Accept' => 'application/json'])
            ->assertCreated()->json('data.0.file.uuid');
        $this->get("/api/v1/files/{$uuid}")->assertOk();

        $this->actingAs($this->requester);
        $this->get("/api/v1/files/{$uuid}")->assertNotFound();
        $this->assertSame([], $this->getJson("/api/v1/helpdesk/tickets/{$ticket->id}/attachments")->json('data'));
        $this->post("/api/v1/helpdesk/tickets/{$ticket->id}/attachments", ['files' => [$this->pdf()], 'comment_id' => $commentId], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('comment_id');
    }

    public function test_delete_rules(): void
    {
        $ticket = $this->ticketBy($this->requester);
        $this->actingAs($this->requester);
        $first = $this->post("/api/v1/helpdesk/tickets/{$ticket->id}/attachments", ['files' => [$this->pdf('a.pdf'), $this->pdf('b.pdf')]], ['Accept' => 'application/json'])
            ->assertCreated()->json('data');
        $path = TicketAttachment::findOrFail($first[0]['id'])->file->path;

        $this->deleteJson("/api/v1/helpdesk/tickets/{$ticket->id}/attachments/{$first[0]['id']}")->assertOk();
        Storage::disk('local')->assertMissing($path);

        $this->travel(16)->minutes();
        $this->deleteJson("/api/v1/helpdesk/tickets/{$ticket->id}/attachments/{$first[1]['id']}")->assertForbidden();
        $this->actingAs($this->handler)->deleteJson("/api/v1/helpdesk/tickets/{$ticket->id}/attachments/{$first[1]['id']}")->assertOk();
        $this->assertDatabaseHas('ticket_histories', ['ticket_id' => $ticket->id, 'action' => 'attachment_removed']);
    }

    public function test_attachment_of_another_ticket_is_not_found(): void
    {
        $a = $this->ticketBy($this->requester);
        $b = $this->ticketBy($this->requester);
        $this->actingAs($this->requester);
        $id = $this->post("/api/v1/helpdesk/tickets/{$a->id}/attachments", ['files' => [$this->pdf()]], ['Accept' => 'application/json'])->json('data.0.id');

        $this->deleteJson("/api/v1/helpdesk/tickets/{$b->id}/attachments/{$id}")->assertNotFound();
    }
}
