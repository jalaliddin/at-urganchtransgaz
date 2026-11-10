<?php

namespace Tests\Feature\Itsm;

use App\Models\Organization;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class ItsmPeopleTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    public function test_pickers_offer_active_people_who_may_take_the_work_in_the_organization(): void
    {
        $this->seedRbac();
        $center = Organization::factory()->create();
        $branch = Organization::factory()->create(['parent_id' => $center->id]);
        $head = $this->makeUser(Roles::CENTRAL_IT_HEAD, $center, true);
        $staff = $this->makeUser(Roles::CENTRAL_IT_STAFF, $center, true);
        $it = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $branch);
        $this->makeUser(Roles::ORG_HEAD, $branch);
        $this->makeUser(Roles::ORG_IT_RESPONSIBLE, Organization::factory()->create());
        $gone = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $branch);
        $gone->forceFill(['is_active' => false])->save();

        $ids = fn (string $for) => array_column($this->actingAs($it)->getJson("/api/v1/itsm/people?for={$for}&organization_id={$branch->id}")
            ->assertOk()->json('data'), 'id');
        $this->assertEqualsCanonicalizing([$head->id, $staff->id, $it->id], $ids('problem'));
        $this->assertEqualsCanonicalizing([$head->id, $staff->id, $it->id], $ids('change'));
        $this->assertEqualsCanonicalizing([$head->id, $staff->id, $it->id], $ids('maintenance'));
        $this->actingAs($it)->getJson("/api/v1/itsm/people?for=problem&organization_id={$center->id}")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/itsm/people?for=task&organization_id={$branch->id}")->assertStatus(422);
        $this->actingAs($this->makeUser(Roles::ORG_HEAD, $branch))->getJson("/api/v1/itsm/people?for=change&organization_id={$branch->id}")->assertForbidden();
    }
}
