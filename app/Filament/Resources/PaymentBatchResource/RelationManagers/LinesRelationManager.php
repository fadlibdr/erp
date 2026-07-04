<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentBatchResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Payables\Models\VendorBill;

/**
 * The bills settled within a payment batch, and for how much.
 */
final class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Tagihan Dibayar';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('vendor_bill_id')
            ->columns([
                TextColumn::make('vendor_bill_id')->label('No. Tagihan')
                    ->formatStateUsing(fn (string $state): string => VendorBill::find($state)?->number ?? $state),
                TextColumn::make('amount_minor')->label('Jumlah')->money('IDR'),
            ]);
    }
}
