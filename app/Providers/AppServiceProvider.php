<?php

namespace App\Providers;

use App\Models\LabOrder;
use App\Models\LabOrderItem;
use App\Models\LabSample;
use App\Policies\LabOrderItemPolicy;
use App\Policies\LabOrderPolicy;
use App\Policies\LabSamplePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Policies are registered explicitly rather than left to discovery so the
     * professional boundaries the laboratory runs on are readable in one place.
     *
     * @var array<class-string, class-string>
     */
    private const POLICIES = [
        LabOrder::class => LabOrderPolicy::class,
        LabOrderItem::class => LabOrderItemPolicy::class,
        LabSample::class => LabSamplePolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
