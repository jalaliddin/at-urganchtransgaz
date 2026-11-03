<?php

namespace App\Support\Access;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;

/**
 * The set of organizations a user may see. Immutable value object built by
 * OrganizationAccessResolver.
 */
final class AccessScope
{
    /** @var array<int, true> */
    private readonly array $lookup;

    /**
     * @param  list<int>  $organizationIds
     */
    public function __construct(
        private readonly bool $unrestricted,
        private readonly array $organizationIds = [],
    ) {
        $this->lookup = array_fill_keys($organizationIds, true);
    }

    public static function unrestricted(): self
    {
        return new self(true);
    }

    public function isUnrestricted(): bool
    {
        return $this->unrestricted;
    }

    /** @return list<int> */
    public function organizationIds(): array
    {
        return $this->organizationIds;
    }

    /** Null (records without an organization) is visible only to unrestricted users. */
    public function allows(?int $organizationId): bool
    {
        if ($this->unrestricted) {
            return true;
        }

        return $organizationId !== null && isset($this->lookup[$organizationId]);
    }

    /** True when every organization in $organization's subtree is accessible. */
    public function coversSubtreeOf(Organization $organization): bool
    {
        if ($this->unrestricted) {
            return true;
        }

        $subtree = DB::table('organizations')
            ->where('path', 'like', $organization->path.'%')
            ->whereNull('deleted_at')
            ->pluck('id');

        return $subtree->isNotEmpty() && $subtree->every(fn ($id) => isset($this->lookup[(int) $id]));
    }
}
