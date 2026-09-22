<?php

namespace App\Policies;

use App\Models\LabOrder;
use App\Models\User;

/**
 * The professional boundaries HEFRA and the AHPC enforce, expressed as gates.
 *
 * Collecting a specimen, entering a raw metric and authorising a result for
 * release are three separate acts with three separate licences behind them.
 * Every denial here is recorded as an `authorization_denied` audit row, which is
 * the paper trail an inspector asks for.
 */
class LabOrderPolicy
{
    public function view(User $user, LabOrder $labOrder): bool
    {
        return $user->hasPermission('view_clinical_history');
    }

    public function collectSpecimen(User $user, LabOrder $labOrder): bool
    {
        return $user->hasPermission('collect_lab_specimens');
    }

    /**
     * Releasing results is reserved to a licensed Medical Laboratory Scientist
     * or Pathologist. The per-item checks live on LabOrderItemPolicy.
     */
    public function authorizeResults(User $user, LabOrder $labOrder): bool
    {
        return $user->hasPermission('authorize_lab_results');
    }

    public function verify(User $user, LabOrder $labOrder): bool
    {
        return $user->hasPermission('authorize_lab_results');
    }

    /**
     * Review is the clinician reading the results, not the laboratory signing
     * them off, so it follows the ordering permission.
     */
    public function review(User $user, LabOrder $labOrder): bool
    {
        return $user->hasPermission('order_lab_tests');
    }

    public function acknowledgeCritical(User $user, LabOrder $labOrder): bool
    {
        return $user->hasPermission('order_lab_tests');
    }
}
