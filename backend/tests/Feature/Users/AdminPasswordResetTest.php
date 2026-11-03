<?php

namespace Tests\Feature\Users;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\PasswordResetByAdmin;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class AdminPasswordResetTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    private Organization $root;

    private User $head;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->root = Organization::factory()->create();
        $this->head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->root, true);
    }

    public function test_reset_returns_temporary_password_and_notifies(): void
    {
        $target = $this->makeUser(Roles::EMPLOYEE, $this->root);
        DB::table('sessions')->insert(['id' => 's1', 'user_id' => $target->id, 'payload' => '', 'last_activity' => time()]);
        $this->actingAs($this->head);

        $temporary = $this->postJson("/api/v1/users/{$target->id}/reset-password")->assertOk()->json('data.temporary_password');

        $fresh = $target->fresh();
        $this->assertTrue(Hash::check($temporary, $fresh->password));
        $this->assertTrue($fresh->must_change_password);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
        $this->assertSame(1, $fresh->notifications()->where('type', PasswordResetByAdmin::class)->count());
        $this->assertSame(1, AuditLog::where('event', 'password_reset')->where('auditable_id', $target->id)->count());
        $this->assertStringNotContainsString($temporary, json_encode(AuditLog::all()->toArray()));
    }

    public function test_non_super_admin_cannot_reset_super_admin(): void
    {
        $super = $this->makeUser(Roles::SUPER_ADMIN);
        $super->update(['organization_id' => $this->root->id]);
        $this->actingAs($this->head);

        $this->postJson("/api/v1/users/{$super->id}/reset-password")->assertForbidden();
    }
}
