@props(['role'])

@php
    $isAdmin = in_array($role?->value, ['admin_utama', 'sub_admin'], true);
@endphp

<span {{ $attributes->merge(['class' => 'badge '.($isAdmin ? 'border-navy-200 bg-navy-50 text-navy-700' : 'border-line bg-paper text-ink-soft')]) }}>
    {{ $role?->label() ?? '—' }}
</span>
