<?php

declare(strict_types=1);

namespace App\Filament\Resources\JournalResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The lines of a journal, read-only — account, debit/credit, and the project/WBS/
 * cost-code dimensions that make the whole ledger sliceable by project.
 */
final class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Baris Jurnal';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('account_code')
            ->columns([
                TextColumn::make('account_code')->label('Akun'),
                TextColumn::make('debit_minor')->label('Debit')->money('IDR'),
                TextColumn::make('credit_minor')->label('Kredit')->money('IDR'),
                TextColumn::make('project_id')->label('Proyek')->toggleable(),
                TextColumn::make('cost_code')->label('Kode Biaya')->toggleable(),
                TextColumn::make('memo')->label('Memo')->wrap()->toggleable(),
            ]);
    }
}
