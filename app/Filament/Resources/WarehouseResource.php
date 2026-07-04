<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\BaseResource;
use App\Filament\Resources\WarehouseResource\Pages;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Inventory\Models\Warehouse;
use Modules\Projects\Models\Project;

/**
 * Warehouse master. Leave the project empty for a central store, or set it for a
 * gudang proyek (site store).
 */
final class WarehouseResource extends BaseResource
{
    protected static ?string $model = Warehouse::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Data Induk';

    protected static ?string $modelLabel = 'Gudang';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('code')->label('Kode')->required()->maxLength(32),
            TextInput::make('name')->label('Nama')->required(),
            Select::make('project_id')->label('Proyek (gudang proyek)')
                ->options(fn (): array => Project::query()->pluck('name', 'id')->all())
                ->searchable()
                ->helperText('Kosongkan untuk gudang pusat.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable(),
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('project_id')->label('Proyek')
                    ->formatStateUsing(fn (?string $state): string => $state ? (Project::find($state)?->name ?? '—') : 'Pusat')
                    ->placeholder('Pusat'),
            ])
            ->defaultSort('code');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarehouses::route('/'),
            'create' => Pages\CreateWarehouse::route('/create'),
            'edit' => Pages\EditWarehouse::route('/{record}/edit'),
        ];
    }
}
