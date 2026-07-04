<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\BaseResource;
use App\Filament\Resources\MaterialIssueResource\Pages;
use App\Filament\Resources\MaterialIssueResource\RelationManagers\LinesRelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Inventory\Models\MaterialIssue;
use Modules\Inventory\Models\Warehouse;
use Modules\Projects\Models\Project;

/**
 * Material issues — stock leaving a warehouse for a WBS/cost code, valued at moving
 * average. Created by the "Buat Pemakaian" action (IssueMaterials); read-only here.
 */
final class MaterialIssueResource extends BaseResource
{
    protected static ?string $model = MaterialIssue::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Inventaris';

    protected static ?string $modelLabel = 'Pemakaian Material';

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
                TextColumn::make('issue_date')->label('Tanggal')->date()->sortable(),
                TextColumn::make('project_id')->label('Proyek')
                    ->formatStateUsing(fn (?string $state): string => $state ? (Project::find($state)?->name ?? '—') : '—'),
                TextColumn::make('cost_code')->label('Kode'),
                TextColumn::make('warehouse_id')->label('Gudang')
                    ->formatStateUsing(fn (?string $state): string => $state ? (Warehouse::find($state)?->name ?? '—') : '—'),
                TextColumn::make('total_minor')->label('Nilai Pemakaian')->money('IDR'),
            ])
            ->defaultSort('issue_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [LinesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaterialIssues::route('/'),
            'view' => Pages\ViewMaterialIssue::route('/{record}'),
        ];
    }
}
