<x-layouts.app title="Documents">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Legal documents</h1>
            <p class="mt-1 max-w-3xl text-sm text-muted">Files stay on the private disk. A download requires permission and is written to the audit log.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('company.documents.expiring') }}" class="rounded border border-line bg-white px-3 py-2 text-sm">Expiring</a>
            @if ($currentUser?->hasPermission('company.document_categories.manage'))
                <a href="{{ route('company.categories.index') }}" class="rounded border border-line bg-white px-3 py-2 text-sm">Categories</a>
            @endif
            @if ($currentUser?->hasPermission('company.documents.create'))
                <a href="{{ route('company.documents.create') }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">New document</a>
            @endif
        </div>
    </div>

    <form method="GET" action="{{ route('company.documents.index') }}" class="mt-4 grid gap-2 sm:grid-cols-5">
        <div class="sm:col-span-2">
            <label for="search" class="sr-only">Search documents</label>
            <input id="search" name="search" value="{{ $search }}" placeholder="Title or document number" class="w-full rounded border border-line bg-white px-3 py-2 text-sm">
        </div>
        <div>
            <label for="category" class="sr-only">Category</label>
            <select id="category" name="category" class="w-full rounded border border-line bg-white px-3 py-2 text-sm">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((int) $categoryId === (int) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status" class="sr-only">Verification status</label>
            <select id="status" name="status" class="w-full rounded border border-line bg-white px-3 py-2 text-sm">
                <option value="">Any verification</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="window" class="sr-only">Expiry</label>
            <select id="window" name="window" class="w-full rounded border border-line bg-white px-3 py-2 text-sm">
                <option value="">Any expiry</option>
                <option value="expired" @selected($window === 'expired')>Expired</option>
                <option value="7" @selected($window === '7')>Within 7 days</option>
                <option value="15" @selected($window === '15')>Within 15 days</option>
                <option value="30" @selected($window === '30')>Within 30 days</option>
                <option value="60" @selected($window === '60')>Within 60 days</option>
            </select>
        </div>
        <button type="submit" class="rounded border border-line bg-white px-3 py-2 text-sm sm:col-span-5 sm:w-fit">Apply filters</button>
    </form>

    <div class="mt-4 overflow-x-auto rounded border border-line bg-white">
        <table class="min-w-full text-sm">
            <thead class="border-b border-line text-left text-muted">
                <tr>
                    <th class="px-3 py-2 font-medium">Title</th>
                    <th class="px-3 py-2 font-medium">Category</th>
                    <th class="px-3 py-2 font-medium">Number</th>
                    <th class="px-3 py-2 font-medium">Verification</th>
                    <th class="px-3 py-2 font-medium">Expiry</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($documents as $document)
                    <tr class="border-b border-line last:border-0">
                        <td class="px-3 py-2"><a href="{{ route('company.documents.show', $document) }}" class="font-medium text-brand hover:text-brand-dark">{{ $document->title }}</a></td>
                        <td class="px-3 py-2">{{ $document->category?->name }}</td>
                        <td class="px-3 py-2">{{ $document->document_number ?: '—' }}</td>
                        <td class="px-3 py-2">{{ $document->statusLabel() }}</td>
                        <td class="px-3 py-2">{{ $document->expiryLabel() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-6 text-muted">No documents match these filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $documents->links() }}</div>
</x-layouts.app>
