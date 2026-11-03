<?php

namespace App\Services\Files;

use App\Models\StoredFile;
use App\Models\User;
use Closure;

/**
 * Decides whether a user may download a stored file. Each owning module
 * registers a resolver that returns true/false for files it owns and null for
 * files it does not know. Any denial wins; with no owner the file is hidden.
 */
class FileAccess
{
    /** @var list<Closure(User, StoredFile): ?bool> */
    private array $resolvers = [];

    /** @param  Closure(User, StoredFile): ?bool  $resolver */
    public function register(Closure $resolver): void
    {
        $this->resolvers[] = $resolver;
    }

    public function canDownload(User $user, StoredFile $file): bool
    {
        $allowed = false;
        foreach ($this->resolvers as $resolver) {
            $decision = $resolver($user, $file);
            if ($decision === false) {
                return false;
            }
            $allowed = $allowed || $decision === true;
        }

        return $allowed;
    }
}
