<x-layouts.app title="Bank and MFS accounts">
    <h1 class="text-xl font-semibold">Bank and MFS accounts</h1>
    <p class="mt-1 max-w-3xl text-sm text-muted">Each account is an asset on the chart, with its own 1xxx code. Cash in Hand is already on the chart. This screen starts empty. Add a bank or an MFS account only when you have its details, including the date it was opened. An opening balance cannot be dated before that day. A deactivated account is not offered for a new journal, receipt, or payment.</p>
    <p class="mt-3 text-sm"><a class="font-medium text-brand hover:text-brand-dark" href="{{ route('accounts.index') }}">Back to the chart</a></p>
    @include('accounting.partials.errors')

    <h2 class="mt-8 text-base font-semibold">Bank accounts</h2>
    @if ($canManage)
        <form method="POST" action="{{ route('bank-accounts.store') }}" class="mt-3 grid max-w-4xl gap-3 rounded border border-line bg-white p-3 sm:grid-cols-2">
            @csrf
            <div>
                <label for="bank-code" class="block text-sm font-medium">Code</label>
                <input id="bank-code" name="code" value="{{ old('code') }}" required maxlength="4" inputmode="numeric" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="bank-name" class="block text-sm font-medium">Bank name</label>
                <input id="bank-name" name="bank_name" value="{{ old('bank_name') }}" required maxlength="160" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="branch" class="block text-sm font-medium">Branch</label>
                <input id="branch" name="branch" value="{{ old('branch') }}" required maxlength="160" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="bank-title" class="block text-sm font-medium">Account title</label>
                <input id="bank-title" name="account_title" value="{{ old('account_title') }}" required maxlength="160" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="account-number" class="block text-sm font-medium">Account number</label>
                <input id="account-number" name="account_number" value="{{ old('account_number') }}" required maxlength="40" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="account-type" class="block text-sm font-medium">Account type</label>
                <select id="account-type" name="account_type" required class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    @foreach ($bankTypes as $value => $label)
                        <option value="{{ $value }}" @selected(old('account_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="bank-opened" class="block text-sm font-medium">Opened on</label>
                <input id="bank-opened" name="opened_on" type="date" value="{{ old('opened_on') }}" required class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label for="bank-parent" class="block text-sm font-medium">Parent asset</label>
                <select id="bank-parent" name="parent_id" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    <option value="">None</option>
                    @foreach ($parents as $parent)
                        <option value="{{ $parent->id }}" @selected((string) old('parent_id') === (string) $parent->id)>{{ $parent->code }} {{ $parent->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">Add bank account</button>
            </div>
        </form>
    @endif
    <div class="mt-4 space-y-3">
        @forelse ($banks as $bank)
            <div id="bank-{{ $bank->id }}" class="rounded border border-line bg-white p-3">
                @if ($canManage && $bank->account)
                    <form method="POST" action="{{ route('bank-accounts.update', $bank) }}" class="grid gap-3 sm:grid-cols-2">
                        @csrf
                        @method('PUT')
                        <p class="text-sm font-medium sm:col-span-2">{{ $bank->account->code }} · {{ $bank->account->type->label() }}</p>
                        <div>
                            <label for="bank-name-{{ $bank->id }}" class="block text-sm font-medium">Bank name</label>
                            <input id="bank-name-{{ $bank->id }}" name="bank_name" value="{{ $bank->bank_name }}" required maxlength="160" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label for="branch-{{ $bank->id }}" class="block text-sm font-medium">Branch</label>
                            <input id="branch-{{ $bank->id }}" name="branch" value="{{ $bank->branch }}" required maxlength="160" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label for="title-{{ $bank->id }}" class="block text-sm font-medium">Account title</label>
                            <input id="title-{{ $bank->id }}" name="account_title" value="{{ $bank->account_title }}" required maxlength="160" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label for="number-{{ $bank->id }}" class="block text-sm font-medium">Account number</label>
                            <input id="number-{{ $bank->id }}" name="account_number" value="{{ $bank->account_number }}" required maxlength="40" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label for="type-{{ $bank->id }}" class="block text-sm font-medium">Account type</label>
                            <select id="type-{{ $bank->id }}" name="account_type" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                                @foreach ($bankTypes as $value => $label)
                                    <option value="{{ $value }}" @selected($bank->account_type->value === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="opened-{{ $bank->id }}" class="block text-sm font-medium">Opened on</label>
                            <input id="opened-{{ $bank->id }}" name="opened_on" type="date" value="{{ $bank->openedOn() }}" required class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label for="parent-{{ $bank->id }}" class="block text-sm font-medium">Parent asset</label>
                            <select id="parent-{{ $bank->id }}" name="parent_id" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                                <option value="">None</option>
                                @foreach ($parents as $parent)
                                    @if ($parent->id !== $bank->account->id)
                                        <option value="{{ $parent->id }}" @selected($bank->account->parent_id === $parent->id)>{{ $parent->code }} {{ $parent->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <label class="flex min-h-11 items-center gap-2 text-sm">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" @checked($bank->account->is_active)>
                            Active
                        </label>
                        <div class="flex items-end">
                            <button type="submit" class="rounded border border-line px-3 py-2 text-sm">Save</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('bank-accounts.destroy', $bank) }}" class="mt-3" onsubmit="return confirm('Delete this bank account? An account with a posting cannot be deleted.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-muted">Delete</button>
                    </form>
                @else
                    <p class="text-sm font-medium">{{ $bank->account?->code }} {{ $bank->account_title }}</p>
                    <p class="mt-1 text-xs text-muted">{{ $bank->bank_name }} · {{ $bank->branch }} · {{ $bank->account_number }} · {{ $bank->account_type->label() }} · opened {{ $bank->openedOn() }} · {{ $bank->account?->is_active ? 'Active' : 'Inactive' }}</p>
                @endif
            </div>
        @empty
            <p class="mt-3 text-sm text-muted">No bank accounts yet.</p>
        @endforelse
    </div>

    <h2 class="mt-8 text-base font-semibold">MFS accounts</h2>
    @if ($canManage)
        <form method="POST" action="{{ route('mfs-accounts.store') }}" class="mt-3 grid max-w-4xl gap-3 rounded border border-line bg-white p-3 sm:grid-cols-2">
            @csrf
            <div>
                <label for="mfs-code" class="block text-sm font-medium">Code</label>
                <input id="mfs-code" name="code" required maxlength="4" inputmode="numeric" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="provider" class="block text-sm font-medium">Provider</label>
                <select id="provider" name="provider" required class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    @foreach ($providers as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="mobile" class="block text-sm font-medium">Mobile number</label>
                <input id="mobile" name="mobile_number" required maxlength="11" inputmode="numeric" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="mfs-title" class="block text-sm font-medium">Account title</label>
                <input id="mfs-title" name="account_title" required maxlength="160" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div>
                <label for="mfs-opened" class="block text-sm font-medium">Opened on</label>
                <input id="mfs-opened" name="opened_on" type="date" required class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label for="mfs-parent" class="block text-sm font-medium">Parent asset</label>
                <select id="mfs-parent" name="parent_id" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                    <option value="">None</option>
                    @foreach ($parents as $parent)
                        <option value="{{ $parent->id }}">{{ $parent->code }} {{ $parent->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <button type="submit" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">Add MFS account</button>
            </div>
        </form>
    @endif
    <div class="mt-4 space-y-3">
        @forelse ($wallets as $wallet)
            <div id="mfs-{{ $wallet->id }}" class="rounded border border-line bg-white p-3">
                @if ($canManage && $wallet->account)
                    <form method="POST" action="{{ route('mfs-accounts.update', $wallet) }}" class="grid gap-3 sm:grid-cols-2">
                        @csrf
                        @method('PUT')
                        <p class="text-sm font-medium sm:col-span-2">{{ $wallet->account->code }} · {{ $wallet->account->type->label() }}</p>
                        <div>
                            <label for="provider-{{ $wallet->id }}" class="block text-sm font-medium">Provider</label>
                            <select id="provider-{{ $wallet->id }}" name="provider" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                                @foreach ($providers as $value => $label)
                                    <option value="{{ $value }}" @selected($wallet->provider->value === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="mobile-{{ $wallet->id }}" class="block text-sm font-medium">Mobile number</label>
                            <input id="mobile-{{ $wallet->id }}" name="mobile_number" value="{{ $wallet->mobile_number }}" required maxlength="11" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label for="mfs-title-{{ $wallet->id }}" class="block text-sm font-medium">Account title</label>
                            <input id="mfs-title-{{ $wallet->id }}" name="account_title" value="{{ $wallet->account_title }}" required maxlength="160" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label for="mfs-opened-{{ $wallet->id }}" class="block text-sm font-medium">Opened on</label>
                            <input id="mfs-opened-{{ $wallet->id }}" name="opened_on" type="date" value="{{ $wallet->openedOn() }}" required class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label for="mfs-parent-{{ $wallet->id }}" class="block text-sm font-medium">Parent asset</label>
                            <select id="mfs-parent-{{ $wallet->id }}" name="parent_id" class="mt-1 w-full rounded border border-line px-3 py-2 text-sm">
                                <option value="">None</option>
                                @foreach ($parents as $parent)
                                    @if ($parent->id !== $wallet->account->id)
                                        <option value="{{ $parent->id }}" @selected($wallet->account->parent_id === $parent->id)>{{ $parent->code }} {{ $parent->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <label class="flex min-h-11 items-center gap-2 text-sm">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" @checked($wallet->account->is_active)>
                            Active
                        </label>
                        <div class="flex items-end">
                            <button type="submit" class="rounded border border-line px-3 py-2 text-sm">Save</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('mfs-accounts.destroy', $wallet) }}" class="mt-3" onsubmit="return confirm('Delete this MFS account? An account with a posting cannot be deleted.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-muted">Delete</button>
                    </form>
                @else
                    <p class="text-sm font-medium">{{ $wallet->account?->code }} {{ $wallet->account_title }}</p>
                    <p class="mt-1 text-xs text-muted">{{ $wallet->provider->label() }} · {{ $wallet->mobile_number }} · opened {{ $wallet->openedOn() }} · {{ $wallet->account?->is_active ? 'Active' : 'Inactive' }}</p>
                @endif
            </div>
        @empty
            <p class="mt-3 text-sm text-muted">No MFS accounts yet.</p>
        @endforelse
    </div>
</x-layouts.app>
