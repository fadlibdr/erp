@props([
    'title' => '',
    'rev' => null,
    'cells' => [],       // [['label' => 'No. Dokumen', 'value' => '012/...', 'mono' => true], ...]
    'foot' => null,
    'stamp' => null,     // lunas | setuju | sp | batal
    'stampLabel' => null,
])
{{-- GARIS etiket — a drawing title-block card for SPK, BAST, BA Opname, addendum. --}}
<div {{ $attributes->merge(['class' => 'garis-etiket']) }}>
    <div class="garis-etiket-head">
        <b>{{ $title }}</b>
        @if ($rev)
            <span>{{ $rev }}</span>
        @endif
    </div>
    <div class="garis-etiket-grid">
        @foreach ($cells as $cell)
            <div class="garis-etiket-cell">
                <small>{{ $cell['label'] ?? '' }}</small>
                <b @class(['garis-mono' => $cell['mono'] ?? false])>{{ $cell['value'] ?? '' }}</b>
            </div>
        @endforeach
    </div>
    @if ($foot || $stamp)
        <div class="garis-etiket-foot">
            <span class="garis-mono">{{ $foot }}</span>
            @if ($stamp)
                <x-garis.stamp :status="$stamp">{{ $stampLabel }}</x-garis.stamp>
            @endif
        </div>
    @endif
</div>
