@props(['label', 'value', 'hint' => null, 'icon' => 'monitor', 'tone' => 'navy'])

@php
    $tones = [
        'navy' => ['chip' => 'bg-navy-50 text-navy-900', 'bar' => 'bg-navy-800'],
        'moss' => ['chip' => 'bg-moss-50 text-moss-700', 'bar' => 'bg-moss-500'],
        'gold' => ['chip' => 'bg-gold-50 text-gold-700', 'bar' => 'bg-gold-500'],
        'brick' => ['chip' => 'bg-brick-50 text-brick-700', 'bar' => 'bg-brick-500'],
    ];

    $tone = $tones[$tone] ?? $tones['navy'];
@endphp

<div {{ $attributes->merge(['class' => 'card group relative overflow-hidden p-5']) }}>
    <span class="absolute inset-y-3 left-0 w-1 rounded-r-full {{ $tone['bar'] }}"></span>

    <div class="flex items-start justify-between gap-3 pl-3">
        <div class="min-w-0">
            <p class="truncate text-[11px] font-semibold uppercase tracking-wider text-ink-faint">{{ $label }}</p>
            <p class="mt-2 font-mono text-3xl font-semibold leading-none tracking-tight text-ink">{{ $value }}</p>
            @if ($hint)
                <p class="mt-2 text-xs leading-snug text-ink-faint">{{ $hint }}</p>
            @endif
        </div>

        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $tone['chip'] }} transition duration-200 group-hover:scale-105">
            <x-icon :name="$icon" size="h-5 w-5" />
        </span>
    </div>
</div>
