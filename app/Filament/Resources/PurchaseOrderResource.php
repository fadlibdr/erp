<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\BaseResource;
use App\Filament\Resources\PurchaseOrderResource\Pages;
use App\Filament\Resources\PurchaseOrderResource\RelationManagers\LinesRelationManager;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Procurement\Actions\ApprovePurchaseOrder;
use Modules\Procurement\Models\PurchaseOrder;
use Throwable;

/**
 * Purchase orders — the head of the cost-control loop. The *Approve* row action runs
 * the ApprovePurchaseOrder Action, which gates the PO against the project control
 * budget; a BLOCK surfaces as a red notification carrying the overspend, a WARN
 * approves but flags the row. The UI never posts or checks budgets itself — it only
 * calls the Action, so all the cost-control logic stays in one tested place.
 */
final class PurchaseOrderResource extends BaseResource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Pengadaan';

    protected static ?string $modelLabel = 'Purchase Order';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('project_id')->label('Proyek')
                ->relationship('project', 'name')->searchable()->required(),
            Select::make('vendor_id')->label('Vendor')
                ->relationship('vendor', 'name')->searchable()->required(),
            TextInput::make('po_date')->label('Tanggal PO')->type('date')->required(),
            TextInput::make('total_minor')->label('Nilai PO (Rp)')->numeric()->required(),
            Select::make('currency')->options(['IDR' => 'IDR', 'USD' => 'USD'])->default('IDR')->required(),
            Select::make('status')->options([
                'draft' => 'Draft', 'approved' => 'Disetujui', 'received' => 'Diterima', 'closed' => 'Ditutup',
            ])->default('draft')->disabled()->dehydrated(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('No. PO')->searchable(),
                TextColumn::make('project.name')->label('Proyek')->searchable(),
                TextColumn::make('vendor.name')->label('Vendor')->searchable(),
                TextColumn::make('total_minor')->label('Nilai')->money('IDR')->sortable(),
                TextColumn::make('status')->badge()->colors([
                    'gray' => 'draft', 'success' => 'approved', 'info' => 'received', 'warning' => 'closed',
                ]),
                TextColumn::make('budget_status')->label('Anggaran')->badge()->colors([
                    'success' => 'ok', 'warning' => 'warn',
                ]),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PurchaseOrder $record): bool => $record->status === 'draft')
                    ->action(function (PurchaseOrder $record): void {
                        try {
                            app(ApprovePurchaseOrder::class)->execute($record);
                            Notification::make()->title('PO disetujui, komitmen anggaran dibuat.')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('PO ditolak')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [LinesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'edit' => Pages\EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}
