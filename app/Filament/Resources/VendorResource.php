<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\BaseResource;
use App\Filament\Resources\VendorResource\Pages;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Procurement\Models\Vendor;

/**
 * Vendor / subcontractor master. sbu_class drives the PPh-final rate withheld on
 * subcontract bills; is_pkp decides whether their PPN is creditable input VAT.
 */
final class VendorResource extends BaseResource
{
    protected static ?string $model = Vendor::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Data Induk';

    protected static ?string $modelLabel = 'Vendor';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('code')->label('Kode')->required()->maxLength(32),
            TextInput::make('name')->label('Nama')->required(),
            TextInput::make('npwp')->label('NPWP')->maxLength(32),
            Select::make('sbu_class')->label('Kualifikasi SBU')->options([
                'small' => 'Kecil',
                'medium_large_spec' => 'Menengah / Besar / Spesialis',
                'none' => 'Tanpa Sertifikat',
            ])->helperText('Menentukan tarif PPh final konstruksi yang dipotong.'),
            Toggle::make('is_pkp')->label('PKP (menerbitkan faktur)')->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable(),
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('npwp')->label('NPWP')->placeholder('—'),
                TextColumn::make('sbu_class')->label('SBU')->badge()->placeholder('—'),
                IconColumn::make('is_pkp')->label('PKP')->boolean(),
            ])
            ->defaultSort('code');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendors::route('/'),
            'create' => Pages\CreateVendor::route('/create'),
            'edit' => Pages\EditVendor::route('/{record}/edit'),
        ];
    }
}
