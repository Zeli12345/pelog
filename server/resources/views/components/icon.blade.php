@props(['name', 'size' => 'h-4 w-4'])

@php
    $classes = $size.' shrink-0';
@endphp

<svg {{ $attributes->merge(['class' => $classes]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('layout-dashboard')
            <rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>
            @break
        @case('monitor')
            <rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
            @break
        @case('file-text')
            <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>
            @break
        @case('camera')
            <path d="M4 8h3l2-2h6l2 2h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13" r="3.5"/>
            @break
        @case('users')
            <circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 5a3.5 3.5 0 0 1 0 6.5M21.5 20a6 6 0 0 0-4.5-5.8"/>
            @break
        @case('user-check')
            <circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16.5 11.5l2.5 2.5 4-4"/>
            @break
        @case('book-open')
            <path d="M12 6c-2-1.5-4.5-2-8-2v14c3.5 0 6 .5 8 2 2-1.5 4.5-2 8-2V4c-3.5 0-6 .5-8 2z"/><path d="M12 6v14"/>
            @break
        @case('shield-check')
            <path d="M12 3l8 3v6c0 4.5-3.2 7.8-8 9-4.8-1.2-8-4.5-8-9V6z"/><path d="M9 12l2 2 4-4"/>
            @break
        @case('settings')
            <circle cx="12" cy="12" r="3"/><path d="M19.4 15a7.9 7.9 0 0 0 .1-2l2-1.5-2-3.5-2.4 1a8 8 0 0 0-1.7-1L15 5h-6l-.4 3a8 8 0 0 0-1.7 1l-2.4-1-2 3.5L4.5 13a7.9 7.9 0 0 0 .1 2l-2 1.5 2 3.5 2.4-1a8 8 0 0 0 1.7 1l.3 3h6l.4-3a8 8 0 0 0 1.7-1l2.4 1 2-3.5z"/>
            @break
        @case('logout')
            <path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 17l-5-5 5-5M4 12h11"/>
            @break
        @case('chevron-right')
            <path d="M9 5l7 7-7 7"/>
            @break
        @case('search')
            <circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14"/>
            @break
        @case('upload')
            <path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 20h16"/>
            @break
        @case('download')
            <path d="M12 4v12M7 11l5 5 5-5"/><path d="M4 20h16"/>
            @break
        @case('key')
            <circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17 6l3 3M14 9l2 2"/>
            @break
        @case('image')
            <rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-9 9"/>
            @break
        @case('alert-triangle')
            <path d="M12 4l9 16H3z"/><path d="M12 10v4M12 17.5v.5"/>
            @break
        @case('refresh')
            <path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 4v7h-7"/>
            @break
        @case('hard-drive')
            <rect x="3" y="5" width="18" height="6" rx="1.5"/><rect x="3" y="13" width="18" height="6" rx="1.5"/><path d="M7 8h.01M7 16h.01"/>
            @break
        @case('filter')
            <path d="M4 5h16l-6 7v6l-4 2v-8z"/>
            @break
        @case('eye')
            <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>
            @break
        @case('pencil')
            <path d="M4 20h4L20 8l-4-4L4 16z"/><path d="M14 6l4 4"/>
            @break
        @case('trash')
            <path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v6M14 11v6"/>
            @break
        @case('arrow-left')
            <path d="M19 12H5M11 18l-6-6 6-6"/>
            @break
        @case('menu')
            <path d="M4 6h16M4 12h16M4 18h16"/>
            @break
        @case('check')
            <path d="M5 13l4 4L19 7"/>
            @break
        @case('x')
            <path d="M6 6l12 12M18 6L6 18"/>
            @break
        @case('lock')
            <rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>
            @break
        @case('wifi-off')
            <path d="M2 8.5a16 16 0 0 1 8-4.3M14.5 4.6A16 16 0 0 1 22 8.5M5 12a11 11 0 0 1 5-2.7M17.5 10.4A11 11 0 0 1 19 12M8.5 15.5a6 6 0 0 1 7 0M12 19h.01"/><path d="M3 3l18 18"/>
            @break
        @default
            <circle cx="12" cy="12" r="9"/>
    @endswitch
</svg>
