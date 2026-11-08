<?php

namespace App\Http\Controllers;

use App\Services\PersonalAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function index(Request $request, PersonalAnalyticsService $analyticsService): Response
    {
        $rangeDays = (int) $request->input('range', 30);
        $analytics = $analyticsService->buildAnalytics($request->user(), $rangeDays);

        return Inertia::render('Analytics/Index', [
            'analytics' => $analytics,
        ]);
    }
}
