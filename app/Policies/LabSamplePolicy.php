<?php

namespace App\Policies;

use App\Models\LabSample;
use App\Models\User;

class LabSamplePolicy
{
    public function receive(User $user, LabSample $sample): bool
    {
        return $user->hasPermission('collect_lab_specimens');
    }

    public function reject(User $user, LabSample $sample): bool
    {
        return $user->hasPermission('collect_lab_specimens');
    }
}
