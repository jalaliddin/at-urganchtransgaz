<?php

namespace Tests\Feature\Organizations;

use App\Models\Organization;
use App\Models\User;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class OrganizationMoveTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    private Organization $root;

    private Organization $a;

    private Organization $a1;

    private Organization $a1a;

    private Organization $b;

    private User $head;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->root = Organization::factory()->create();
        $this->a = Organization::factory()->childOf($this->root)->create();
        $this->a1 = Organization::factory()->childOf($this->a)->create();
        $this->a1a = Organization::factory()->childOf($this->a1)->create();
        $this->b = Organization::factory()->childOf($this->root)->create();
        $this->head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->root, true);
    }

    public function test_move_rewrites_subtree_paths_and_depth(): void
    {
        $this->actingAs($this->head);

        $this->putJson("/api/v1/organizations/{$this->a1->id}", ['parent_id' => $this->b->id])->assertOk();

        $r = $this->root->id;
        $this->assertSame("/{$r}/{$this->b->id}/{$this->a1->id}/", $this->a1->fresh()->path);
        $this->assertSame("/{$r}/{$this->b->id}/{$this->a1->id}/{$this->a1a->id}/", $this->a1a->fresh()->path);
        $this->assertSame(2, $this->a1->fresh()->depth);
        $this->assertSame(3, $this->a1a->fresh()->depth);
        $this->assertSame("/{$r}/{$this->a->id}/", $this->a->fresh()->path);
    }

    public function test_cannot_move_into_own_descendant_or_self(): void
    {
        $this->actingAs($this->head);

        $this->putJson("/api/v1/organizations/{$this->a->id}", ['parent_id' => $this->a1a->id])
            ->assertStatus(422)->assertJsonValidationErrors(['parent_id']);
        $this->putJson("/api/v1/organizations/{$this->a->id}", ['parent_id' => $this->a->id])
            ->assertStatus(422)->assertJsonValidationErrors(['parent_id']);
    }

    public function test_move_requires_access_to_whole_subtree_and_target(): void
    {
        // Can edit A1 itself but not its child A1a → may not move the subtree.
        $partial = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->a1);
        $this->grantAccess($partial, $this->b, true);
        $this->actingAs($partial);
        $this->putJson("/api/v1/organizations/{$this->a1->id}", ['parent_id' => $this->b->id])->assertForbidden();

        // Covers A's subtree but the target B is outside its scope.
        $this->actingAs($this->makeUser(Roles::CENTRAL_IT_HEAD, $this->a, true));
        $this->putJson("/api/v1/organizations/{$this->a1->id}", ['parent_id' => $this->b->id])
            ->assertStatus(422)->assertJsonValidationErrors(['parent_id']);
    }

    public function test_visibility_follows_the_move(): void
    {
        $userA = $this->makeUser(Roles::ORG_HEAD, $this->a, true);
        $userB = $this->makeUser(Roles::ORG_HEAD, $this->b, true);
        $this->actingAs($userA);
        $this->getJson("/api/v1/organizations/{$this->a1->id}")->assertOk();

        $this->actingAs($this->head);
        $this->putJson("/api/v1/organizations/{$this->a1->id}", ['parent_id' => $this->b->id])->assertOk();

        $this->actingAs($userA);
        $this->getJson("/api/v1/organizations/{$this->a1->id}")->assertNotFound();
        $this->getJson("/api/v1/organizations/{$this->a1a->id}")->assertNotFound();
        $this->actingAs($userB);
        $this->getJson("/api/v1/organizations/{$this->a1->id}")->assertOk();
        $this->getJson("/api/v1/organizations/{$this->a1a->id}")->assertOk();
    }
}
