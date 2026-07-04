<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\BaseResource;
use App\Filament\Resources\PayRunResource\Pages;
use App\Filament\Resources\PayRunResource\RelationManagers\LinesRelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Payroll\Models\PayRun;
use Modules\Projects\Models\Project;

/**
 * Payroll runs. Runs are produced by the "Jalankan Penggajian" action (RunPayroll),
 * never a plain form — the figures are computed (PPh 21 TER + BPJS), so the resource
 * is read-only: list the frozen totals, view the per-employee breakdown.
 */
final class PayRunResource extends BaseResource
{
    protected static ?string $model = PayRun::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Penggajian';

    protected static ?string $modelLabel = 'Penggajian';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('period')->label('Periode')->sortable(),
                TextColumn::make('project_id')->label('Proyek')
                    ->formatStateUsing(fn (?string $state): string => $state ? (Project::find($state)?->name ?? '—') : '—'),
                TextColumn::make('cost_code')->label('Kode'),
                TextColumn::make('gross_minor')->label('Bruto')->money('IDR'),
                TextColumn::make('pph21_minor')->label('PPh 21')->money('IDR'),
                TextColumn::make('bpjs_employee_minor')->label('BPJS Kary.')->money('IDR')->toggleable(),
                TextColumn::make('net_minor')->label('Neto (THP)')->money('IDR'),
                TextColumn::make('status')->badge()->colors(['success' => 'approved']),
            ])
            ->defaultSort('period', 'desc');
    }

    public static function getRelations(): array
    {
        return [LinesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayRuns::route('/'),
            'view' => Pages\ViewPayRun::route('/{record}'),
        ];
    }
}
