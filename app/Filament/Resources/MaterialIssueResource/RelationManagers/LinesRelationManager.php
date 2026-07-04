<?php

declare(strict_types=1);

namespace App\Filament\Resources\MaterialIssueResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Inventory\Models\Item;

/**
 * The items issued on a material issue, each valued at moving average.
 */
final class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Item Dipakai';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('item_id')
            ->columns([
                TextColumn::make('item_id')->label('Item')
                    ->formatStateUsing(fn (string $state): string => Item::find($state)?->name ?? $state),
                TextColumn::make('quantity')->label('Kuantitas'),
                TextColumn::make('amount_minor')->label('Nilai (Rata2 Bergerak)')->money('IDR'),
            ]);
    }
}
