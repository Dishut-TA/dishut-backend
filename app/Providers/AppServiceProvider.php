<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

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
        JsonResource::withoutWrapping();
        // Wait, if we use withoutWrapping, it removes data wrapper completely, but we want 'payload'.
        // Wait, actually JsonResource::wrap('payload'); is the correct way.
        JsonResource::wrap('payload');
    }
}
