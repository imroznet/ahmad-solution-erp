<x-layouts.app title="New tax rule">
    <h1 class="text-xl font-semibold">New tax rule</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">The first version is a draft. Any percentage you store here is informational. It is not copied onto Mushak 6.3 and it is not a default.</p>
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded border border-red-200 bg-white px-3 py-2 text-sm text-red-800"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="POST" action="{{ route('tax-rules.store') }}" class="mt-4 grid max-w-3xl gap-3">
        @csrf
        @include('tax.rules.fields')
        <button class="w-fit rounded bg-brand px-3 py-2 text-sm font-medium text-white">Save draft</button>
    </form>
</x-layouts.app>
