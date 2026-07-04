@props(['status' => 'setuju', 'size' => null])
@php
    // GARIS document stamp — final document states only, never workflow steps.
    $map = [
        'lunas' => ['label' => 'Lunas', 'class' => 'garis-stamp-lunas'],
        'setuju' => ['label' => 'Disetujui', 'class' => 'garis-stamp-setuju'],
        'sp' => ['label' => 'SP-2', 'class' => 'garis-stamp-sp'],
        'batal' => ['label' => 'Dibatalkan', 'class' => 'garis-stamp-batal'],
    ];
    $s = $map[$status] ?? $map['setuju'];
    $classes = 'garis-stamp '.$s['class'].($size === 'sm' ? ' garis-stamp-sm' : '');
@endphp
<span {{ $attributes->merge(['class' => $classes]) }}>{{ trim($slot) !== '' ? $slot : $s['label'] }}</span>
