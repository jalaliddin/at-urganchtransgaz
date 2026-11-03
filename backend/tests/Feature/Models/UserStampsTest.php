<?php

namespace Tests\Feature\Models;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserStampsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stamps_are_set_when_authenticated(): void
    {
        $creator = User::factory()->create();
        $editor = User::factory()->create();

        $this->actingAs($creator);
        $department = Department::factory()->create();

        $this->assertSame($creator->id, $department->created_by);
        $this->assertSame($creator->id, $department->updated_by);

        $this->actingAs($editor);
        $department->update(['name' => 'Yangi nom']);

        $this->assertSame($creator->id, $department->fresh()->created_by);
        $this->assertSame($editor->id, $department->fresh()->updated_by);
    }

    public function test_stamps_are_null_without_authenticated_user(): void
    {
        $department = Department::factory()->create();

        $this->assertNull($department->created_by);
        $this->assertNull($department->updated_by);
    }
}
