<?php

namespace App\Policies;

use App\Models\LabOrderItem;
use App\Models\User;

class LabOrderItemPolicy
{
    public function enterResult(User $user, LabOrderItem $item): bool
    {
        return $user->hasPermission('enter_lab_results');
    }

    /**
     * A Medical Laboratory Scientist is licensed to authorise a result they
     * produced themselves, so holding the permission is the rule that binds.
     * Facilities wanting a strict four-eyes separation switch on
     * `lab.require_second_person`, which additionally bars the person who
     * entered the value from signing it off.
     */
    public function authorizeResult(User $user, LabOrderItem $item): bool
    {
        if (! $user->hasPermission('authorize_lab_results')) {
            return false;
        }

        if (config('lab.require_second_person') && $item->entered_by === $user->id) {
            return false;
        }

        return true;
    }
}
