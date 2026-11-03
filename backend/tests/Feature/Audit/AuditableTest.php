<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class AuditableTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actor = $this->makeUser(Roles::SUPER_ADMIN);
        $this->actingAs($this->actor);
    }

    private function lastLogFor(string $type, int $id, string $event): ?AuditLog
    {
        return AuditLog::where('auditable_type', $type)->where('auditable_id', $id)
            ->where('event', $event)->latest('id')->first();
    }

    public function test_create_is_audited(): void
    {
        $employee = Employee::factory()->create(['last_name' => 'Karimov']);

        $log = $this->lastLogFor('employee', $employee->id, 'created');

        $this->assertNotNull($log);
        $this->assertSame($this->actor->id, $log->user_id);
        $this->assertSame($this->actor->username, $log->user_label);
        $this->assertSame($employee->organization_id, $log->organization_id);
        $this->assertSame('Karimov', $log->new_values['last_name']);
        $this->assertArrayNotHasKey('updated_at', $log->new_values);
        $this->assertArrayNotHasKey('created_by', $log->new_values);
    }

    public function test_update_records_only_changes(): void
    {
        $employee = Employee::factory()->create(['position' => 'Muhandis']);

        $employee->update(['position' => 'Bosh muhandis']);

        $log = $this->lastLogFor('employee', $employee->id, 'updated');
        $this->assertSame(['position' => 'Muhandis'], $log->old_values);
        $this->assertSame(['position' => 'Bosh muhandis'], $log->new_values);
    }

    public function test_delete_and_restore_are_audited(): void
    {
        $department = Department::factory()->create();

        $department->delete();
        $department->restore();

        $this->assertNotNull($this->lastLogFor('department', $department->id, 'deleted'));
        $this->assertNotNull($this->lastLogFor('department', $department->id, 'restored'));
        $this->assertNull($this->lastLogFor('department', $department->id, 'updated'));
    }

    public function test_password_is_never_logged(): void
    {
        $user = User::factory()->create(['name' => 'Eski']);
        $createdLog = $this->lastLogFor('user', $user->id, 'created');
        $this->assertArrayNotHasKey('password', $createdLog->new_values);

        $user->update(['name' => 'Yangi', 'password' => 'NewSecret123']);
        $log = $this->lastLogFor('user', $user->id, 'updated');
        $this->assertSame(['name' => 'Yangi'], $log->new_values);

        $countBefore = AuditLog::count();
        $user->update(['password' => 'Another123']);
        $this->assertSame($countBefore, AuditLog::count());
    }

    public function test_only_excluded_fields_changed_writes_nothing(): void
    {
        $department = Department::factory()->create();
        $countBefore = AuditLog::count();

        $department->touch();

        $this->assertSame($countBefore, AuditLog::count());
    }

    public function test_request_context_is_captured(): void
    {
        $department = Department::factory()->create(['name' => 'Eski']);
        Route::middleware('api')->post('api/v1/__test/departments/{id}', function (int $id) {
            Department::findOrFail($id)->update(['name' => 'Yangi']);

            return ['ok' => true];
        });
        $requestId = (string) Str::uuid();

        $this->postJson("/api/v1/__test/departments/{$department->id}", [], ['X-Request-Id' => $requestId])->assertOk();

        $log = $this->lastLogFor('department', $department->id, 'updated');
        $this->assertSame($requestId, $log->request_id);
        $this->assertSame('POST', $log->http_method);
        $this->assertSame('127.0.0.1', $log->ip_address);
        $this->assertSame("/api/v1/__test/departments/{$department->id}", $log->url);
    }

    public function test_organization_subject_uses_its_own_id(): void
    {
        $organization = Organization::factory()->create();

        $this->assertSame($organization->id, $this->lastLogFor('organization', $organization->id, 'created')->organization_id);
    }

    public function test_console_context_has_no_request_data_or_actor(): void
    {
        auth()->forgetGuards();

        $department = Department::factory()->create();

        $log = $this->lastLogFor('department', $department->id, 'created');
        $this->assertNull($log->user_id);
        $this->assertNull($log->request_id);
        $this->assertNull($log->http_method);
    }
}
