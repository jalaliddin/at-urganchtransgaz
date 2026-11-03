<?php

namespace Tests\Feature\Access;

use App\Models\Organization;
use App\Support\Access\OrganizationAccessResolver;
use App\Support\Permissions\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithAccess;
use Tests\TestCase;

class OrganizationAccessResolverTest extends TestCase
{
    use InteractsWithAccess, RefreshDatabase;

    private Organization $root;

    private Organization $a;

    private Organization $a1;

    private Organization $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = Organization::factory()->create();
        $this->a = Organization::factory()->childOf($this->root)->create();
        $this->a1 = Organization::factory()->childOf($this->a)->create();
        $this->b = Organization::factory()->childOf($this->root)->create();
    }

    private function resolver(): OrganizationAccessResolver
    {
        return app(OrganizationAccessResolver::class);
    }

    public function test_super_admin_is_unrestricted(): void
    {
        $user = $this->makeUser(Roles::SUPER_ADMIN);

        $scope = $this->resolver()->resolve($user);

        $this->assertTrue($scope->isUnrestricted());
        $this->assertTrue($scope->allows($this->b->id));
        $this->assertTrue($scope->allows(null));
    }

    public function test_access_with_descendants_includes_whole_subtree(): void
    {
        $user = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->a, descendants: true);

        $ids = $this->resolver()->resolve($user)->organizationIds();
        sort($ids);

        $this->assertSame([$this->a->id, $this->a1->id], $ids);
    }

    public function test_access_without_descendants_is_exact(): void
    {
        $user = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->a);

        $scope = $this->resolver()->resolve($user);

        $this->assertSame([$this->a->id], $scope->organizationIds());
        $this->assertFalse($scope->allows($this->a1->id));
        $this->assertFalse($scope->allows(null));
    }

    public function test_future_child_is_covered_after_forget(): void
    {
        $user = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->a, descendants: true);
        $this->resolver()->resolve($user);

        $a2 = Organization::factory()->childOf($this->a)->create();
        $this->resolver()->forget($user);

        $this->assertTrue($this->resolver()->resolve($user)->allows($a2->id));
    }

    public function test_no_access_rows_means_empty_scope(): void
    {
        $user = $this->makeUser(Roles::EMPLOYEE);

        $scope = $this->resolver()->resolve($user);

        $this->assertFalse($scope->isUnrestricted());
        $this->assertSame([], $scope->organizationIds());
        $this->assertFalse($scope->allows($this->a->id));
    }

    public function test_covers_subtree(): void
    {
        $withDescendants = $this->makeUser(Roles::CENTRAL_IT_HEAD, $this->a, descendants: true);
        $exact = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->a);

        $this->assertTrue($this->resolver()->resolve($withDescendants)->coversSubtreeOf($this->a));
        $this->assertFalse($this->resolver()->resolve($exact)->coversSubtreeOf($this->a));
        $this->assertFalse($this->resolver()->resolve($exact)->coversSubtreeOf($this->a1->fresh()));
    }

    public function test_user_helpers_delegate_to_resolver(): void
    {
        $user = $this->makeUser(Roles::ORG_IT_RESPONSIBLE, $this->a);

        $this->assertTrue($user->canAccessOrganization($this->a->id));
        $this->assertFalse($user->canAccessOrganization($this->b->id));
        $this->assertSame([$this->a->id], $user->accessScope()->organizationIds());
    }
}
