<?php

declare(strict_types=1);

namespace App\Filament\Resources\PurchaseOrderResource\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * PO lines — each tagged with the WBS node and cost code it burdens, which is what
 * the commitment is bucketed by. Editing these before approval is what determines
 * which control-budget line the PO is checked against.
 */
final class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Item PO';

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('description')->label('Uraian')->required(),
            TextInput::make('wbs_id')->label('WBS')->helperText('Node WBS untuk kontrol anggaran'),
            TextInput::make('cost_code')->label('Kode Biaya')->default('MAT')->maxLength(32),
            TextInput::make('quantity')->label('Kuantitas')->numeric()->default(1),
            TextInput::make('unit_rate_minor')->label('Harga Satuan (Rp)')->numeric()->default(0),
            TextInput::make('amount_minor')->label('Jumlah (Rp)')->numeric()->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('description')->label('Uraian'),
                TextColumn::make('cost_code')->label('Kode'),
                TextColumn::make('amount_minor')->label('Jumlah')->money('IDR'),
            ]);
    }
}
