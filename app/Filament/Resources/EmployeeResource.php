<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\BaseResource;
use App\Filament\Resources\EmployeeResource\Pages;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Payroll\Models\Employee;

/**
 * Employee master. PTKP status selects the monthly PPh 21 TER category (PMK 168/2023).
 */
final class EmployeeResource extends BaseResource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?string $navigationGroup = 'Data Induk';

    protected static ?string $modelLabel = 'Karyawan';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('code')->label('NIK')->required()->maxLength(32),
            TextInput::make('name')->label('Nama')->required(),
            TextInput::make('npwp')->label('NPWP')->maxLength(32),
            Select::make('ptkp_status')->label('Status PTKP')->required()->default('TK/0')->options([
                'TK/0' => 'TK/0', 'TK/1' => 'TK/1', 'TK/2' => 'TK/2', 'TK/3' => 'TK/3',
                'K/0' => 'K/0', 'K/1' => 'K/1', 'K/2' => 'K/2', 'K/3' => 'K/3',
            ])->helperText('Menentukan kategori TER PPh 21.'),
            TextInput::make('monthly_gross_minor')->label('Gaji Bruto Bulanan (Rp)')->numeric()->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('NIK')->searchable()->sortable(),
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('ptkp_status')->label('PTKP')->badge(),
                TextColumn::make('monthly_gross_minor')->label('Gaji Bruto')->money('IDR')->sortable(),
            ])
            ->defaultSort('code');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
