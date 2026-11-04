<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentCategoryRequest;
use App\Http\Requests\UpdateDocumentCategoryRequest;
use App\Models\DocumentCategory;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DocumentCategoryController extends Controller
{
    public function index(): View
    {
        return view('company.categories.index', [
            'categories' => DocumentCategory::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreDocumentCategoryRequest $request, AuditLogger $audit): RedirectResponse
    {
        $actor = $this->actor($request->user());
        $name = $request->validated('name');
        $base = Str::slug($name);
        $slug = $base !== '' ? $base : 'category';
        $suffix = 2;

        while (DocumentCategory::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        $sort = (int) DocumentCategory::query()->max('sort_order') + 1;
        $category = DocumentCategory::query()->create([
            'name' => $name,
            'slug' => $slug,
            'is_system' => false,
            'is_active' => true,
            'sort_order' => $sort,
        ]);

        $audit->record('document_category_created', $category, newValues: [
            'name' => $category->name,
            'slug' => $category->slug,
        ], actor: $actor);

        return redirect()->route('company.categories.index')->with('status', 'Category created.');
    }

    public function update(UpdateDocumentCategoryRequest $request, DocumentCategory $category, AuditLogger $audit): RedirectResponse
    {
        $actor = $this->actor($request->user());
        $before = [
            'name' => $category->name,
            'is_active' => $category->is_active,
        ];

        $category->fill([
            'name' => $request->validated('name'),
            'is_active' => $request->boolean('is_active'),
        ]);
        $category->save();

        $audit->record('document_category_updated', $category, oldValues: $before, newValues: [
            'name' => $category->name,
            'is_active' => $category->is_active,
        ], actor: $actor);

        return redirect()->route('company.categories.index')->with('status', 'Category updated.');
    }

    private function actor(mixed $user): User
    {
        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }
}
