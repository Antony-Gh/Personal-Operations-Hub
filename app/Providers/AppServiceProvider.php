<?php

namespace App\Providers;

use App\Models\User;
use App\Notifications\OperationalNotification;
use App\Services\WindowsToastService;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
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
        Vite::prefetch(concurrency: 3);

        Event::listen(NotificationSent::class, function (NotificationSent $event): void {
            if (
                $event->channel === 'database'
                && $event->notifiable instanceof User
                && $event->notification instanceof OperationalNotification
            ) {
                app(WindowsToastService::class)->notifyIfEnabled(
                    user: $event->notifiable,
                    title: $event->notification->title,
                    message: $event->notification->message,
                    category: $event->notification->category,
                    isCritical: $event->notification->isCritical,
                );
            }
        });
    }
}
