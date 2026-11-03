<?php

namespace Tests\Feature\Models;

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_gets_path_and_depth(): void
    {
        $root = Organization::factory()->create();

        $this->assertSame("/{$root->id}/", $root->fresh()->path);
        $this->assertSame(0, $root->fresh()->depth);
    }

    public function test_child_and_grandchild_paths(): void
    {
        $root = Organization::factory()->create();
        $child = Organization::factory()->childOf($root)->create();
        $grand = Organization::factory()->childOf($child)->create();

        $this->assertSame("/{$root->id}/{$child->id}/", $child->fresh()->path);
        $this->assertSame("/{$root->id}/{$child->id}/{$grand->id}/", $grand->fresh()->path);
        $this->assertSame(2, $grand->fresh()->depth);
    }

    public function test_descendants_and_self(): void
    {
        $root = Organization::factory()->create();
        $child = Organization::factory()->childOf($root)->create();
        $grand = Organization::factory()->childOf($child)->create();
        Organization::factory()->create(); // another tree

        $ids = $root->fresh()->descendantsAndSelf()->orderBy('id')->pluck('id')->all();

        $this->assertSame([$root->id, $child->id, $grand->id], $ids);
    }
}
