<?php

namespace App\Services\Search;

use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use App\Support\Search\SearchTerm;

/**
 * Header search. Each group appears only with its view permission and is limited
 * by the organization scope (global scopes) to 5 results.
 */
class GlobalSearch
{
    private const LIMIT = 5;

    /** @return array<string, list<array<string, mixed>>> */
    public function search(User $user, string $term): array
    {
        $results = [];

        if ($user->can('organizations.view')) {
            $results['organizations'] = SearchTerm::whereContains(Organization::query(), ['name', 'short_name', 'code', 'tin'], $term)
                ->orderBy('name')->limit(self::LIMIT)->get()
                ->map(fn (Organization $o): array => ['id' => $o->id, 'name' => $o->name, 'code' => $o->getAttribute('code')])
                ->values()->all();
        }

        if ($user->can('employees.view')) {
            $results['employees'] = SearchTerm::whereContains(
                Employee::query()->with('organization'),
                ['last_name', 'first_name', 'middle_name', 'personnel_number', 'phone'],
                $term,
            )->orderBy('last_name')->limit(self::LIMIT)->get()
                ->map(fn (Employee $e): array => [
                    'id' => $e->id,
                    'full_name' => $e->full_name,
                    'position' => $e->getAttribute('position'),
                    'organization' => $e->organization ? ['id' => $e->organization->id, 'name' => $e->organization->name] : null,
                ])->values()->all();
        }

        if ($user->can('users.view')) {
            $results['users'] = SearchTerm::whereContains(User::query(), ['username', 'name', 'email'], $term)
                ->orderBy('username')->limit(self::LIMIT)->get()
                ->map(fn (User $u): array => ['id' => $u->id, 'username' => $u->username, 'name' => $u->name])
                ->values()->all();
        }

        return $results;
    }
}
