@props(['name', 'value' => '', 'choices' => []])
@php
    $symbols = [];
    foreach ($choices as $choice) {
        if (is_array($choice) && isset($choice['symbol'])) {
            $symbols[] = (string) $choice['symbol'];
        }
    }
    $current = trim((string) $value);
    $known = $current !== '' && in_array($current, $symbols, true);
    $selected = $current === '' ? '' : ($known ? $current : '__other__');
    $custom = $known || $current === '' ? '' : $current;
@endphp
<div class="mt-1 space-y-2" x-data="{ choice: @js($selected), custom: @js($custom) }">
    <select x-model="choice" aria-label="Unit" class="w-full min-w-32 rounded border border-line bg-white px-2 py-2 text-sm">
        <option value="">Choose a unit</option>
        @foreach ($choices as $unit)
            @if (is_array($unit) && isset($unit['symbol'], $unit['name']))
                <option value="{{ $unit['symbol'] }}">{{ $unit['name'] }} ({{ $unit['symbol'] }})</option>
            @endif
        @endforeach
        <option value="__other__">Other…</option>
    </select>
    <input x-show="choice === '__other__'" x-cloak x-model="custom" maxlength="40" placeholder="Type the unit" aria-label="Custom unit" class="w-full rounded border border-line px-2 py-2 text-sm">
    <input type="hidden" name="{{ $name }}" value="{{ $current }}" :value="choice === '__other__' ? custom.trim() : choice">
</div>
