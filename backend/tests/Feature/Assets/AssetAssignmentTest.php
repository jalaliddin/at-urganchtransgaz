<?php

namespace Tests\Feature\Assets;

use App\Enums\EmployeeStatus;
use App\Models\Asset;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\Assets\AssetsReturnRequiredNotification;
use App\Services\Employees\EmployeeService;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class AssetAssignmentTest extends TestCase
{
    use CreatesAssets, InteractsWithAccess, RefreshDatabase;

    private Organization $org;

    private User $it;

    private Employee $employee;

    private Asset $asset;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAssets();
        $this->org = Organization::factory()->create();
        $this->it = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->org);
        $this->employee = Employee::factory()->create(['organization_id' => $this->org->id]);
        $this->asset = $this->assetIn($this->org, $this->it);
    }

    private function url(string $suffix): string
    {
        return "/api/v1/assets/{$this->asset->id}{$suffix}";
    }

    public function test_assign_and_return_with_history(): void
    {
        $this->actingAs($this->it);

        $this->postJson($this->url('/assign'), ['employee_id' => $this->employee->id, 'note' => 'Yangi ish joyi'])
            ->assertOk()->assertJsonPath('data.status', 'in_use')->assertJsonPath('data.holder.id', $this->employee->id);
        $other = Employee::factory()->create(['organization_id' => $this->org->id]);
        $this->postJson($this->url('/assign'), ['employee_id' => $other->id])->assertOk()->assertJsonPath('data.holder.id', $other->id);

        $this->postJson($this->url('/return'), ['note' => 'Ishchi joy almashdi'])
            ->assertOk()->assertJsonPath('data.status', 'in_stock')->assertJsonPath('data.holder', null);

        $history = $this->getJson($this->url('/assignments'))->assertOk()->json('data');
        $this->assertCount(2, $history);
        $this->assertNotNull($history[0]['returned_at']);
        $this->assertSame($other->id, $history[0]['employee']['id']);
        $this->assertDatabaseHas('asset_events', ['asset_id' => $this->asset->id, 'action' => 'returned']);
        $this->postJson($this->url('/return'))->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
    }

    public function test_assignee_must_be_an_active_employee_of_the_asset_organization(): void
    {
        $this->actingAs($this->it);
        $stranger = Employee::factory()->create(['organization_id' => Organization::factory()->create()->id]);
        $dismissed = Employee::factory()->create(['organization_id' => $this->org->id, 'status' => EmployeeStatus::Dismissed]);

        $this->postJson($this->url('/assign'), ['employee_id' => $stranger->id])->assertStatus(422)->assertJsonValidationErrors('employee_id');
        $this->postJson($this->url('/assign'), ['employee_id' => $dismissed->id])->assertStatus(422)->assertJsonValidationErrors('employee_id');

        $this->asset->forceFill(['status' => 'written_off'])->save();
        $this->postJson($this->url('/assign'), ['employee_id' => $this->employee->id])->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
    }

    public function test_assign_permission(): void
    {
        $this->actingAs($this->makeUser(Roles::ORG_HEAD, $this->org));

        $this->postJson($this->url('/assign'), ['employee_id' => $this->employee->id])->assertForbidden();
    }

    public function test_employee_card_and_my_assets(): void
    {
        $user = $this->makeUser(Roles::EMPLOYEE, $this->org);
        $user->forceFill(['employee_id' => $this->employee->id])->save();
        $this->actingAs($this->it);
        $this->postJson($this->url('/assign'), ['employee_id' => $this->employee->id])->assertOk();
        $this->assetIn($this->org, $this->it, ['name' => 'Zaxira monitor']);

        $this->getJson("/api/v1/employees/{$this->employee->id}/assets")->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->asset->id);

        $this->actingAs($user->fresh());
        $this->getJson('/api/v1/assets/mine')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.inventory_number', $this->asset->inventory_number);
        $this->getJson('/api/v1/assets')->assertForbidden();
        $this->getJson("/api/v1/employees/{$this->employee->id}/assets")->assertForbidden();
    }

    public function test_dismissal_asks_asset_managers_to_collect_held_assets(): void
    {
        Notification::fake();
        $this->actingAs($this->it);
        $this->postJson($this->url('/assign'), ['employee_id' => $this->employee->id])->assertOk();
        $elsewhere = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, Organization::factory()->create());

        app(EmployeeService::class)->update($this->employee, ['status' => 'dismissed', 'dismissed_at' => now()->toDateString()], $this->it);

        Notification::assertSentTo($this->it, AssetsReturnRequiredNotification::class,
            fn ($n, $c, $u) => $n->toArray($u)['params']['count'] === 1 && $n->toArray($u)['url'] === "/employees/{$this->employee->id}");
        Notification::assertNotSentTo($elsewhere, AssetsReturnRequiredNotification::class);
    }
}
