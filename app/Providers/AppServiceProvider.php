<?php

namespace App\Providers;

use App\Services\Groups\GroupCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(GroupCatalog::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Flash a notification that the layout shows bottom right on the next page.
        RedirectResponse::macro('notify', function (string $message, string $type = 'success'): RedirectResponse {
            /** @var RedirectResponse $this */
            return $this->with('notify', ['type' => $type, 'message' => $message]);
        });
    }
}
