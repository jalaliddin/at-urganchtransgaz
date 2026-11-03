<?php

namespace Tests\Feature\Organizations;

use App\Enums\OrganizationType;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class OrganizationApiTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    private Organization $root;

    private Organization $a;

    private Organization $b;

    private User $head;

    private User $subA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->root = Organization::factory()->create(['type' => OrganizationType::HeadOffice, 'name' => 'Markaziy apparat']);
        $this->a = Organization::factory()->childOf($this->root)->create(['name' => 'Xorazm GTS']);
        $this->b = Organization::factory()->childOf($this->root)->create(['name' => 'Urganch MGQB']);
        $this->head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->root, true);
        $this->subA = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->a);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'parent_id' => $this->root->id,
            'type' => 'subsidiary',
            'name' => 'Xiva gaz ta\'minoti',
            'code' => 'GTS-1',
        ], $overrides);
    }

    public function test_central_head_creates_child(): void
    {
        $this->actingAs($this->head);

        $id = $this->postJson('/api/v1/organizations', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.depth', 1)
            ->assertJsonPath('data.parent.id', $this->root->id)
            ->json('data.id');

        $this->assertSame("/{$this->root->id}/{$id}/", Organization::find($id)->path);
        $this->assertSame(1, AuditLog::where('auditable_type', 'organization')->where('auditable_id', $id)->where('event', 'created')->count());
    }

    public function test_only_unrestricted_can_create_root(): void
    {
        $this->actingAs($this->head);
        $this->postJson('/api/v1/organizations', $this->payload(['parent_id' => null]))
            ->assertStatus(422)->assertJsonValidationErrors(['parent_id']);

        $this->actingAs($this->makeUser(Roles::SUPER_ADMIN));
        $this->postJson('/api/v1/organizations', $this->payload(['parent_id' => null, 'code' => 'ROOT-2']))
            ->assertCreated()->assertJsonPath('data.depth', 0);
    }

    public function test_subsidiary_user_cannot_create(): void
    {
        $this->actingAs($this->subA);

        $this->postJson('/api/v1/organizations', $this->payload(['parent_id' => $this->a->id]))->assertForbidden();
    }

    public function test_other_organization_is_invisible(): void
    {
        $this->actingAs($this->subA);

        $this->getJson("/api/v1/organizations/{$this->b->id}")->assertNotFound();
        $this->putJson("/api/v1/organizations/{$this->b->id}", ['name' => 'X'])->assertNotFound();
        $this->deleteJson("/api/v1/organizations/{$this->b->id}")->assertNotFound();
    }

    public function test_list_and_tree_only_show_scope(): void
    {
        $this->actingAs($this->subA);

        $this->assertSame([$this->a->id], collect($this->getJson('/api/v1/organizations')->assertOk()->json('data'))->pluck('id')->all());
        $this->getJson('/api/v1/organizations/tree')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->a->id)
            ->assertJsonPath('data.0.children', []);
    }

    public function test_tree_nests_children_for_central_head(): void
    {
        $this->actingAs($this->head);

        $this->getJson('/api/v1/organizations/tree')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->root->id)
            ->assertJsonCount(2, 'data.0.children');
    }

    public function test_validation_rules(): void
    {
        $this->actingAs($this->head);
        Organization::factory()->childOf($this->root)->create(['code' => 'DUP-1']);

        $this->postJson('/api/v1/organizations', $this->payload(['tin' => '12345']))->assertJsonValidationErrors(['tin']);
        $this->postJson('/api/v1/organizations', $this->payload(['code' => 'DUP-1']))->assertJsonValidationErrors(['code']);
        $this->postJson('/api/v1/organizations', $this->payload(['type' => 'galaxy']))->assertJsonValidationErrors(['type']);

        $this->actingAs($this->makeUser(Roles::CENTRAL_IT_HEAD, $this->a, true));
        $this->postJson('/api/v1/organizations', $this->payload(['parent_id' => $this->b->id]))->assertJsonValidationErrors(['parent_id']);
    }

    public function test_update_by_head_and_forbidden_for_subsidiary(): void
    {
        $this->actingAs($this->head);
        $this->putJson("/api/v1/organizations/{$this->a->id}", ['name' => 'Xorazm GTS (yangi)'])
            ->assertOk()->assertJsonPath('data.name', 'Xorazm GTS (yangi)');

        $this->actingAs($this->subA);
        $this->putJson("/api/v1/organizations/{$this->a->id}", ['name' => 'X'])->assertForbidden();
    }

    public function test_delete_guards_and_soft_delete(): void
    {
        $this->actingAs($this->head);
        $this->deleteJson("/api/v1/organizations/{$this->root->id}")->assertStatus(409)->assertJsonPath('code', 'conflict');

        Employee::factory()->for($this->b)->create();
        $this->deleteJson("/api/v1/organizations/{$this->b->id}")->assertStatus(409);

        $empty = Organization::factory()->childOf($this->root)->create();
        $this->deleteJson("/api/v1/organizations/{$empty->id}")->assertOk();
        $this->assertSoftDeleted('organizations', ['id' => $empty->id]);
    }

    public function test_list_parameters_are_validated(): void
    {
        $this->actingAs($this->head);

        $this->getJson('/api/v1/organizations?sort=password')->assertStatus(400)->assertJsonPath('code', 'bad_request');
        $this->getJson('/api/v1/organizations?per_page=1000')->assertOk()->assertJsonPath('meta.per_page', 100);
        $this->getJson('/api/v1/organizations?per_page=abc')->assertOk()->assertJsonPath('meta.per_page', 25);
    }

    public function test_search_and_filters(): void
    {
        $this->actingAs($this->head);

        $this->assertSame([$this->a->id], collect($this->getJson('/api/v1/organizations?search=xorazm')->json('data'))->pluck('id')->all());
        $this->assertSame(
            [$this->root->id],
            collect($this->getJson('/api/v1/organizations?filter[type]=head_office')->json('data'))->pluck('id')->all(),
        );
    }

    public function test_filter_organization_out_of_scope_returns_empty(): void
    {
        $this->actingAs($this->subA);

        $this->getJson("/api/v1/organizations?filter[organization]={$this->b->id}")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_can_flags(): void
    {
        $this->actingAs($this->head);
        $this->getJson("/api/v1/organizations/{$this->a->id}")->assertJsonPath('data.can.update', true);

        $this->actingAs($this->makeUser(Roles::ORG_HEAD, $this->a));
        $this->getJson("/api/v1/organizations/{$this->a->id}")
            ->assertOk()
            ->assertJsonPath('data.can.update', false)
            ->assertJsonPath('data.can.delete', false);
    }
}
