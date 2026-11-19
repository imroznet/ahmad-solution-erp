@props(['href', 'active' => false, 'icon', 'label'])
<a
    href="{{ $href }}"
    title="{{ $label }}"
    @click="sidebar = false"
    @class([
        'flex min-h-11 items-center gap-2 rounded px-3 py-2 text-sm focus-visible:outline-white',
        'bg-white/15 font-medium' => $active,
        'hover:bg-white/10' => ! $active,
    ])
>
    <x-nav-icon :name="$icon" />
    <span class="nav-copy">{{ $label }}</span>
</a>
