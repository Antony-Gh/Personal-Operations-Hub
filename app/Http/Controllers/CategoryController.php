<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $categories = Category::where('user_id', $user->id)
            ->ordered()
            ->withCount(['tasks', 'projects'])
            ->get();

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:50',
            'icon' => 'nullable|string|max:50',
        ]);

        $user = $request->user();
        $maxOrder = Category::where('user_id', $user->id)->max('sort_order') ?? 0;

        Category::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'color' => $validated['color'] ?? '#6366f1',
            'icon' => $validated['icon'] ?? 'Folder',
            'sort_order' => $maxOrder + 1,
            'is_archived' => false,
        ]);

        return back()->with('success', 'Area created successfully.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:50',
            'icon' => 'nullable|string|max:50',
            'is_archived' => 'nullable|boolean',
        ]);

        $category->update($validated);

        return back()->with('success', 'Area updated successfully.');
    }

    public function toggleArchive(Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $category->update([
            'is_archived' => !$category->is_archived,
        ]);

        $status = $category->is_archived ? 'archived' : 'restored';
        return back()->with('success', "Area {$status} successfully.");
    }

    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:categories,id',
        ]);

        $user = $request->user();

        DB::transaction(function () use ($validated, $user) {
            foreach ($validated['order'] as $index => $id) {
                Category::where('id', $id)
                    ->where('user_id', $user->id)
                    ->update(['sort_order' => $index + 1]);
            }
        });

        return back()->with('success', 'Areas reordered successfully.');
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        $validated = $request->validate([
            'task_action' => 'required|string|in:reassign,delete_tasks,keep_unassigned',
            'reassign_to_category_id' => [
                'nullable',
                'required_if:task_action,reassign',
                Rule::exists('categories', 'id')->where('user_id', $request->user()->id),
            ],
        ]);

        DB::transaction(function () use ($category, $validated) {
            if ($validated['task_action'] === 'reassign') {
                $targetId = $validated['reassign_to_category_id'];
                Task::where('category_id', $category->id)->update(['category_id' => $targetId]);
            } elseif ($validated['task_action'] === 'delete_tasks') {
                Task::where('category_id', $category->id)->delete();
            } else {
                Task::where('category_id', $category->id)->update(['category_id' => null]);
            }

            $category->delete();
        });

        return back()->with('success', 'Area deleted successfully.');
    }
}

