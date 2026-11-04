<x-layouts.app :title="$document ? 'Edit document' : 'New document'">
    <h1 class="text-xl font-semibold">{{ $document ? 'Edit document' : 'New document' }}</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">{{ $document ? 'Saving changes the record only. To add a file, use the version form on the document page. Earlier files are not overwritten.' : 'At least one PDF, JPG, or PNG is required. Each file is stored under a random name.' }}</p>

    <form method="POST" action="{{ $document ? route('company.documents.update', $document) : route('company.documents.store') }}" enctype="multipart/form-data" class="mt-6 max-w-3xl space-y-4">
        @csrf
        @if ($document) @method('PUT') @endif
        @if ($errors->any())
            <div role="alert" class="rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
                <p class="font-medium">The document could not be saved.</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="title" class="block text-sm font-medium">Document title</label>
                <input id="title" name="title" required value="{{ old('title', $document?->title ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="document_category_id" class="block text-sm font-medium">Category</label>
                <select id="document_category_id" name="document_category_id" required class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm">
                    <option value="">Select a category</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) old('document_category_id', $document?->document_category_id ?? 0) === $category->id)>{{ $category->name }}{{ $category->is_active ? '' : ' (inactive)' }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="verification_status" class="block text-sm font-medium">Verification status</label>
                <select id="verification_status" name="verification_status" required class="mt-1 w-full rounded border border-line bg-white px-3 py-2 text-sm">
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(old('verification_status', $document?->verification_status ?? 'pending') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="document_number" class="block text-sm font-medium">Document number</label>
                <input id="document_number" name="document_number" value="{{ old('document_number', $document?->document_number ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="issuing_authority" class="block text-sm font-medium">Issuing authority</label>
                <input id="issuing_authority" name="issuing_authority" value="{{ old('issuing_authority', $document?->issuing_authority ?? '') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="issued_on" class="block text-sm font-medium">Issue date</label>
                <input id="issued_on" name="issued_on" type="date" value="{{ old('issued_on', $document?->issued_on?->toDateString()) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="expires_on" class="block text-sm font-medium">Expiry date</label>
                <input id="expires_on" name="expires_on" type="date" value="{{ old('expires_on', $document?->expires_on?->toDateString()) }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label for="remarks" class="block text-sm font-medium">Remarks</label>
                <textarea id="remarks" name="remarks" rows="3" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">{{ old('remarks', $document?->remarks ?? '') }}</textarea>
            </div>
            @unless ($document)
                <div class="sm:col-span-2">
                    <label for="files" class="block text-sm font-medium">Files</label>
                    <input id="files" name="files[]" type="file" multiple required accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" class="mt-1 block w-full text-sm">
                    <p class="mt-1 text-xs text-muted">Up to {{ (int) config('asl.documents.max_files') }} files, {{ (int) config('asl.documents.max_kilobytes') }} KB each. PDF, JPG, JPEG, or PNG.</p>
                </div>
                <div class="sm:col-span-2">
                    <label for="note" class="block text-sm font-medium">Version note</label>
                    <input id="note" name="note" value="{{ old('note') }}" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                </div>
            @endunless
        </div>
        <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">Save document</button>
    </form>
</x-layouts.app>
