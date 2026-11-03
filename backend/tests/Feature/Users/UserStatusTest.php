<?php

namespace Tests\Feature\Users;

use App\Exceptions\DomainConflictException;
use App\Models\Organization;
use App\Models\User;
use App\Services\Users\UserService;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class UserStatusTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    private User $head;

    private User $target;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $root = Organization::factory()->create();
        $a = Organization::factory()->childOf($root)->create();
        $this->head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $root, true);
        $this->target = $this->makeUser(Roles::EMPLOYEE, $a);
    }

    public function test_deactivate_revokes_sessions_and_activate_restores(): void
    {
        DB::table('sessions')->insert(['id' => 's1', 'user_id' => $this->target->id, 'payload' => '', 'last_activity' => time()]);
        $this->actingAs($this->head);

        $this->postJson("/api/v1/users/{$this->target->id}/deactivate")->assertOk()->assertJsonPath('data.is_active', false);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->target->id)->count());

        $this->postJson("/api/v1/users/{$this->target->id}/activate")->assertOk()->assertJsonPath('data.is_active', true);
    }

    public function test_cannot_deactivate_self(): void
    {
        $this->actingAs($this->head);

        $this->postJson("/api/v1/users/{$this->head->id}/deactivate")->assertForbidden();
    }

    public function test_last_super_admin_cannot_be_deactivated_by_system_callers(): void
    {
        $only = $this->makeUser(Roles::SUPER_ADMIN);

        $this->expectException(DomainConflictException::class);
        app(UserService::class)->deactivate($only, null);
    }

    public function test_user_without_permission_cannot_deactivate(): void
    {
        $this->actingAs($this->makeUser(Roles::CENTRAL_IT_STAFF, Organization::first(), true));

        $this->postJson("/api/v1/users/{$this->target->id}/deactivate")->assertForbidden();
    }
}
