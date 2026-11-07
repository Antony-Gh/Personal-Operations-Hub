<?php

namespace App\Http\Controllers;

use App\Models\PhoneUsageLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PhoneUsageController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'minutes' => 'required|integer|min:0|max:1440',
            'notes' => 'nullable|string|max:500',
        ]);

        $user = $request->user();

        PhoneUsageLog::updateOrCreate(
            [
                'user_id' => $user->id,
                'date' => $validated['date'],
            ],
            [
                'minutes' => $validated['minutes'],
                'notes' => $validated['notes'] ?? null,
            ]
        );

        return back()->with('success', 'Phone usage recorded.');
    }
}
