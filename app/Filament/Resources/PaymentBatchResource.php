<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\BaseResource;
use App\Filament\Resources\PaymentBatchResource\Pages;
use App\Filament\Resources\PaymentBatchResource\RelationManagers\LinesRelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Payables\Models\PaymentBatch;

/**
 * Payment batches — bank runs that settle approved vendor bills. Created by the
 * "Bayar Terpilih" bulk action on the vendor-bill list, so this is read-only.
 */
final class PaymentBatchResource extends BaseResource
{
    protected static ?string $model = PaymentBatch::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Hutang';

    protected static ?string $modelLabel = 'Batch Pembayaran';

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
                TextColumn::make('number')->label('No.')->placeholder('—'),
                TextColumn::make('payment_date')->label('Tanggal')->date()->sortable(),
                TextColumn::make('bank')->label('Bank')->badge(),
                TextColumn::make('total_minor')->label('Total Dibayar')->money('IDR'),
            ])
            ->defaultSort('payment_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [LinesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentBatches::route('/'),
            'view' => Pages\ViewPaymentBatch::route('/{record}'),
        ];
    }
}
