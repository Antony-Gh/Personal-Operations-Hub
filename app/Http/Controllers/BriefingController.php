<?php

namespace App\Http\Controllers;

use App\Services\BriefingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BriefingController extends Controller
{
    public function index(Request $request, BriefingService $briefingService): Response
    {
        $user = $request->user();
        $tab = in_array($request->input('tab'), ['daily', 'weekly'], true)
            ? (string) $request->input('tab')
            : 'daily';

        return Inertia::render('Briefings/Index', [
            'initialTab' => $tab,
            'dailyBriefing' => $briefingService->buildDailyBriefing($user, $request->input('date')),
            'weeklyDigest' => $briefingService->buildWeeklyDigest($user, $request->input('date')),
        ]);
    }

    public function sendDaily(Request $request, BriefingService $briefingService): RedirectResponse
    {
        $sent = $briefingService->sendDailyBriefingNotification($request->user());

        return back()->with(
            'success',
            $sent ? 'Daily briefing sent to Notification Center.' : 'Today\'s daily briefing was already delivered.'
        );
    }

    public function sendWeekly(Request $request, BriefingService $briefingService): RedirectResponse
    {
        $sent = $briefingService->sendWeeklyDigestNotification($request->user());

        return back()->with(
            'success',
            $sent ? 'Weekly digest sent to Notification Center.' : 'This week\'s digest was already delivered.'
        );
    }
}
