<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('documents.view');
    }

    public function view(User $user, Document $document): bool
    {
        return $user->can('documents.view') && $user->canAccessOrganization($document->organization_id);
    }

    public function create(User $user): bool
    {
        return $user->can('documents.manage');
    }

    public function update(User $user, Document $document): bool
    {
        return $user->can('documents.manage') && $user->canAccessOrganization($document->organization_id);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }
}
