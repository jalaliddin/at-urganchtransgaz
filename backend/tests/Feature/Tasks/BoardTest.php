<?php

namespace Tests\Feature\Tasks;

use App\Models\Organization;
use App\Models\User;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTasks;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class BoardTest extends TestCase
{
    use CreatesTasks, InteractsWithAccess, RefreshDatabase;

    private Organization $org;

    private User $it;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->org = Organization::factory()->create();
        $this->it = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org);
    }

    public function test_a_new_board_gets_the_standard_columns_and_stays_inside_its_organization(): void
    {
        $this->actingAs($this->it);
        $id = $this->postJson('/api/v1/task-boards', ['organization_id' => $this->org->id, 'name' => 'AT xizmati', 'description' => 'Kundalik ishlar'])
            ->assertCreated()->json('data.id');
        $columns = $this->getJson("/api/v1/task-boards/{$id}")->assertOk()->json('data.columns');
        $this->assertSame(['Backlog', 'Rejalashtirilgan', 'Jarayonda', 'Tekshiruvda', 'Bajarildi', 'Bekor qilindi'], array_column($columns, 'name'));
        $this->assertSame(['open', 'open', 'open', 'open', 'done', 'cancelled'], array_column($columns, 'category'));

        $this->postJson('/api/v1/task-boards', ['organization_id' => Organization::factory()->create()->id, 'name' => 'X'])
            ->assertStatus(422)->assertJsonValidationErrors('organization_id');
        $foreign = $this->boardIn(Organization::factory()->create());
        $this->getJson("/api/v1/task-boards/{$foreign->id}")->assertNotFound();
        $this->assertSame([$id], array_column($this->getJson('/api/v1/task-boards')->assertOk()->json('data'), 'id'));

        $this->actingAs($this->makeUser(Roles::ORG_HEAD, $this->org));
        $this->getJson("/api/v1/task-boards/{$id}")->assertOk();
        $this->postJson('/api/v1/task-boards', ['organization_id' => $this->org->id, 'name' => 'Y'])->assertForbidden();
        $this->actingAs($this->makeUser(Roles::EMPLOYEE, $this->org));
        $this->getJson('/api/v1/task-boards')->assertForbidden();
    }

    public function test_columns_can_be_configured_but_a_board_keeps_an_open_and_a_done_column(): void
    {
        $board = $this->boardIn($this->org);
        $this->actingAs($this->it);
        $review = $this->column($board, 'Tekshiruvda');
        $done = $this->column($board, 'Bajarildi');

        $extra = $this->postJson("/api/v1/task-boards/{$board->id}/columns", ['name' => 'Kutilmoqda', 'category' => 'open', 'wip_limit' => 3])
            ->assertCreated()->assertJsonPath('data.position', 7)->json('data.id');
        $this->putJson("/api/v1/task-columns/{$review->id}", ['name' => 'Sinovda', 'wip_limit' => 5])->assertOk()->assertJsonPath('data.name', 'Sinovda');
        $ids = array_column($this->getJson("/api/v1/task-boards/{$board->id}")->json('data.columns'), 'id');
        $reordered = [$extra, ...array_values(array_diff($ids, [$extra]))];
        $this->postJson("/api/v1/task-boards/{$board->id}/columns/order", ['ids' => $reordered])->assertOk();
        $this->assertSame($reordered, array_column($this->getJson("/api/v1/task-boards/{$board->id}")->json('data.columns'), 'id'));
        $this->postJson("/api/v1/task-boards/{$board->id}/columns/order", ['ids' => [$extra]])->assertStatus(422);

        $this->deleteJson("/api/v1/task-columns/{$done->id}")->assertStatus(422)->assertJsonValidationErrors('column');
        $this->putJson("/api/v1/task-columns/{$done->id}", ['category' => 'open'])->assertStatus(422)->assertJsonValidationErrors('category');

        $this->taskOn($board, $this->it, ['column_id' => $extra]);
        $this->deleteJson("/api/v1/task-columns/{$extra}")->assertStatus(409);
        $this->putJson("/api/v1/task-columns/{$extra}", ['category' => 'done'])->assertStatus(422)->assertJsonValidationErrors('category');
        $this->deleteJson("/api/v1/task-columns/{$review->id}")->assertOk();
        $this->deleteJson("/api/v1/task-boards/{$board->id}")->assertStatus(409);

        $foreign = $this->boardIn(Organization::factory()->create());
        $this->putJson('/api/v1/task-columns/'.$this->column($foreign, 'Backlog')->id, ['name' => 'X'])->assertNotFound();
    }

    public function test_labels_and_archiving(): void
    {
        $board = $this->boardIn($this->org);
        $this->actingAs($this->it);

        $label = $this->postJson("/api/v1/task-boards/{$board->id}/labels", ['name' => 'Tarmoq', 'color' => 'teal'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/task-boards/{$board->id}/labels", ['name' => 'tarmoq'])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->putJson("/api/v1/task-labels/{$label}", ['name' => 'Tarmoq ishlari', 'color' => 'blue'])->assertOk();
        $this->getJson("/api/v1/task-boards/{$board->id}")->assertJsonPath('data.labels.0.name', 'Tarmoq ishlari');

        $this->putJson("/api/v1/task-boards/{$board->id}", ['is_archived' => true])->assertOk()->assertJsonPath('data.is_archived', true);
        $this->postJson("/api/v1/task-boards/{$board->id}/columns", ['name' => 'Z', 'category' => 'open'])->assertStatus(409);
        $this->deleteJson("/api/v1/task-labels/{$label}")->assertStatus(409);
        $this->getJson('/api/v1/task-boards')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/task-boards?filter[archived]=1')->assertJsonCount(1, 'data');
        $this->putJson("/api/v1/task-boards/{$board->id}", ['is_archived' => false])->assertOk();
        $this->deleteJson("/api/v1/task-labels/{$label}")->assertOk();
        $this->deleteJson("/api/v1/task-boards/{$board->id}")->assertOk();
    }

    public function test_members_are_the_people_who_can_open_the_board(): void
    {
        $board = $this->boardIn($this->org);
        $staff = $this->makeUser(Roles::CENTRAL_IT_STAFF, $this->org);
        $this->makeUser(Roles::EMPLOYEE, $this->org);
        $this->makeUser(Roles::CENTRAL_IT_STAFF, Organization::factory()->create());

        $ids = array_column($this->actingAs($this->it)->getJson("/api/v1/task-boards/{$board->id}/members")->assertOk()->json('data'), 'id');
        $this->assertEqualsCanonicalizing([$this->it->id, $staff->id], $ids);
    }
}
