@php
    $address = implode(', ', array_values(array_filter(
        is_array($company['address'] ?? null) ? $company['address'] : [],
        fn (mixed $line): bool => is_string($line) && $line !== '',
    )));
    $contact = [];
    foreach (['phone', 'email', 'website'] as $key) {
        $value = $company[$key] ?? null;
        if (is_string($value) && $value !== '') {
            $contact[] = $value;
        }
    }
    $identity = [];
    if (is_string($company['tin'] ?? null) && $company['tin'] !== '') {
        $identity[] = 'TIN '.$company['tin'];
    }
    if (is_string($company['bin'] ?? null) && $company['bin'] !== '') {
        $identity[] = 'BIN '.$company['bin'];
    }
    $details = implode(' · ', array_filter([
        implode(' · ', $contact),
        implode(' · ', $identity),
    ]));
    $trading = is_string($company['trading_name'] ?? null) && $company['trading_name'] !== ''
        ? ' ('.$company['trading_name'].')'
        : '';
@endphp
@if (! empty($logo))
    <img src="{{ $logo }}" alt="" style="width: 28px; height: auto;">
@endif
<p class="letterhead-name">{{ $company['legal_name'] }}{{ $trading }}</p>
@if ($address !== '')
    <p class="letterhead-meta">{{ $address }}</p>
@endif
@if ($details !== '')
    <p class="letterhead-meta">{{ $details }}</p>
@endif
