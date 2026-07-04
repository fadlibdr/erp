<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectResource\Pages;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Projects\Models\Project;

/**
 * The project master: contract commercials that drive termin billing (retention %,
 * uang muka %) and the PPh-final regime (contract date). Money is edited in minor
 * units (whole rupiah for IDR) — the same integers the domain stores, no float step.
 */
final class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Proyek';

    protected static ?string $modelLabel = 'Proyek';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('code')->label('Kode')->required()->maxLength(32),
            TextInput::make('name')->label('Nama Proyek')->required(),
            TextInput::make('contract_number')->label('No. Kontrak')->maxLength(64),
            TextInput::make('contract_date')->label('Tanggal Kontrak')->type('date')
                ->helperText('Menentukan rezim PPh final (aturan transisi PP 51/2008 vs PP 9/2022).'),
            Select::make('service_class')->label('Klasifikasi Jasa')->options([
                'integrated_work' => 'Pekerjaan Terintegrasi (EPC)',
                'construction_work' => 'Pelaksanaan Konstruksi',
                'planning_supervision' => 'Perencanaan / Pengawasan',
            ])->default('integrated_work')->required(),
            TextInput::make('contract_value_minor')->label('Nilai Kontrak (Rp)')->numeric()->required(),
            TextInput::make('retention_percent')->label('Retensi %')->numeric()->default(5),
            TextInput::make('uang_muka_percent')->label('Uang Muka %')->numeric()->default(20),
            Select::make('status')->options([
                'planning' => 'Perencanaan', 'active' => 'Aktif', 'maintenance' => 'Pemeliharaan', 'closed' => 'Selesai',
            ])->default('active')->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable(),
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('contract_value_minor')->label('Nilai Kontrak')->money('IDR')->sortable(),
                TextColumn::make('status')->badge(),
            ])
            ->defaultSort('code');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
        ];
    }
}
