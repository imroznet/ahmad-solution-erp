<x-layouts.app title="Units">
    <h1 class="text-xl font-semibold">Units</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Measurement units for lines that are typed by hand. Deactivating a unit hides it from new choices. A document that already stores the text keeps that text. This list has no items, prices, or stock.</p>

    @if ($canManage)
        <form method="POST" action="{{ route('units.store') }}" class="mt-6 grid max-w-3xl gap-3 rounded border border-line bg-white p-3 sm:grid-cols-2 lg:grid-cols-4">
            @csrf
            <div class="sm:col-span-2 lg:col-span-1">
                <label for="name" class="block text-sm font-medium">Name</label>
                <input id="name" name="name" value="{{ old('name') }}" required maxlength="80" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="symbol" class="block text-sm font-medium">Symbol</label>
                <input id="symbol" name="symbol" value="{{ old('symbol') }}" required maxlength="40" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="sort_order" class="block text-sm font-medium">Sort</label>
                <input id="sort_order" name="sort_order" type="number" min="0" max="65535" value="{{ old('sort_order', '0') }}" required class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <label class="flex min-h-11 items-center gap-2 text-sm lg:col-span-2">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked((string) old('is_active', '1') === '1')>
                Active
            </label>
            <div class="flex items-end">
                <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">Add unit</button>
            </div>
            @if ($errors->any())
                <div class="sm:col-span-2 lg:col-span-4 text-sm text-red-800">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif
        </form>
    @endif

    <div class="mt-6 space-y-3">
        @forelse ($units as $unit)
            <div class="rounded border border-line bg-white p-3">
                @if ($canManage)
                    <form method="POST" action="{{ route('units.update', $unit) }}" class="grid gap-3 lg:grid-cols-[1fr_8rem_6rem_auto_auto] lg:items-end">
                        @csrf
                        @method('PUT')
                        <div>
                            <label for="name-{{ $unit->id }}" class="block text-sm font-medium">Name</label>
                            <input id="name-{{ $unit->id }}" name="name" value="{{ $unit->name }}" required maxlength="80" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label for="symbol-{{ $unit->id }}" class="block text-sm font-medium">Symbol</label>
                            <input id="symbol-{{ $unit->id }}" name="symbol" value="{{ $unit->symbol }}" required maxlength="40" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label for="sort-{{ $unit->id }}" class="block text-sm font-medium">Sort</label>
                            <input id="sort-{{ $unit->id }}" name="sort_order" type="number" min="0" max="65535" value="{{ $unit->sort_order }}" required class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                        </div>
                        <label class="flex min-h-11 items-center gap-2 text-sm">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" @checked($unit->is_active)>
                            Active
                        </label>
                        <button type="submit" class="rounded border border-line px-3 py-2 text-sm">Save</button>
                    </form>
                    <form method="POST" action="{{ route('units.destroy', $unit) }}" class="mt-3" onsubmit="return confirm('Delete this unit? Documents that already use this text keep it.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-muted">Delete</button>
                    </form>
                @else
                    <p class="text-sm font-medium">{{ $unit->name }} <span class="font-normal text-muted">({{ $unit->symbol }})</span></p>
                    <p class="mt-1 text-xs text-muted">{{ $unit->is_active ? 'Active' : 'Inactive' }} · Sort {{ $unit->sort_order }}</p>
                @endif
            </div>
        @empty
            <p class="text-sm text-muted">No units yet.</p>
        @endforelse
    </div>
</x-layouts.app>
