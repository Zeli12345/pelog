@props(['label', 'value', 'hint' => null, 'icon' => 'monitor', 'tone' => 'navy'])

@php
    $tones = [
        'navy' => 'bg-navy-50 text-navy-900',
        'moss' => 'bg-moss-50 text-moss-700',
        'gold' => 'bg-gold-50 text-gold-700',
        'brick' => 'bg-brick-50 text-brick-700',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'card p-4']) }}>
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-xs font-medium uppercase tracking-wide text-ink-faint">{{ $label }}</p>
            <p class="mt-1 font-mono text-2xl font-semibold text-ink">{{ $value }}</p>
            @if ($hint)
                <p class="mt-0.5 text-xs text-ink-faint">{{ $hint }}</p>
            @endif
        </div>
        <span class="flex h-9 w-9 items-center justify-center rounded-md {{ $tones[$tone] ?? $tones['navy'] }}">
            <x-icon :name="$icon" size="h-4 w-4" />
        </span>
    </div>
</div>
