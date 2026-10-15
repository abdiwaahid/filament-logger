<?php

namespace Abdiwaahid\FilamentLogger\Loggers;

use Filament\Facades\Filament;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Failed;
use Spatie\Activitylog\ActivityLogger;
use Spatie\Activitylog\ActivityLogStatus;

class AccessLogger
{
    /**
     * Log user login
     *
     * @param  Login|Logout|Failed  $event
     * @return void
     */
    public function handle(Login|Logout|Failed $event)
    {
        $user = $event->user;
        $eventName = match (true) {
            $event instanceof Login => 'Login',
            $event instanceof Logout => 'Logout',
            default => 'Failed Login',
        };
        $description = ($event instanceof Failed ? 'An authentication attempt' : Filament::getUserName($user)).' '.match ($eventName) {
            'Login' => 'logged in',
            'Logout' => 'logged out',
            default => 'failed to log in',
        };

        $logger = app(ActivityLogger::class)
            ->useLog(config('filament-logger.access.log_name'))
            ->setLogStatus(app(ActivityLogStatus::class))
            ->withProperties(['ip' => request()->ip(), 'user_agent' => request()->userAgent()])
            ->event($eventName);

        if ($event instanceof Failed) {
            $logger->causedByAnonymous();
        } else {
            $logger->causedBy($user);
        }

        $logger->log($description);
    }
}
