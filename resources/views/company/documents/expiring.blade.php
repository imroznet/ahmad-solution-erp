<x-layouts.app title="Expiring documents">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Expiring documents</h1>
            <p class="mt-1 max-w-3xl text-sm text-muted">Reminders are read from the database in Asia/Dhaka. No background worker is required. A scheduled email can be added later through cPanel cron.</p>
        </div>
        <a href="{{ route('company.documents.index') }}" class="text-sm font-medium text-brand hover:text-brand-dark">All documents</a>
    </div>

    <dl class="mt-6 grid gap-4 sm:grid-cols-5">
        @foreach (['expired' => 'Expired', '7' => 'Within 7 days', '15' => 'Within 15 days', '30' => 'Within 30 days', '60' => 'Within 60 days'] as $key => $label)
            <div class="rounded border border-line bg-white px-4 py-3">
                <dt class="text-sm text-muted">{{ $label }}</dt>
                <dd class="mt-1 text-2xl font-semibold">
                    <a href="{{ route('company.documents.expiring', ['window' => $key]) }}">{{ $counts[$key === 'expired' ? 'expired' : 'within_'.$key] }}</a>
                </dd>
            </div>
        @endforeach
    </dl>

    <h2 class="mt-8 text-base font-semibold">
        {{ $window === 'expired' ? 'Expired' : 'Expiring within '.$window.' days' }}
    </h2>
    <div class="mt-3 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="border-b border-line text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium">Title</th>
                    <th class="px-3 py-2 font-medium">Category</th>
                    <th class="px-3 py-2 font-medium">Expiry</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($documents as $document)
                    <tr class="border-b border-line last:border-0">
                        <td class="px-3 py-2"><a href="{{ route('company.documents.show', $document) }}" class="font-medium text-brand hover:text-brand-dark">{{ $document->title }}</a></td>
                        <td class="px-3 py-2">{{ $document->category?->name }}</td>
                        <td class="px-3 py-2">{{ $document->expiryLabel() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-3 py-6 text-muted">Nothing is in this window.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $documents->links() }}</div>
</x-layouts.app>
