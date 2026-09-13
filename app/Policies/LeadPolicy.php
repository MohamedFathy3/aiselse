<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

/**
 * Reference authorization pattern for CRM records (spec section 4 & 51):
 * Admin sees/manages everything; Sales sees/manages only what is assigned
 * to them. ClientPolicy, and any future entity policy, should mirror this
 * shape (`viewAny` for listing, per-record checks keyed on ownership).
 */
class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // both roles can list; the query itself is scoped by role in the controller
    }

    public function view(User $user, Lead $lead): bool
    {
        return $user->isAdmin() || $lead->assigned_to === $user->id;
    }

    public function create(User $user): bool
    {
        return true; // both Admin and Sales may create leads
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->isAdmin() || $lead->assigned_to === $user->id;
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->isAdmin();
    }

    public function convert(User $user, Lead $lead): bool
    {
        return ($user->isAdmin() || $lead->assigned_to === $user->id)
            && $lead->status->isOpen();
    }

    public function assign(User $user, Lead $lead): bool
    {
        return $user->isAdmin();
    }
}
