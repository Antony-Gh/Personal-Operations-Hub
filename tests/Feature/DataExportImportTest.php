<?php

use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\UploadedFile;

it('exports data as json stream', function () {
    $user = User::factory()->create();
    Category::create(['user_id' => $user->id, 'name' => 'University']);

    $response = $this->actingAs($user)->get(route('settings.export.json'));

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'application/json');
});

it('exports tasks as csv spreadsheet', function () {
    $user = User::factory()->create();
    Task::create(['user_id' => $user->id, 'title' => 'Exportable Task', 'priority' => 'medium', 'status' => 'not_started']);

    $response = $this->actingAs($user)->get(route('settings.export.tasks-csv'));

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

it('previews and validates import backup json file', function () {
    $user = User::factory()->create();

    $payload = [
        'version' => '1.0',
        'exported_at' => now()->toIso8601String(),
        'categories' => [['id' => 1, 'name' => 'Imported Area']],
        'projects' => [],
        'tasks' => [['id' => 10, 'title' => 'Imported Task', 'priority' => 'high']],
    ];

    $file = UploadedFile::fake()->createWithContent('backup.json', json_encode($payload));

    $response = $this->actingAs($user)->post(route('settings.import.preview'), [
        'backup_file' => $file,
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'valid' => true,
        'summary' => [
            'version' => '1.0',
            'categories_count' => 1,
            'tasks_count' => 1,
        ],
    ]);
});

it('processes import and creates new records in merge mode', function () {
    $user = User::factory()->create();

    $payload = [
        'version' => '1.0',
        'categories' => [['id' => 99, 'name' => 'Imported Unique Area']],
        'tasks' => [['title' => 'Imported Special Task', 'priority' => 'high', 'category_id' => 99]],
    ];

    $response = $this->actingAs($user)->post(route('settings.import.process'), [
        'payload' => $payload,
        'mode' => 'merge',
    ]);

    $response->assertSessionHas('success');
    expect(Category::where('user_id', $user->id)->where('name', 'Imported Unique Area')->exists())->toBeTrue();
    expect(Task::where('user_id', $user->id)->where('title', 'Imported Special Task')->exists())->toBeTrue();
});

