<x-layouts.app title="Financial years">
    <h1 class="text-xl font-semibold">Financial years</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Each year has its own start and end. Government rules can change them, so the software does not assume a July–June year or any other fixed period. A journal date has to fall inside one of these years, or inside the year saved on the company profile. Changing a year keeps every journal already dated in it inside the new dates.</p>
    @include('accounting.partials.errors')

    @if ($profileYear)
        <p class="mt-4 text-sm">Company profile year: {{ $profileYear['starts'] }} to {{ $profileYear['ends'] }}. Edit that pair on the company profile. Add other years here.</p>
    @else
        <p class="mt-4 text-sm">The company profile does not have a financial year yet.</p>
    @endif

    @if ($canManage)
        <form method="POST" action="{{ route('financial-years.store') }}" class="mt-6 grid max-w-3xl gap-3 rounded border border-line bg-white p-3 sm:grid-cols-3">
            @csrf
            <div>
                <label for="starts-on" class="block text-sm font-medium">Starts</label>
                <input id="starts-on" name="starts_on" type="date" required value="{{ old('starts_on') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="ends-on" class="block text-sm font-medium">Ends</label>
                <input id="ends-on" name="ends_on" type="date" required value="{{ old('ends_on') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div class="flex items-end">
                <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">Add year</button>
            </div>
        </form>
    @endif

    <div class="mt-6 space-y-3">
        @forelse ($years as $year)
            <div class="rounded border border-line bg-white p-3">
                @if ($canManage)
                    <form method="POST" action="{{ route('financial-years.update', $year) }}" class="grid gap-3 sm:grid-cols-3">
                        @csrf
                        @method('PUT')
                        <div>
                            <label for="starts-{{ $year->id }}" class="block text-sm font-medium">Starts</label>
                            <input id="starts-{{ $year->id }}" name="starts_on" type="date" required value="{{ $year->startsOn() }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label for="ends-{{ $year->id }}" class="block text-sm font-medium">Ends</label>
                            <input id="ends-{{ $year->id }}" name="ends_on" type="date" required value="{{ $year->endsOn() }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="rounded border border-line px-3 py-2 text-sm">Save</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('financial-years.destroy', $year) }}" class="mt-3" onsubmit="return confirm('Delete this financial year?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-muted">Delete</button>
                    </form>
                @else
                    <p class="text-sm">{{ $year->startsOn() }} to {{ $year->endsOn() }}</p>
                @endif
            </div>
        @empty
            <p class="text-sm text-muted">No extra financial years yet.</p>
        @endforelse
    </div>
</x-layouts.app>
