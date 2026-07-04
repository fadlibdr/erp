<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\VendorBillResource\Pages;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Payables\Actions\ApproveSubcontractorBill;
use Modules\Payables\Models\VendorBill;
use Throwable;

/**
 * Subcontractor/vendor bills — the procure-to-pay money path. The *Approve* action
 * runs ApproveSubcontractorBill: it resolves the PPh-final rate (the contractor is
 * the withholder), decomposes the bill, and publishes the fact Finance posts as a
 * balanced accrual.
 */
final class VendorBillResource extends Resource
{
    protected static ?string $model = VendorBill::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Hutang';

    protected static ?string $modelLabel = 'Tagihan Vendor';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('vendor_id')->label('Vendor')->relationship('vendor', 'name')->searchable()->required(),
            Select::make('project_id')->label('Proyek')->relationship('project', 'name')->searchable(),
            TextInput::make('bill_date')->label('Tanggal Tagihan')->type('date')->required(),
            TextInput::make('contract_date')->label('Tanggal Kontrak Sub')->type('date')
                ->helperText('Menentukan rezim PPh final subkontrak.'),
            Select::make('service_class')->label('Klasifikasi Jasa')->options([
                'construction_work' => 'Pelaksanaan Konstruksi',
                'integrated_work' => 'Pekerjaan Terintegrasi',
                'planning_supervision' => 'Perencanaan / Pengawasan',
            ])->default('construction_work')->required(),
            TextInput::make('cost_code')->label('Kode Biaya')->default('SUB')->maxLength(32),
            TextInput::make('retention_percent')->label('Retensi %')->numeric()->default(5),
            TextInput::make('work_value_minor')->label('Nilai Pekerjaan (Rp)')->numeric()->required(),
            Select::make('status')->options(['draft' => 'Draft', 'approved' => 'Disetujui'])
                ->default('draft')->disabled()->dehydrated(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->label('No.')->searchable(),
                TextColumn::make('vendor.name')->label('Vendor')->searchable(),
                TextColumn::make('work_value_minor')->label('Nilai')->money('IDR'),
                TextColumn::make('pph_withheld_minor')->label('PPh Dipotong')->money('IDR')->placeholder('—'),
                TextColumn::make('net_payable_minor')->label('Neto Dibayar')->money('IDR')->placeholder('—'),
                TextColumn::make('status')->badge()->colors(['gray' => 'draft', 'success' => 'approved']),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Setujui')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (VendorBill $record): bool => $record->status !== 'approved')
                    ->action(function (VendorBill $record): void {
                        try {
                            app(ApproveSubcontractorBill::class)->execute($record);
                            Notification::make()->title('Tagihan disetujui; akrual diposting via outbox.')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Gagal menyetujui tagihan')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorBills::route('/'),
            'create' => Pages\CreateVendorBill::route('/create'),
            'edit' => Pages\EditVendorBill::route('/{record}/edit'),
        ];
    }
}
