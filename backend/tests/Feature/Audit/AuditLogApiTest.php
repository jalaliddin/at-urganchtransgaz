<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class AuditLogApiTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    private Organization $root;

    private Organization $a;

    private Organization $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->root = Organization::factory()->create();
        $this->a = Organization::factory()->childOf($this->root)->create();
        $this->b = Organization::factory()->childOf($this->root)->create();
    }

    /** Signs in as an auditor, then empties the log so only the test's rows remain. */
    private function actAsAuditor(Organization $organization, bool $descendants = false): void
    {
        $this->actingAs($this->makeUser(Roles::AUDITOR, $organization, $descendants));
        DB::table('audit_logs')->delete();
    }

    private function log(?int $organizationId, string $createdAt = '2026-11-01 08:00:00', string $event = 'login', ?int $auditableId = null): int
    {
        return DB::table('audit_logs')->insertGetId([
            'event' => $event, 'organization_id' => $organizationId, 'created_at' => $createdAt,
            'auditable_type' => $auditableId ? 'employee' : null, 'auditable_id' => $auditableId,
        ]);
    }

    private function ids(TestResponse $response): array
    {
        return collect($response->assertOk()->json('data'))->pluck('id')->sort()->values()->all();
    }

    public function test_auditor_with_root_access_lists_logs(): void
    {
        $this->actAsAuditor($this->root, true);
        $ids = [$this->log($this->a->id), $this->log($this->b->id)];

        $this->assertSame($ids, $this->ids($this->getJson('/api/v1/audit-logs')));
    }

    public function test_employee_is_forbidden(): void
    {
        $this->actingAs($this->makeUser(Roles::EMPLOYEE, $this->a));

        $this->getJson('/api/v1/audit-logs')->assertForbidden();
    }

    public function test_scoped_auditor_sees_only_own_organization(): void
    {
        $this->actAsAuditor($this->a);
        $mine = $this->log($this->a->id);
        $foreign = $this->log($this->b->id);
        $system = $this->log(null);

        $this->assertSame([$mine], $this->ids($this->getJson('/api/v1/audit-logs')));
        $this->getJson("/api/v1/audit-logs/{$foreign}")->assertNotFound();
        $this->getJson("/api/v1/audit-logs/{$system}")->assertNotFound();
        $this->getJson("/api/v1/audit-logs/{$mine}")->assertOk()->assertJsonPath('data.event', 'login');
    }

    public function test_date_filters_use_tashkent_days(): void
    {
        // 2026-11-02 19:30 UTC is 2026-11-03 00:30 in Tashkent.
        $this->actAsAuditor($this->root, true);
        $id = $this->log($this->a->id, '2026-11-02 19:30:00');

        $this->assertSame([$id], $this->ids($this->getJson('/api/v1/audit-logs?filter[created_from]=2026-11-03')));
        $this->assertSame([], $this->ids($this->getJson('/api/v1/audit-logs?filter[created_to]=2026-11-02')));
        $this->assertSame([$id], $this->ids($this->getJson('/api/v1/audit-logs?filter[created_to]=2026-11-03')));
        $this->getJson('/api/v1/audit-logs?filter[created_from]=03.11.2026')->assertStatus(400);
    }

    public function test_filter_by_auditable_and_organization(): void
    {
        $this->actAsAuditor($this->root, true);
        $match = $this->log($this->a->id, auditableId: 77);
        $this->log($this->a->id, auditableId: 78);
        $this->log($this->b->id);

        $this->assertSame([$match], $this->ids($this->getJson('/api/v1/audit-logs?filter[auditable_type]=employee&filter[auditable_id]=77')));
        $this->assertCount(2, $this->ids($this->getJson("/api/v1/audit-logs?filter[organization]={$this->a->id}")));
    }

    public function test_only_created_at_is_sortable(): void
    {
        $this->actingAs($this->makeUser(Roles::AUDITOR, $this->root, true));

        $this->getJson('/api/v1/audit-logs?sort=-created_at')->assertOk();
        $this->getJson('/api/v1/audit-logs?sort=event')->assertStatus(400);
    }

    public function test_resource_exposes_actor_and_labels(): void
    {
        $actor = $this->makeUser(Roles::AUDITOR, $this->root, true);
        $this->actingAs($actor);
        $this->a->update(['name' => 'Yangilangan nom']);

        $row = AuditLog::where('event', 'updated')->where('auditable_type', 'organization')->first();
        $this->getJson("/api/v1/audit-logs/{$row->id}")->assertOk()
            ->assertJsonPath('data.user.username', $actor->username)
            ->assertJsonPath('data.event_label', __('enums.audit_event.updated'))
            ->assertJsonPath('data.organization.id', $this->a->id)
            ->assertJsonPath('data.new_values.name', 'Yangilangan nom');
    }
}
