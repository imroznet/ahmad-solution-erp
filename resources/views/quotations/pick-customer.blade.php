<x-layouts.app title="New quotation">
    <h1 class="text-xl font-semibold">New quotation</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Choose an active customer. Lines are typed by hand. There is no product list, and tax is not calculated.</p>

    <form method="GET" action="{{ route('quotations.create') }}" class="mt-6 max-w-xl space-y-4">
        <div>
            <label for="q" class="block text-sm font-medium">Find a customer</label>
            <input id="q" name="q" value="{{ $search }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
        </div>
        <button type="submit" class="rounded border border-line bg-white px-3 py-2 text-sm">Search</button>
    </form>

    <ul class="mt-6 max-w-xl divide-y divide-line rounded border border-line bg-white">
        @forelse ($customers as $customer)
            <li class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                <span>{{ $customer->legal_name }}@if ($customer->trading_name) <span class="text-muted">· {{ $customer->trading_name }}</span>@endif</span>
                <a href="{{ route('quotations.create', ['party' => $customer->id, 'q' => $search]) }}" class="font-medium text-brand">Continue</a>
            </li>
        @empty
            <li class="px-3 py-4 text-sm text-muted">No active customer matches this search.</li>
        @endforelse
    </ul>
</x-layouts.app>
