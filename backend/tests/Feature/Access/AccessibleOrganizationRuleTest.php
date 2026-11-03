<?php

namespace Tests\Feature\Access;

use App\Models\Organization;
use App\Rules\AccessibleOrganization;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class AccessibleOrganizationRuleTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    private function passes(mixed $value, bool $mustBeActive = true): bool
    {
        return Validator::make(
            ['organization_id' => $value],
            ['organization_id' => [new AccessibleOrganization($mustBeActive)]],
        )->passes();
    }

    public function test_passes_for_accessible_active_organization(): void
    {
        $own = Organization::factory()->create();
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $own));

        $this->assertTrue($this->passes($own->id));
    }

    public function test_fails_for_other_organization(): void
    {
        $own = Organization::factory()->create();
        $other = Organization::factory()->create();
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $own));

        $this->assertFalse($this->passes($other->id));
    }

    public function test_fails_for_unknown_or_malformed_id(): void
    {
        $this->actingAs($this->makeUser(Roles::SUPER_ADMIN));

        $this->assertFalse($this->passes(999999));
        $this->assertFalse($this->passes('abc'));
    }

    public function test_inactive_organization_depends_on_flag(): void
    {
        $inactive = Organization::factory()->inactive()->create();
        $this->actingAs($this->makeUser(Roles::ORG_IT_RESPONSIBLE, $inactive));

        $this->assertFalse($this->passes($inactive->id));
        $this->assertTrue($this->passes($inactive->id, mustBeActive: false));
    }

    public function test_fails_without_authenticated_user(): void
    {
        $org = Organization::factory()->create();

        $this->assertFalse($this->passes($org->id));
    }
}
