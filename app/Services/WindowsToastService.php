<?php

namespace App\Services;

use App\Models\User;
use App\Support\UserDateTime;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

class WindowsToastService
{
    private static bool $faking = false;

    /**
     * @var array<int, array{user_id: int, title: string, message: string, category: string}>
     */
    private static array $dispatched = [];

    public static function fake(): void
    {
        self::$faking = true;
        self::$dispatched = [];
    }

    public static function resetFake(): void
    {
        self::$faking = false;
        self::$dispatched = [];
    }

    /**
     * @return array<int, array{user_id: int, title: string, message: string, category: string}>
     */
    public static function dispatched(): array
    {
        return self::$dispatched;
    }

    /**
     * Dispatch a browserless Windows 10/11 Action Center Toast notification if enabled by the user.
     */
    public function notifyIfEnabled(
        User $user,
        string $title,
        string $message,
        string $category = 'system_notifications',
        bool $isCritical = false,
        bool $force = false,
    ): bool {
        $setting = $user->setting ?? $user->ensureSetting();

        if (! $force) {
            if (! $setting->notifications_enabled || ! $setting->windows_toast_enabled) {
                return false;
            }

            if (UserDateTime::isInQuietHours($user) && ! ($isCritical && $setting->allow_critical_in_quiet_hours)) {
                return false;
            }
        }

        if (self::$faking) {
            self::$dispatched[] = [
                'user_id' => $user->id,
                'title' => $title,
                'message' => $message,
                'category' => $category,
            ];

            return true;
        }

        if (app()->runningUnitTests() || PHP_OS_FAMILY !== 'Windows') {
            return false;
        }

        $scriptPath = base_path('scripts/send-windows-toast.ps1');
        if (! is_file($scriptPath)) {
            return false;
        }

        try {
            Process::timeout(8)->run([
                'powershell',
                '-NoProfile',
                '-NonInteractive',
                '-ExecutionPolicy',
                'Bypass',
                '-File',
                $scriptPath,
                '-Title',
                mb_substr($title, 0, 120),
                '-Message',
                mb_substr($message, 0, 240),
                '-Category',
                mb_substr($category, 0, 50),
            ]);

            return true;
        } catch (Throwable $e) {
            Log::warning('Windows Toast dispatch failed.', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
