@props(['name'])
@php
    $inner = match ($name) {
        'grid' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
        'truck' => '<path d="M1 7h11v8H1z"/><path d="M12 10h4l3 3v2h-7z"/><circle cx="6" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'receipt' => '<path d="M6 2h12v20l-3-2-3 2-3-2-3 2V2z"/><path d="M9 7h6M9 11h6M9 15h4"/>',
        'percent' => '<circle cx="7" cy="7" r="3"/><circle cx="17" cy="17" r="3"/><path d="M19 5L5 19"/>',
        'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
        'minus' => '<circle cx="12" cy="12" r="9"/><path d="M8 12h8"/>',
        'landmark' => '<path d="M3 10h18"/><path d="M5 10V20M9 10V20M15 10V20M19 10V20"/><path d="M3 20h18"/><path d="M12 3l9 7H3z"/>',
        'user' => '<path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="8" r="4"/>',
        'building' => '<path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/><path d="M16 9h2a2 2 0 0 1 2 2v10"/><path d="M8 7h4M8 11h4M8 15h4"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="3"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'office' => '<path d="M3 21h18"/><path d="M6 21V7l6-4 6 4v14"/><path d="M10 21v-4h4v4"/>',
        'folder' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'ruler' => '<path d="M3 16l13-13 5 5L8 21z"/><path d="M7 12l2 2M11 8l2 2M15 4l2 2"/>',
        'check' => '<path d="M20 6L9 17l-5-5"/>',
        'shield' => '<path d="M12 3l8 4v6c0 5-3.5 7.5-8 8-4.5-.5-8-3-8-8V7z"/>',
        'list' => '<path d="M8 6h13M8 12h13M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/>',
        'activity' => '<path d="M22 12h-4l-3 7-6-14-3 7H2"/>',
        default => '<circle cx="12" cy="12" r="8"/>',
    };
@endphp
<svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $inner !!}</svg>
