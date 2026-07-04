<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\BaseResource;
use App\Filament\Resources\JournalResource\Pages;
use App\Filament\Resources\JournalResource\RelationManagers\LinesRelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Finance\Models\Journal;

/**
 * A read-only window on the general ledger. Journals are immutable by design —
 * corrections are reversals, never edits — so the UI offers no create/edit/delete,
 * only inspection. Every posting that any module's fact produced lands here,
 * sliceable by the fact type that created it.
 */
final class JournalResource extends BaseResource
{
    protected static ?string $model = Journal::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Keuangan';

    protected static ?string $modelLabel = 'Jurnal';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('No. Jurnal')->searchable(),
                TextColumn::make('date')->label('Tanggal')->date()->sortable(),
                TextColumn::make('description')->label('Uraian')->wrap(),
                TextColumn::make('fact_type')->label('Sumber Fakta')->badge()->toggleable(),
                TextColumn::make('total_minor')->label('Nilai')->money('IDR')->sortable(),
            ])
            ->defaultSort('date', 'desc');
    }

    public static function getRelations(): array
    {
        return [LinesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJournals::route('/'),
            'view' => Pages\ViewJournal::route('/{record}'),
        ];
    }
}
