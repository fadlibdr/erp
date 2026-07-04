<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\BaseResource;
use App\Filament\Resources\GrnResource\Pages;
use App\Filament\Resources\GrnResource\RelationManagers\LinesRelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Procurement\Models\Grn;
use Modules\Procurement\Models\PurchaseOrder;

/**
 * Goods receipt notes (penerimaan barang), created by the "Terima Barang" action on
 * a purchase order. Read-only.
 */
final class GrnResource extends BaseResource
{
    protected static ?string $model = Grn::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';

    protected static ?string $navigationGroup = 'Pengadaan';

    protected static ?string $modelLabel = 'Penerimaan Barang';

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
                TextColumn::make('number')->label('No. GRN')->searchable()->placeholder('—'),
                TextColumn::make('received_date')->label('Tanggal')->date()->sortable(),
                TextColumn::make('purchase_order_id')->label('PO')
                    ->formatStateUsing(fn (?string $state): string => $state ? (PurchaseOrder::find($state)?->number ?? '—') : '—'),
                TextColumn::make('total_minor')->label('Nilai')->money('IDR'),
            ])
            ->defaultSort('received_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [LinesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGrns::route('/'),
            'view' => Pages\ViewGrn::route('/{record}'),
        ];
    }
}
