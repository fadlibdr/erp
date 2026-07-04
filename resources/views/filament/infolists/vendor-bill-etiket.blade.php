@php
    use Modules\Procurement\Models\Vendor;
    use Modules\Projects\Models\Project;

    $r = $getRecord();
    $rp = fn ($m) => 'Rp '.number_format((int) $m, 0, ',', '.');

    // Resolve related names with explicit queries (not lazy relations): this blade
    // is app-layer glue, and strict mode forbids lazy loading. It also sidesteps the
    // dependency law — Payables must not depend on Projects, so no model relation.
    $vendorName = Vendor::query()->whereKey($r->vendor_id)->value('name') ?? '—';
    $projectName = $r->project_id
        ? (Project::query()->whereKey($r->project_id)->value('name') ?? '—')
        : '—';

    $stamp = match ($r->status) {
        'paid' => 'lunas',
        'approved' => 'setuju',
        default => null,
    };
@endphp
<x-garis.etiket
    title="Tagihan Vendor / Subkontraktor"
    :rev="$r->number ?? 'Draf'"
    :foot="'Three-way match: '.($r->match_status ?? '—')"
    :stamp="$stamp"
    :cells="[
        ['label' => 'No. Tagihan', 'value' => $r->number ?? '—', 'mono' => true],
        ['label' => 'Vendor', 'value' => $vendorName],
        ['label' => 'Proyek', 'value' => $projectName],
        ['label' => 'Kode Biaya', 'value' => $r->cost_code ?? '—'],
        ['label' => 'Nilai Pekerjaan', 'value' => $rp($r->work_value_minor), 'mono' => true],
        ['label' => 'PPN Masukan', 'value' => $rp($r->ppn_input_minor), 'mono' => true],
        ['label' => 'Retensi', 'value' => $rp($r->retention_minor), 'mono' => true],
        ['label' => 'PPh Dipotong', 'value' => $rp($r->pph_withheld_minor), 'mono' => true],
        ['label' => 'Netto Dibayar', 'value' => $rp($r->net_payable_minor), 'mono' => true],
        ['label' => 'Status Alur', 'value' => ucfirst($r->status)],
    ]"
/>
