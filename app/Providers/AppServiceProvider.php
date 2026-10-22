<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Campaign;
use App\Models\Task;
use App\Models\User;
use App\Observers\CampaignObserver;
use App\Observers\TaskObserver;
use App\Observers\UserObserver;

class AppServiceProvider extends ServiceProvider
{
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
        Campaign::observe(CampaignObserver::class);
        Task::observe(TaskObserver::class);
        User::observe(UserObserver::class);
    }
}
