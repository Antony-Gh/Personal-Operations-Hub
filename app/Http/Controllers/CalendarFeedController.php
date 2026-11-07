<?php

namespace App\Http\Controllers;

use App\Services\IcsGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

class CalendarFeedController extends Controller
{
    public function export(Request $request, IcsGeneratorService $ics): HttpResponse
    {
        $payload = $ics->generateForUser($request->user(), includeCancelled: false);
        $filename = 'personal-operations-hub-'.now()->format('Y-m-d').'.ics';

        return response($payload, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function feed(string $token, IcsGeneratorService $ics): HttpResponse
    {
        $cleanToken = preg_replace('/\.ics$/i', '', trim($token)) ?? '';
        $user = $ics->resolveUserByToken($cleanToken);

        if ($user === null) {
            return response('Invalid or revoked calendar subscription token.', 403, [
                'Content-Type' => 'text/plain; charset=utf-8',
            ]);
        }

        $payload = $ics->generateForUser($user, includeCancelled: true);

        return response($payload, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="personal-operations-hub.ics"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function generateToken(Request $request, IcsGeneratorService $ics): JsonResponse|RedirectResponse
    {
        $rawToken = $ics->generateFeedToken($request->user());
        $feedUrl = route('calendar.feed', ['token' => $rawToken]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'feed_url' => $feedUrl,
            ]);
        }

        return back()->with('success', 'Private calendar subscription link generated.');
    }

    public function revokeToken(Request $request, IcsGeneratorService $ics): JsonResponse|RedirectResponse
    {
        $ics->revokeFeedToken($request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'feed_url' => null,
            ]);
        }

        return back()->with('success', 'Calendar subscription token revoked.');
    }
}
