<?php

use App\Models\Category;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;

it('creates project with initial milestones', function () {
    $user = User::factory()->create();
    $cat = Category::create(['user_id' => $user->id, 'name' => 'Project - ROV']);

    $response = $this->actingAs($user)->post(route('projects.store'), [
        'name' => 'ROV Submersible Build',
        'category_id' => $cat->id,
        'status' => 'in_progress',
        'description' => 'Chassis and thrusters telemetry',
        'milestones' => [
            ['title' => 'Chassis assembly', 'due_date' => '2026-11-20'],
            ['title' => 'Waterproof tank test', 'due_date' => '2026-12-05'],
        ],
    ]);

    $response->assertSessionHas('success');
    $project = Project::where('user_id', $user->id)->where('name', 'ROV Submersible Build')->first();
    expect($project)->not->toBeNull();
    expect($project->milestones)->toHaveCount(2);
});

it('toggles project milestone completion', function () {
    $user = User::factory()->create();
    $project = Project::create(['user_id' => $user->id, 'name' => 'Minesweeper Robot', 'status' => 'in_progress']);
    $m = ProjectMilestone::create(['project_id' => $project->id, 'title' => 'Sensor integration', 'is_completed' => false]);

    $this->actingAs($user)->post(route('milestones.toggle', $m->id));
    expect($m->fresh()->is_completed)->toBeTrue();
    expect($m->fresh()->completed_at)->not->toBeNull();
});
