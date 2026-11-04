<x-layouts.app :title="$document->title">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">{{ $document->title }}</h1>
            <p class="mt-1 text-sm text-muted">{{ $document->category?->name }} · {{ $document->statusLabel() }} · {{ $document->expiryLabel() }}</p>
        </div>
        @if ($currentUser?->hasPermission('company.documents.update'))
            <a href="{{ route('company.documents.edit', $document) }}" class="rounded border border-line bg-white px-3 py-2 text-sm">Edit details</a>
        @endif
    </div>

    <dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div>
            <dt class="text-sm text-muted">Document number</dt>
            <dd>{{ $document->document_number ?: '—' }}</dd>
        </div>
        <div>
            <dt class="text-sm text-muted">Issuing authority</dt>
            <dd>{{ $document->issuing_authority ?: '—' }}</dd>
        </div>
        <div>
            <dt class="text-sm text-muted">Issue date</dt>
            <dd>{{ $document->issued_on?->format('d M Y') ?: '—' }}</dd>
        </div>
        <div>
            <dt class="text-sm text-muted">Created by</dt>
            <dd>{{ $document->creator?->name ?: '—' }}</dd>
        </div>
        <div>
            <dt class="text-sm text-muted">Updated by</dt>
            <dd>{{ $document->editor?->name ?: '—' }}</dd>
        </div>
        <div>
            <dt class="text-sm text-muted">Updated</dt>
            <dd>{{ $document->updated_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }}</dd>
        </div>
    </dl>
    @if ($document->remarks)
        <p class="mt-4 max-w-3xl text-sm">{{ $document->remarks }}</p>
    @endif

    <section class="mt-8">
        <h2 class="text-base font-semibold">Version history</h2>
        <p class="mt-1 text-sm text-muted">Each upload is a new version. Nothing here replaces an older file.</p>
        <div class="mt-3 overflow-x-auto rounded border border-line bg-white">
            <table class="min-w-full text-sm">
                <thead class="border-b border-line text-left text-muted">
                    <tr>
                        <th class="px-3 py-2 font-medium">Version</th>
                        <th class="px-3 py-2 font-medium">File</th>
                        <th class="px-3 py-2 font-medium">Uploaded</th>
                        <th class="px-3 py-2 font-medium">Note</th>
                        <th class="px-3 py-2 font-medium"><span class="sr-only">Download</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($document->versions->sortByDesc('version_number') as $version)
                        <tr class="border-b border-line last:border-0">
                            <td class="px-3 py-2">{{ $version->version_number }}</td>
                            <td class="px-3 py-2">{{ $version->original_filename }}</td>
                            <td class="px-3 py-2">{{ $version->created_at?->timezone(config('app.timezone'))->format('d M Y, H:i') }} · {{ $version->uploader?->name }}</td>
                            <td class="px-3 py-2">{{ $version->note ?: '—' }}</td>
                            <td class="px-3 py-2 text-right">
                                @if ($currentUser?->hasPermission('company.documents.download'))
                                    <a href="{{ route('company.documents.download', [$document, $version]) }}" class="font-medium text-brand hover:text-brand-dark">Download</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-6 text-muted">No files have been stored.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($currentUser?->hasPermission('company.documents.update'))
        <form method="POST" action="{{ route('company.documents.versions.store', $document) }}" enctype="multipart/form-data" class="mt-8 max-w-3xl space-y-3">
            @csrf
            <h2 class="text-base font-semibold">Add files</h2>
            @if ($errors->any())
                <div role="alert" class="rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div>
                <label for="files" class="block text-sm font-medium">Files</label>
                <input id="files" name="files[]" type="file" multiple required accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="mt-1 block w-full text-sm">
            </div>
            <div>
                <label for="note" class="block text-sm font-medium">Version note</label>
                <input id="note" name="note" value="{{ old('note') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">Store new version</button>
        </form>
    @endif

    @if ($currentUser?->hasPermission('company.documents.delete'))
        <form method="POST" action="{{ route('company.documents.destroy', $document) }}" class="mt-8 max-w-3xl space-y-3 rounded border border-line bg-white px-4 py-3">
            @csrf
            <h2 class="text-sm font-medium">Retire this document</h2>
            <p class="text-sm text-muted">The record leaves the active list. File versions stay in private storage and are not replaced.</p>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="confirm_delete" value="1" required>
                I understand this document will be retired
            </label>
            <button type="submit" class="rounded border border-line px-3 py-2 text-sm">Retire document</button>
        </form>
    @endif
</x-layouts.app>
