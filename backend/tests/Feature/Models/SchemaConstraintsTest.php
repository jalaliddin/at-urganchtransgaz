<?php

namespace Tests\Feature\Models;

use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_personnel_number_is_unique_per_organization(): void
    {
        $a = Organization::factory()->create();
        $b = Organization::factory()->create();
        Employee::factory()->for($a)->create(['personnel_number' => '00412']);

        Employee::factory()->for($b)->create(['personnel_number' => '00412']);
        $this->assertSame(2, Employee::where('personnel_number', '00412')->count());

        $this->expectException(QueryException::class);
        Employee::factory()->for($a)->create(['personnel_number' => '00412']);
    }

    public function test_username_is_unique(): void
    {
        User::factory()->create(['username' => 'a.karimov']);

        $this->expectException(QueryException::class);
        User::factory()->create(['username' => 'a.karimov']);
    }

    public function test_user_email_is_nullable_but_unique(): void
    {
        User::factory()->create(['email' => null]);
        User::factory()->create(['email' => null]);
        User::factory()->create(['email' => 'it@urtg.uz']);

        $this->expectException(QueryException::class);
        User::factory()->create(['email' => 'it@urtg.uz']);
    }

    public function test_user_hides_secret_attributes(): void
    {
        $user = User::factory()->create(['two_factor_secret' => 'SECRET']);

        $array = $user->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
        $this->assertArrayNotHasKey('two_factor_secret', $array);
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $array);
    }

    public function test_employee_full_name(): void
    {
        $employee = Employee::factory()->make([
            'last_name' => 'Karimov', 'first_name' => 'Aziz', 'middle_name' => null,
        ]);

        $this->assertSame('Karimov Aziz', $employee->full_name);
    }

    public function test_user_for_employee_inherits_organization(): void
    {
        $employee = Employee::factory()->create();

        $user = User::factory()->forEmployee($employee)->create();

        $this->assertSame($employee->organization_id, $user->organization_id);
        $this->assertTrue($user->employee->is($employee));
    }
}
