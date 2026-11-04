<x-layouts.app title="Company">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Company profile</h1>
            <p class="mt-1 max-w-3xl text-sm text-muted">This record is the source for later quotations, invoices, challans, and tax documents. It is not visible to public visitors.</p>
        </div>
        @if ($currentUser?->hasPermission('company.update'))
            <a href="{{ route('company.edit') }}" class="rounded bg-brand px-3 py-2 text-sm font-medium text-white hover:bg-brand-dark">{{ $profile ? 'Edit profile' : 'Create profile' }}</a>
        @endif
    </div>

    @if (! $profile)
        <p class="mt-6 text-sm text-muted">No company profile has been saved.</p>
    @else
        <div class="mt-6 grid gap-6 lg:grid-cols-[12rem_1fr]">
            <div>
                @if ($profile->logo_path)
                    <img src="{{ route('company.logo') }}" alt="Company logo" class="h-28 w-28 rounded border border-line bg-white object-contain">
                @else
                    <div class="flex h-28 w-28 items-center justify-center rounded border border-line bg-white text-sm text-muted">No logo</div>
                @endif
            </div>
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm text-muted">Legal name</dt>
                    <dd>{{ $profile->legal_name }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted">Brand / trading name</dt>
                    <dd>{{ $profile->trading_name ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted">RJSC registration</dt>
                    <dd>{{ $profile->rjsc_registration_number ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted">Incorporation date</dt>
                    <dd>{{ $profile->incorporation_date?->format('d M Y') ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted">Business type</dt>
                    <dd>{{ $profile->business_type ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted">Currency</dt>
                    <dd>{{ $profile->currency_code }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted">TIN</dt>
                    <dd>{{ $profile->tin ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted">BIN</dt>
                    <dd>{{ $profile->bin ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted">Trade licence</dt>
                    <dd>
                        {{ $profile->trade_licence_number ?: '—' }}
                        @if ($profile->trade_licence_issuing_authority)
                            <span class="block text-sm text-muted">{{ $profile->trade_licence_issuing_authority }}</span>
                        @endif
                        @if ($profile->tradeLicenceState() === 'expired')
                            <span class="block text-sm text-red-800">Expired {{ $profile->trade_licence_expires_on?->format('d M Y') }}</span>
                        @elseif ($profile->tradeLicenceState() === 'expiring')
                            <span class="block text-sm">Expires {{ $profile->trade_licence_expires_on?->format('d M Y') }}</span>
                        @elseif ($profile->trade_licence_expires_on)
                            <span class="block text-sm text-muted">Valid until {{ $profile->trade_licence_expires_on->format('d M Y') }}</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-muted">Financial year</dt>
                    <dd>{{ $profile->financial_year_starts_on?->format('d M Y') }} – {{ $profile->financial_year_ends_on?->format('d M Y') }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted">Website</dt>
                    <dd>{{ $profile->website ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted">Official email</dt>
                    <dd>{{ $profile->email ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted">Official phone</dt>
                    <dd>{{ $profile->phone ?: '—' }}</dd>
                </div>
            </dl>
        </div>
        @if ($profile->description)
            <p class="mt-6 max-w-3xl text-sm">{{ $profile->description }}</p>
        @endif

        <section class="mt-8">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h2 class="text-base font-semibold">Addresses</h2>
                @if ($currentUser?->hasPermission('company.address.manage'))
                    <a href="{{ route('company.addresses.index') }}" class="text-sm font-medium text-brand hover:text-brand-dark">Manage addresses</a>
                @endif
            </div>
            <p class="mt-1 text-sm text-muted">The registered office and the corporate office are separate records.</p>
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                @forelse ($profile->addresses as $address)
                    <article class="rounded border border-line bg-white px-4 py-3 text-sm">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="font-medium">{{ $address->typeLabel() }}</h3>
                            <span>{{ $address->is_active ? 'Active' : 'Closed' }}</span>
                        </div>
                        <p class="mt-2">{{ implode(', ', $address->lines()) }}</p>
                        <p class="mt-2 text-muted">
                            @if ($address->is_default_correspondence) Correspondence default. @endif
                            @if ($address->is_default_billing) Billing default. @endif
                            @if ($address->effective_from) From {{ $address->effective_from->format('d M Y') }}. @endif
                            @if ($address->effective_until) Until {{ $address->effective_until->format('d M Y') }}. @endif
                        </p>
                    </article>
                @empty
                    <p class="text-sm text-muted">No addresses have been saved.</p>
                @endforelse
            </div>
        </section>
    @endif
</x-layouts.app>
