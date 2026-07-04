<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Modules\Finance\Services\CommitmentRepository;
use Modules\Projects\Models\BudgetLine;
use Modules\Projects\Models\Project;

/**
 * The cost-control dashboard: every control-budget line with its live open
 * commitment, booked actual, and remaining headroom — the same three numbers the
 * PO approval gate weighs. "Tersedia" (available) turns red once a bucket is
 * overspent, so a project manager sees a breach before the next PO is even raised.
 *
 * Read-only and computed through the very CommitmentRepository the domain uses, so
 * the badge and the gate can never disagree.
 */
final class BudgetControlWidget extends BaseWidget
{
    protected static ?string $heading = 'Kendali Anggaran — Anggaran vs Komitmen vs Aktual';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(BudgetLine::query())
            ->columns([
                TextColumn::make('project_id')->label('Proyek')
                    ->formatStateUsing(fn (string $state): string => Project::find($state)?->name ?? $state),
                TextColumn::make('cost_code')->label('Kode'),
                TextColumn::make('budget_minor')->label('Anggaran')->money('IDR'),
                TextColumn::make('committed')->label('Komitmen')->money('IDR')
                    ->state(fn (BudgetLine $record): int => $this->exposure($record)['open']),
                TextColumn::make('actual')->label('Aktual')->money('IDR')
                    ->state(fn (BudgetLine $record): int => $this->exposure($record)['actual']),
                TextColumn::make('available')->label('Tersedia')->money('IDR')
                    ->state(function (BudgetLine $record): int {
                        $e = $this->exposure($record);

                        return (int) $record->budget_minor - $e['open'] - $e['actual'];
                    })
                    ->color(fn (int $state): string => $state < 0 ? 'danger' : 'success'),
            ]);
    }

    /** @return array{open: int, actual: int} */
    private function exposure(BudgetLine $line): array
    {
        $companyId = Project::find($line->project_id)?->company_id ?? '';

        return app(CommitmentRepository::class)->exposureFor($companyId, $line->project_id, $line->wbs_id, $line->cost_code);
    }
}
