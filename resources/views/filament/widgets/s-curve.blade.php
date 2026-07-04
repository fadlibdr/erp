<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Kurva-S — Rencana vs Realisasi</x-slot>
        <x-slot name="description">Proyek PDC Tower · deviasi ditulis eksplisit</x-slot>

        <svg viewBox="0 0 640 220" width="100%" role="img"
             aria-label="Kurva-S rencana versus realisasi, deviasi minus 3,1 persen">
            {{-- axes --}}
            <line x1="40" y1="10" x2="40" y2="185" stroke="#DEDEDA" stroke-width="1" />
            <line x1="40" y1="185" x2="620" y2="185" stroke="#DEDEDA" stroke-width="1" />
            <g font-family="IBM Plex Mono, monospace" font-size="10" fill="#6B6E76">
                <text x="8" y="14">100%</text>
                <text x="14" y="100">50%</text>
                <text x="22" y="188">0%</text>
                @foreach ($labels as $l)
                    <text x="{{ $l['x'] - 8 }}" y="203">{{ $l['m'] }}</text>
                @endforeach
            </g>
            {{-- plan (dashed grey) --}}
            <polyline points="{{ $planPts }}" fill="none" stroke="#6B6E76" stroke-width="2" stroke-dasharray="6 5" />
            {{-- realisation (solid Biru Baja) --}}
            <polyline points="{{ $actPts }}" fill="none" stroke="#1B5693" stroke-width="2.5" />
            {{-- deviation (amber tick from realised up to plan) --}}
            <line x1="{{ $mx }}" y1="{{ $my }}" x2="{{ $mx }}" y2="{{ $devTopY }}" stroke="#EDA200" stroke-width="2.5" />
            <circle cx="{{ $mx }}" cy="{{ $my }}" r="4" fill="#1B5693" />
            <g font-family="IBM Plex Mono, monospace" font-size="11" font-weight="600">
                <text x="{{ $mx + 12 }}" y="{{ $my - 8 }}" fill="#8A5A00">Deviasi {{ $deviasi }}%</text>
                <text x="{{ $mx + 12 }}" y="{{ $my + 8 }}" fill="#1A1C20">Realisasi {{ $realisasi }}%</text>
            </g>
        </svg>

        <div class="garis-legend">
            <span><i class="dash"></i>Rencana</span>
            <span><i></i>Realisasi</span>
            <span><i class="dev"></i>Deviasi</span>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
