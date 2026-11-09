<?php

namespace Tests\Feature\Itsm;

use App\Models\KbArticleAttachment;
use App\Models\Organization;
use App\Models\StoredFile;
use App\Models\User;
use App\Services\Files\FileAccess;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\CreatesTickets;
use Tests\Concerns\InteractsWithAccess;
use Tests\Feature\Files\SafeUploadTest;
use Tests\TestCase;

class KnowledgeBaseTest extends TestCase
{
    use CreatesTickets, InteractsWithAccess, RefreshDatabase;

    private Organization $center;

    private User $head;

    private User $staff;

    private User $it;

    private User $employee;

    private User $auditor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedHelpdesk();
        $this->center = Organization::factory()->create();
        $branch = Organization::factory()->create(['parent_id' => $this->center->id]);
        $this->head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->center, true);
        $this->staff = $this->makeUser(Roles::CENTRAL_IT_STAFF, $this->center, true);
        $this->it = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $branch);
        $this->employee = $this->makeStaff(Roles::EMPLOYEE, $branch);
        $this->auditor = $this->makeUser(Roles::AUDITOR, $this->center, true);
    }

    /** @param  array<string, mixed>  $attributes */
    private function write(array $attributes = [], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->staff)->postJson('/api/v1/kb-articles', [
            'title' => 'Printerda qog\'oz tiqilib qolsa', 'type' => 'guide', 'audience' => 'all',
            'summary' => 'Qog\'ozni xavfsiz chiqarish', 'body' => "## Qadamlar\n1. Printerni o'chiring\n2. Qopqoqni oching",
            'category_id' => $this->category('printer')->id, ...$attributes,
        ]);
    }

    public function test_readers_see_only_published_articles_of_their_audience_and_authors_their_own(): void
    {
        $public = $this->write()->assertCreated()->assertJsonPath('data.status', 'draft')->json('data');
        $this->assertMatchesRegularExpression('/^KB-\d{5}$/', $public['number']);
        $id = $public['id'];

        $this->actingAs($this->employee)->getJson("/api/v1/kb-articles/{$id}")->assertNotFound();
        $this->actingAs($this->it)->getJson("/api/v1/kb-articles/{$id}")->assertNotFound();
        $this->actingAs($this->head)->getJson("/api/v1/kb-articles/{$id}")->assertOk();
        $this->actingAs($this->it)->putJson("/api/v1/kb-articles/{$id}", ['title' => 'X', 'type' => 'faq', 'audience' => 'all', 'body' => 'x'])->assertNotFound();

        $this->actingAs($this->staff)->postJson("/api/v1/kb-articles/{$id}/publish")->assertOk()->assertJsonPath('data.status', 'published');
        $this->actingAs($this->employee)->getJson("/api/v1/kb-articles/{$id}")->assertOk()
            ->assertJsonPath('data.body', "## Qadamlar\n1. Printerni o'chiring\n2. Qopqoqni oching")
            ->assertJsonPath('data.can.edit', false);
        $this->actingAs($this->it)->putJson("/api/v1/kb-articles/{$id}", ['title' => 'X', 'type' => 'faq', 'audience' => 'all', 'body' => 'x'])->assertForbidden();
        $this->actingAs($this->head)->putJson("/api/v1/kb-articles/{$id}", ['title' => 'Qog\'oz tiqilishi', 'type' => 'guide', 'audience' => 'all', 'body' => 'Yangi matn'])
            ->assertOk()->assertJsonPath('data.title', 'Qog\'oz tiqilishi');

        $internal = $this->write(['title' => 'VPN shlyuzini qayta ishga tushirish', 'audience' => 'it', 'publish' => true])
            ->assertCreated()->assertJsonPath('data.status', 'published')->json('data.id');
        $this->actingAs($this->employee)->getJson("/api/v1/kb-articles/{$internal}")->assertNotFound();
        $this->actingAs($this->auditor)->getJson("/api/v1/kb-articles/{$internal}")->assertOk();
        $this->assertSame([$id], array_column($this->actingAs($this->employee)->getJson('/api/v1/kb-articles')->assertOk()->json('data'), 'id'));

        $this->actingAs($this->staff)->postJson("/api/v1/kb-articles/{$id}/archive")->assertOk()->assertJsonPath('data.status', 'archived');
        $this->actingAs($this->employee)->getJson("/api/v1/kb-articles/{$id}")->assertNotFound();
        $this->actingAs($this->staff)->getJson("/api/v1/kb-articles/{$id}")->assertOk();
        $this->write([], $this->employee)->assertForbidden();
        $this->write(['audience' => 'everyone', 'type' => 'note'])->assertStatus(422)->assertJsonValidationErrors(['audience', 'type']);
    }

    public function test_search_filters_and_suggestions_for_the_ticket_form(): void
    {
        $paper = $this->write(['publish' => true])->json('data.id');
        $this->write(['title' => 'VPN ulanmayapti', 'type' => 'solution', 'audience' => 'it', 'category_id' => $this->category('network')->id, 'publish' => true]);
        $outlook = $this->write(['title' => 'Outlook parolini tiklash', 'type' => 'faq', 'category_id' => $this->category('account')->id,
            'body' => 'Printer emas, pochta haqida', 'publish' => true])->json('data.id');
        $this->write(['title' => 'Printer drayverini o\'rnatish (qoralama)']);

        $this->actingAs($this->employee);
        $this->assertEqualsCanonicalizing([$paper, $outlook], array_column($this->getJson('/api/v1/kb-articles?search=printer')->json('data'), 'id'));
        $this->getJson('/api/v1/kb-articles?filter[type]=faq')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $outlook);
        $this->getJson('/api/v1/kb-articles?filter[category]='.$this->category('printer')->id)->assertJsonCount(1, 'data');

        $suggested = $this->getJson('/api/v1/kb-articles/suggest?title='.urlencode('Printer qog\'ozni tortmayapti').'&category='.$this->category('printer')->id)
            ->assertOk()->json('data');
        $this->assertSame($paper, $suggested[0]['id']);
        $this->assertNotContains('VPN ulanmayapti', array_column($suggested, 'title'));
        $this->getJson('/api/v1/kb-articles/suggest?title=ab')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->it)->getJson('/api/v1/kb-articles/suggest?title=VPN')->assertJsonCount(1, 'data');
    }

    public function test_views_votes_files_and_delete_rules(): void
    {
        Storage::fake('local');
        $id = $this->write(['publish' => true])->json('data.id');
        $this->actingAs($this->employee)->getJson("/api/v1/kb-articles/{$id}");
        $this->getJson("/api/v1/kb-articles/{$id}")->assertJsonPath('data.views', 2);
        $this->actingAs($this->staff)->getJson("/api/v1/kb-articles/{$id}")->assertJsonPath('data.views', 2);

        $this->actingAs($this->employee)->postJson("/api/v1/kb-articles/{$id}/vote", ['helpful' => true])->assertOk()
            ->assertJsonPath('data.helpful', 1)->assertJsonPath('data.my_vote', true);
        $this->postJson("/api/v1/kb-articles/{$id}/vote", ['helpful' => false])->assertOk()
            ->assertJsonPath('data.helpful', 0)->assertJsonPath('data.not_helpful', 1);
        $this->actingAs($this->it)->postJson("/api/v1/kb-articles/{$id}/vote", ['helpful' => true])->assertJsonPath('data.helpful', 1);

        $draft = $this->write(['title' => 'Qoralama'])->json('data.id');
        $this->actingAs($this->staff)->postJson("/api/v1/kb-articles/{$draft}/attachments", [
            'files' => [UploadedFile::fake()->createWithContent('qollanma.pdf', SafeUploadTest::pdf())],
        ])->assertCreated()->assertJsonCount(1, 'data.attachments');
        $file = StoredFile::query()->findOrFail(KbArticleAttachment::query()->where('article_id', $draft)->value('file_id'));
        $access = app(FileAccess::class);
        $this->assertFalse($access->canDownload($this->employee, $file));
        $this->actingAs($this->staff)->postJson("/api/v1/kb-articles/{$draft}/publish")->assertOk();
        $this->assertTrue($access->canDownload($this->employee->fresh(), $file));
        $this->actingAs($this->employee)->postJson("/api/v1/kb-articles/{$draft}/vote", ['helpful' => true])->assertOk();

        $this->actingAs($this->staff)->deleteJson("/api/v1/kb-articles/{$id}")->assertStatus(409);
        $this->postJson("/api/v1/kb-articles/{$id}/archive")->assertOk();
        $this->deleteJson("/api/v1/kb-articles/{$id}")->assertStatus(409);
        $fresh = $this->write(['title' => 'Bekor qilinadigan'])->json('data.id');
        $this->actingAs($this->it)->deleteJson("/api/v1/kb-articles/{$fresh}")->assertNotFound();
        $this->actingAs($this->head)->deleteJson("/api/v1/kb-articles/{$fresh}")->assertOk();
        $this->assertDatabaseMissing('kb_articles', ['id' => $fresh]);
    }

    public function test_an_article_can_start_from_a_ticket_the_writer_can_see(): void
    {
        $ticket = $this->ticketBy($this->employee);
        $foreign = $this->ticketBy($this->makeStaff(Roles::EMPLOYEE, Organization::factory()->create()));

        $this->write(['source_ticket_id' => $ticket->id], $this->it)->assertCreated()
            ->assertJsonPath('data.source_ticket.number', $ticket->number);
        $this->write(['source_ticket_id' => $foreign->id], $this->it)->assertStatus(422)->assertJsonValidationErrors('source_ticket_id');
    }
}
