<?php

namespace Tests\Feature\Assets;

use App\Exceptions\InvalidTransitionException;
use App\Models\AssetDisposal;
use App\Models\AssetTransfer;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use App\Services\Assets\AssetLifecycle;
use App\Services\Assets\AssetTransferService;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesAssets;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

/** Regressions from the Phase 3 final review. */
class AssetReviewFixesTest extends TestCase
{
    use CreatesAssets, InteractsWithAccess, RefreshDatabase;

    private Organization $a;

    private Organization $b;

    private User $itA;

    private User $itB;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seedAssets();
        $this->a = Organization::factory()->create();
        $this->b = Organization::factory()->create();
        $this->itA = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->a);
        $this->itB = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->b);
    }

    public function test_returning_an_asset_under_repair_keeps_it_in_repair(): void
    {
        $asset = $this->assetIn($this->a, $this->itA);
        $employee = Employee::factory()->create(['organization_id' => $this->a->id]);
        $this->actingAs($this->itA);
        $this->postJson("/api/v1/assets/{$asset->id}/assign", ['employee_id' => $employee->id])->assertOk();
        $repair = $this->postJson("/api/v1/assets/{$asset->id}/maintenances", ['description' => 'Ekran'])->json('data.id');

        $this->postJson("/api/v1/assets/{$asset->id}/return")->assertOk()->assertJsonPath('data.status', 'in_repair')->assertJsonPath('data.holder', null);
        $this->postJson("/api/v1/assets/{$asset->id}/assign", ['employee_id' => $employee->id])->assertStatus(422);

        $this->postJson("/api/v1/assets/{$asset->id}/maintenances/{$repair}/close", ['result' => 'Tuzatildi'])->assertOk();
        $this->assertSame('in_stock', $asset->fresh()->status->value);
    }

    public function test_transfer_transitions_recheck_status_under_lock(): void
    {
        $asset = $this->assetIn($this->a, $this->itA);
        $service = app(AssetTransferService::class);
        $transfer = $service->create($this->a->id, $this->b->id, null, $this->itA);
        $service->addItems($transfer, [$asset->id], null, $this->itA);
        $service->send($transfer, $this->itA);

        $staleForSender = AssetTransfer::findOrFail($transfer->id);
        $service->receive(AssetTransfer::findOrFail($transfer->id), $this->itB, null);

        $this->actingAs($this->itA);
        $this->expectException(InvalidTransitionException::class);
        try {
            $service->cancel($staleForSender, $this->itA);
        } finally {
            $this->assertSame('received', $transfer->fresh()->status->value);
            $this->assertSame($this->b->id, $asset->fresh()->organization_id);
        }
    }

    public function test_disposal_decision_rechecks_status_under_lock(): void
    {
        $head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->a);
        $asset = $this->assetIn($this->a, $this->itA);
        $lifecycle = app(AssetLifecycle::class);
        $disposal = $lifecycle->requestDisposal($asset, 'Eskirgan', null, $this->itA);
        $stale = AssetDisposal::findOrFail($disposal->id);

        $lifecycle->decide(AssetDisposal::findOrFail($disposal->id), true, null, $head);

        $this->expectException(InvalidTransitionException::class);
        try {
            $lifecycle->decide($stale, false, 'Kech', $head);
        } finally {
            $this->assertSame('approved', $disposal->fresh()->status->value);
        }
    }

    public function test_inventory_number_cannot_be_cleared_on_edit(): void
    {
        $asset = $this->assetIn($this->a, $this->itA);
        $this->actingAs($this->itA);

        $this->patchJson("/api/v1/assets/{$asset->id}", ['inventory_number' => null])->assertStatus(422)->assertJsonValidationErrors('inventory_number');
        $this->patchJson("/api/v1/assets/{$asset->id}", ['inventory_number' => ''])->assertStatus(422);
        $this->patchJson("/api/v1/assets/{$asset->id}", ['inventory_number' => 'INV-77'])->assertOk()->assertJsonPath('data.inventory_number', 'INV-77');
    }

    public function test_assets_in_repair_or_lost_cannot_be_transferred(): void
    {
        $repairing = $this->assetIn($this->a, $this->itA);
        $lost = $this->assetIn($this->a, $this->itA);
        $lost->forceFill(['status' => 'lost'])->save();
        $this->actingAs($this->itA);
        $this->postJson("/api/v1/assets/{$repairing->id}/maintenances", ['description' => 'Ekran'])->assertCreated();
        $transfer = $this->postJson('/api/v1/asset-transfers', ['from_organization_id' => $this->a->id, 'to_organization_id' => $this->b->id])->json('data.id');

        $this->postJson("/api/v1/asset-transfers/{$transfer}/items", ['asset_ids' => [$repairing->id]])->assertStatus(422);
        $this->postJson("/api/v1/asset-transfers/{$transfer}/items", ['asset_ids' => [$lost->id]])->assertStatus(422);
    }

    public function test_lookup_accepts_inventory_numbers_with_slashes(): void
    {
        $asset = $this->assetIn($this->a, $this->itA, ['inventory_number' => '0012/34']);
        $this->actingAs($this->itA);

        $this->getJson('/api/v1/assets/lookup/0012/34')->assertOk()->assertJsonPath('data.id', $asset->id);
        $this->getJson('/api/v1/assets/lookup/0012%2F34')->assertOk()->assertJsonPath('data.id', $asset->id);
    }
}
