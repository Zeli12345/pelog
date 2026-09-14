@props(['status' => 'offline'])

@php
    $map = [
        'in_use' => ['label' => 'Digunakan', 'class' => 'border-moss-200 bg-moss-50 text-moss-700', 'dot' => 'bg-moss-500'],
        'available' => ['label' => 'Tersedia', 'class' => 'border-line bg-paper text-ink-soft', 'dot' => 'bg-ink-faint'],
        'offline' => ['label' => 'Offline', 'class' => 'border-gold-200 bg-gold-50 text-gold-700', 'dot' => 'bg-gold-500'],
        'maintenance' => ['label' => 'Perawatan', 'class' => 'border-brick-200 bg-brick-50 text-brick-700', 'dot' => 'bg-brick-500'],
    ];

    $item = $map[$status] ?? $map['offline'];
@endphp

<span {{ $attributes->merge(['class' => 'badge '.$item['class']]) }}>
    @if ($status === 'in_use')
        <span class="relative flex h-1.5 w-1.5">
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-moss-400 opacity-70"></span>
            <span class="relative inline-flex h-1.5 w-1.5 rounded-full {{ $item['dot'] }}"></span>
        </span>
    @else
        <span class="h-1.5 w-1.5 rounded-full {{ $item['dot'] }}"></span>
    @endif

    {{ $item['label'] }}
</span>
