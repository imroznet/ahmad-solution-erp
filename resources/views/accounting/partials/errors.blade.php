@if ($errors->any())
    <div class="mb-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800">
        @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif
