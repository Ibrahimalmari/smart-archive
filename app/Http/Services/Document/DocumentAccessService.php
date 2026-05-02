<?php

namespace App\Http\Services\Document;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DocumentAccessService
{
    public function scopedQuery(User $user): Builder
    {
        $query = Document::with('user', 'organization', 'department');

        if ($user->role === 'SuperAdmin') {
            return $query;
        }

        if ($user->role === 'Admin') {
            return $query->where('organization_id', $user->organization_id);
        }

        if (in_array($user->role, ['Manager', 'Employee', 'Auditor'], true)) {
            return $query->where('department_id', $user->department_id);
        }

        return $query->whereRaw('1 = 0');
    }

    public function canCreate(User $user, int|string|null $organizationId, int|string|null $departmentId): bool
    {
        if ($user->role === 'Auditor') {
            return false;
        }

        if ($user->role === 'Admin') {
            return (int) $organizationId === (int) $user->organization_id;
        }

        if (in_array($user->role, ['Employee', 'Manager'], true)) {
            return (int) $departmentId === (int) $user->department_id;
        }

        return $user->role === 'SuperAdmin';
    }

    public function canAccess(User $user, Document $document): bool
    {
        if ($user->role === 'SuperAdmin') {
            return true;
        }

        if ($user->role === 'Admin') {
            return (int) $document->organization_id === (int) $user->organization_id;
        }

        if (in_array($user->role, ['Manager', 'Employee', 'Auditor'], true)) {
            return (int) $document->department_id === (int) $user->department_id;
        }

        return false;
    }

    public function canUpdate(User $user, Document $document): bool
    {
        if ($user->role === 'Auditor') {
            return false;
        }

        if ($user->role === 'Employee') {
            return (int) $document->uploaded_by === (int) $user->id
                && (int) $document->department_id === (int) $user->department_id;
        }

        if ($user->role === 'Manager') {
            return (int) $document->department_id === (int) $user->department_id;
        }

        if ($user->role === 'Admin') {
            return (int) $document->organization_id === (int) $user->organization_id;
        }

        return $user->role === 'SuperAdmin';
    }

    public function canDelete(User $user, Document $document): bool
    {
        if (in_array($user->role, ['Employee', 'Auditor'], true)) {
            return false;
        }

        if ($user->role === 'Manager') {
            return (int) $document->department_id === (int) $user->department_id;
        }

        if ($user->role === 'Admin') {
            return (int) $document->organization_id === (int) $user->organization_id;
        }

        return $user->role === 'SuperAdmin';
    }

    public function canSubmitForReview(User $user, Document $document): bool
    {
        if ($user->role === 'Auditor') {
            return false;
        }

        if (!$this->canAccess($user, $document)) {
            return false;
        }

        if ($user->role === 'Employee') {
            return (int) $document->uploaded_by === (int) $user->id;
        }

        return in_array($user->role, ['SuperAdmin', 'Admin', 'Manager'], true);
    }

    public function canReviewWorkflow(User $user, Document $document): bool
    {
        return in_array($user->role, ['SuperAdmin', 'Admin', 'Manager'], true)
            && $this->canAccess($user, $document);
    }
}
