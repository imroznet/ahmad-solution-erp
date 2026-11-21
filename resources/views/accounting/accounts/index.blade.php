<x-layouts.app title="Chart of accounts">
    <h1 class="text-xl font-semibold">Chart of accounts</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Asset codes start with 1, liabilities with 2, equity with 3, income with 4, and expenses with 5. System accounts stay as the module created them. Cash in Hand is the only cash account set up here. Bank and MFS accounts are added on their own screen, and no bank details are stored until then. An account with a posting can be deactivated, not deleted.</p>
    <p class="mt-3 text-sm"><a class="font-medium text-brand hover:text-brand-dark" href="{{ route('cash-accounts.index') }}">Bank and MFS accounts</a></p>
    @include('accounting.partials.errors')

    @if ($canManage)
        <form method="POST" action="{{ route('accounts.store') }}" class="mt-6 grid max-w-4xl gap-3 rounded border border-line bg-white p-3 sm:grid-cols-2 lg:grid-cols-4">
            @csrf
            <div>
                <label for="code" class="block text-sm font-medium">Code</label>
                <input id="code" name="code" value="{{ old('code') }}" required maxlength="4" inputmode="numeric" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div class="sm:col-span-2 lg:col-span-1">
                <label for="name" class="block text-sm font-medium">Name</label>
                <input id="name" name="name" value="{{ old('name') }}" required maxlength="160" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="type" class="block text-sm font-medium">Type</label>
                <select id="type" name="type" required class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label for="parent_id" class="block text-sm font-medium">Parent</label>
                <select id="parent_id" name="parent_id" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    <option value="">None</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected((string) old('parent_id') === (string) $account->id)>{{ $account->code }} {{ $account->name }} ({{ $account->type->label() }})</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">Add account</button>
            </div>
        </form>
    @endif

    <div class="mt-6 space-y-3">
        @forelse ($accounts as $account)
            <div class="rounded border border-line bg-white p-3" style="margin-left: {{ ($depths[$account->id] ?? 0) * 1.25 }}rem">
                @if ($account->bankAccount || $account->mfsAccount)
                    <p class="text-sm font-medium">{{ $account->code }} {{ $account->name }}</p>
                    <p class="mt-1 text-xs text-muted">
                        {{ $account->type->label() }} · {{ $account->is_active ? 'Active' : 'Inactive' }}
                        @if ($account->bankAccount)
                            · Bank · {{ $account->bankAccount->bank_name }}
                        @else
                            · MFS · {{ $account->mfsAccount->provider->label() }}
                        @endif
                    </p>
                    <a class="mt-2 inline-block text-sm text-brand hover:text-brand-dark" href="{{ route('cash-accounts.index') }}">Open Bank and MFS</a>
                @elseif ($canManage && ! $account->isSystem())
                    <form method="POST" action="{{ route('accounts.update', $account) }}" class="grid gap-3 lg:grid-cols-[7rem_1fr_1fr_auto_auto] lg:items-end">
                        @csrf
                        @method('PUT')
                        <p class="text-sm font-medium">{{ $account->code }}<span class="mt-1 block text-xs font-normal text-muted">{{ $account->type->label() }}</span></p>
                        <div>
                            <label for="name-{{ $account->id }}" class="block text-sm font-medium">Name</label>
                            <input id="name-{{ $account->id }}" name="name" value="{{ $account->name }}" required maxlength="160" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label for="parent-{{ $account->id }}" class="block text-sm font-medium">Parent</label>
                            <select id="parent-{{ $account->id }}" name="parent_id" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                                <option value="">None</option>
                                @foreach ($accounts as $parent)
                                    @if ($parent->id !== $account->id)
                                        <option value="{{ $parent->id }}" @selected($account->parent_id === $parent->id)>{{ $parent->code }} {{ $parent->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <label class="flex min-h-11 items-center gap-2 text-sm">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" @checked($account->is_active)>
                            Active
                        </label>
                        <button type="submit" class="rounded border border-line px-3 py-2 text-sm">Save</button>
                    </form>
                    <form method="POST" action="{{ route('accounts.destroy', $account) }}" class="mt-3" onsubmit="return confirm('Delete this account? An account with a posting cannot be deleted.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-muted">Delete</button>
                    </form>
                @else
                    <p class="text-sm font-medium">{{ $account->code }} {{ $account->name }}</p>
                    <p class="mt-1 text-xs text-muted">{{ $account->type->label() }} · {{ $account->is_active ? 'Active' : 'Inactive' }}@if ($account->isSystem()) · System account @endif</p>
                @endif
            </div>
        @empty
            <p class="text-sm text-muted">No accounts yet.</p>
        @endforelse
    </div>
</x-layouts.app>
