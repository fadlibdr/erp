<?php

declare(strict_types=1);

namespace App\Filament\Resources\PayRunResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Payroll\Models\Employee;

/**
 * Per-employee decomposition of a payroll run: gross → PPh 21 + BPJS → net.
 */
final class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Rincian per Karyawan';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('employee_id')
            ->columns([
                TextColumn::make('employee_id')->label('Karyawan')
                    ->formatStateUsing(fn (string $state): string => Employee::find($state)?->name ?? $state),
                TextColumn::make('gross_minor')->label('Bruto')->money('IDR'),
                TextColumn::make('pph21_minor')->label('PPh 21')->money('IDR'),
                TextColumn::make('bpjs_employee_minor')->label('BPJS Kary.')->money('IDR'),
                TextColumn::make('bpjs_employer_minor')->label('BPJS Persh.')->money('IDR'),
                TextColumn::make('net_minor')->label('Neto (THP)')->money('IDR'),
            ]);
    }
}
