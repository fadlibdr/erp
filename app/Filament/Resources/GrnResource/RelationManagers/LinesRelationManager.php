<?php

declare(strict_types=1);

namespace App\Filament\Resources\GrnResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Inventory\Models\Item;

/**
 * The items received on a GRN.
 */
final class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Item Diterima';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('item_id')->label('Item')
                    ->formatStateUsing(fn (?string $state): string => $state ? (Item::find($state)?->name ?? '—') : '—'),
                TextColumn::make('cost_code')->label('Kode'),
                TextColumn::make('quantity')->label('Kuantitas'),
                TextColumn::make('amount_minor')->label('Nilai')->money('IDR'),
            ]);
    }
}
