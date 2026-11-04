<x-layouts.app title="Document categories">
    <h1 class="text-xl font-semibold">Document categories</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">System categories can be renamed or deactivated. They are not deleted, so existing documents keep their category. Custom categories can be added for records that do not fit the list.</p>

    <form method="POST" action="{{ route('company.categories.store') }}" class="mt-6 flex max-w-xl flex-wrap items-end gap-2">
        @csrf
        <div class="min-w-0 flex-1">
            <label for="name" class="block text-sm font-medium">New category</label>
            <input id="name" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            @error('name')<p class="mt-1 text-sm text-red-800">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">Add category</button>
    </form>

    <div class="mt-6 space-y-3">
        @foreach ($categories as $category)
            <form method="POST" action="{{ route('company.categories.update', $category) }}" class="grid gap-3 rounded border border-line bg-white px-3 py-3 sm:grid-cols-[1fr_auto_auto] sm:items-center">
                @csrf
                @method('PUT')
                <div>
                    <label for="name-{{ $category->id }}" class="sr-only">Category name</label>
                    <input id="name-{{ $category->id }}" name="name" value="{{ $category->name }}" required class="w-full rounded border border-line px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-muted">{{ $category->is_system ? 'System category' : 'Custom category' }} · {{ $category->slug }}</p>
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_active" value="1" @checked($category->is_active)>
                    Active
                </label>
                <button type="submit" class="rounded border border-line px-3 py-2 text-sm">Save</button>
            </form>
        @endforeach
    </div>
</x-layouts.app>
