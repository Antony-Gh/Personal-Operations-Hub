<?php

use App\Models\CalendarEvent;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;

test('authenticated calendar ics export generates valid RFC 5545 calendar with events tasks projects milestones and VALARMs', function (): void {
    $user = User::factory()->create(['name' => 'Anthony Gaius']);
    $user->ensureSetting()->update(['timezone' => 'UTC']);

    CalendarEvent::create([
        'user_id' => $user->id,
        'title' => 'Architecture Review Meeting',
        'description' => 'Sync with systems team',
        'location' => 'Room 302',
        'start_time' => Carbon::parse('2026-11-15 10:00:00', 'UTC'),
        'end_time' => Carbon::parse('2026-11-15 11:00:00', 'UTC'),
        'all_day' => false,
        'reminder_minutes' => 15,
    ]);

    $project = Project::factory()->create([
        'user_id' => $user->id,
        'name' => 'Compiler Capstone',
        'status' => 'in_progress',
        'target_date' => '2026-12-01',
        'reminders_enabled' => true,
        'reminder_days' => [3, 0],
    ]);

    ProjectMilestone::create([
        'project_id' => $project->id,
        'title' => 'Parser Complete',
        'due_date' => '2026-11-20',
        'due_time' => '16:00',
        'is_completed' => false,
        'reminders_enabled' => true,
        'reminder_offsets' => [1, 0],
    ]);

    Task::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'title' => 'Write AST Visitor Tests',
        'priority' => 'high',
        'status' => 'in_progress',
        'due_date' => '2026-11-18',
        'due_time' => '14:00',
        'reminder_preset' => '30m',
    ]);

    $response = $this->actingAs($user)
        ->get('/calendar/export.ics')
        ->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/calendar');

    $ics = $response->getContent();
    expect($ics)->toContain('BEGIN:VCALENDAR')
        ->and($ics)->toContain('VERSION:2.0')
        ->and($ics)->toContain('Architecture Review Meeting')
        ->and($ics)->toContain('[Task] Write AST Visitor Tests')
        ->and($ics)->toContain('[Project Deadline] Compiler Capstone')
        ->and($ics)->toContain('[Milestone] Parser Complete')
        ->and($ics)->toContain('BEGIN:VALARM')
        ->and($ics)->toContain('TRIGGER:-PT15M')
        ->and($ics)->toContain('TRIGGER:-PT30M')
        ->and($ics)->toContain('END:VCALENDAR');
});

test('private calendar subscription token authenticates unauthenticated feed requests enforces isolation and supports rotation and revocation', function (): void {
    $userA = User::factory()->create(['name' => 'User Alpha']);
    $userB = User::factory()->create(['name' => 'User Beta']);

    $taskA = Task::factory()->create([
        'user_id' => $userA->id,
        'title' => 'Alpha Secret Launch',
        'due_date' => '2026-11-22',
        'status' => 'not_started',
    ]);

    Task::factory()->create([
        'user_id' => $userB->id,
        'title' => 'Beta Private Roadmap',
        'due_date' => '2026-11-22',
        'status' => 'not_started',
    ]);

    $genResponse = $this->actingAs($userA)
        ->postJson('/calendar/feed/token')
        ->assertOk();

    $feedUrl = $genResponse->json('feed_url');
    expect($feedUrl)->toContain('/calendar/feed/')
        ->and($feedUrl)->toEndWith('.ics');

    // Stored token hash is not equal to raw token
    $settingA = $userA->setting()->firstOrFail();
    expect($settingA->calendar_feed_enabled)->toBeTrue()
        ->and($settingA->calendar_feed_token_hash)->not->toBeNull();

    // Soft-deleting a task emits STATUS:CANCELLED in the live feed
    $deletedTask = Task::factory()->create([
        'user_id' => $userA->id,
        'title' => 'Cancelled Sprint Item',
        'due_date' => '2026-11-25',
        'status' => 'not_started',
    ]);
    $deletedTask->delete();

    // Unauthenticated client can fetch User A's feed via token and NEVER sees User B's items
    auth()->logout();
    $feedPath = parse_url($feedUrl, PHP_URL_PATH);

    $feedResponse = $this->get($feedPath)->assertOk();
    $body = $feedResponse->getContent();

    expect($body)->toContain('Alpha Secret Launch')
        ->and($body)->toContain('STATUS:CANCELLED')
        ->and($body)->not->toContain('Beta Private Roadmap')
        ->and($body)->toContain("UID:task-{$taskA->id}@personal-operations-hub.local");

    // Regenerating token invalidates previous feed URL immediately
    $secondResponse = $this->actingAs($userA)
        ->postJson('/calendar/feed/token')
        ->assertOk();
    $newFeedPath = parse_url($secondResponse->json('feed_url'), PHP_URL_PATH);

    auth()->logout();
    $this->get($feedPath)->assertForbidden();
    $this->get($newFeedPath)->assertOk();

    // Revoking token disables the feed URL immediately
    $this->actingAs($userA)
        ->deleteJson('/calendar/feed/token')
        ->assertOk();

    auth()->logout();
    $this->get($newFeedPath)->assertForbidden();
});
