<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Habit;
use App\Models\UserSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $setting = UserSetting::firstOrCreate(
            ['user_id' => $user->id],
            [
                'display_name' => $user->name,
                'theme' => 'light',
                'language' => 'en',
                'week_start' => 1,
                'date_format' => 'YYYY-MM-DD',
                'time_format' => '24h',
                'default_priority' => 'medium',
                'default_task_duration' => 30,
                'phone_usage_target_minutes' => 60,
                'weekly_review_prompts' => UserSetting::defaultPrompts(),
            ]
        );

        $categories = Category::where('user_id', $user->id)->ordered()->get();
        $habits = Habit::where('user_id', $user->id)->orderBy('sort_order')->get();

        return Inertia::render('Settings/Index', [
            'setting' => $setting,
            'categories' => $categories,
            'habits' => $habits,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'display_name' => 'nullable|string|max:100',
            'theme' => 'required|in:light,dark,system',
            'language' => 'required|string|max:10',
            'week_start' => 'required|integer|in:0,1',
            'date_format' => 'required|string|max:20',
            'time_format' => 'required|in:12h,24h',
            'default_priority' => 'required|in:high,medium,low',
            'default_task_duration' => 'required|integer|min:5|max:480',
            'phone_usage_target_minutes' => 'required|integer|min:0|max:1440',
            'weekly_review_prompts' => 'required|array',
            'weekly_review_prompts.*' => 'required|string|max:500',
        ]);

        $setting = UserSetting::firstOrCreate(['user_id' => $user->id]);
        $setting->update($validated);

        if (!empty($validated['display_name']) && $validated['display_name'] !== $user->name) {
            $user->update(['name' => $validated['display_name']]);
        }

        return back()->with('success', 'Settings updated successfully.');
    }
}

