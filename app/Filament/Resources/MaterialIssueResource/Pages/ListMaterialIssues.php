<?php

declare(strict_types=1);

namespace App\Filament\Resources\MaterialIssueResource\Pages;

use App\Filament\Resources\MaterialIssueResource;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Modules\Inventory\Actions\IssueMaterials;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\Warehouse;
use Modules\Projects\Models\Project;
use Throwable;

final class ListMaterialIssues extends ListRecords
{
    protected static string $resource = MaterialIssueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('issue')
                ->label('Buat Pemakaian')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    Select::make('project_id')->label('Proyek')->required()
                        ->options(fn (): array => Project::query()->pluck('name', 'id')->all()),
                    TextInput::make('cost_code')->label('Kode Biaya')->default('MAT')->required(),
                    Select::make('warehouse_id')->label('Gudang')->required()
                        ->options(fn (): array => Warehouse::query()->where('company_id', Filament::getTenant()?->getKey())->pluck('name', 'id')->all()),
                    TextInput::make('issue_date')->label('Tanggal')->type('date')->required()->default(now()->format('Y-m-d')),
                    Select::make('item_id')->label('Item')->required()
                        ->options(fn (): array => Item::query()->where('company_id', Filament::getTenant()?->getKey())->pluck('name', 'id')->all()),
                    TextInput::make('qty')->label('Kuantitas')->numeric()->required(),
                ])
                ->action(function (array $data): void {
                    $tenant = Filament::getTenant();
                    if ($tenant === null) {
                        return;
                    }
                    try {
                        $issue = app(IssueMaterials::class)->execute(
                            companyId: (string) $tenant->getKey(),
                            projectId: $data['project_id'],
                            wbsId: null,
                            costCode: $data['cost_code'],
                            warehouseId: $data['warehouse_id'],
                            issueDate: $data['issue_date'],
                            lines: [['item_id' => $data['item_id'], 'qty_milli' => (int) round(((float) $data['qty']) * 1000)]],
                        );
                        Notification::make()
                            ->title('Pemakaian dicatat.')
                            ->body('Nilai Rp '.number_format((int) $issue->total_minor, 0, ',', '.').' dibebankan ke proyek; jurnal via outbox.')
                            ->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Gagal mencatat pemakaian')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
