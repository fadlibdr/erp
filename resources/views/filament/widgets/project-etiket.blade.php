<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Kartu Etiket — contoh dokumen (BAST-1)</x-slot>
        <x-slot name="description">Komponen <code>&lt;x-garis.etiket&gt;</code> · dipakai untuk SPK, BAST, BA Opname, addendum</x-slot>

        <x-garis.etiket
            title="Berita Acara Serah Terima Pertama (BAST-1)"
            rev="Rev. B"
            foot="e-Meterai terpasang · TTE tersertifikasi PSrE"
            stamp="setuju"
            stamp-label="Disetujui"
            :cells="[
                ['label' => 'No. Dokumen', 'value' => '012/BAST/PII-PDC/VII/26', 'mono' => true],
                ['label' => 'Kontrak', 'value' => '3900567913', 'mono' => true],
                ['label' => 'Tanggal', 'value' => '28 Agu 2026'],
                ['label' => 'Masa Pemeliharaan', 'value' => '180 hari'],
                ['label' => 'Disiapkan', 'value' => 'Site Manager — PII'],
                ['label' => 'Diperiksa', 'value' => 'Konsultan MK'],
                ['label' => 'Disetujui', 'value' => 'Direksi — PDC'],
                ['label' => 'Lampiran', 'value' => 'Punch list · 14 item'],
            ]"
        />
    </x-filament::section>
</x-filament-widgets::widget>
