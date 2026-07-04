<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\BaseResource;
use App\Filament\Resources\ArInvoiceResource\Pages;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Modules\Projects\Models\Project;
use Modules\Receivables\Actions\ReceiveCustomerPayment;
use Modules\Receivables\Models\ArInvoice;
use Throwable;

/**
 * Customer AR invoices (born from termin facts). The "Terima Pembayaran" action
 * records a cash receipt (ReceiveCustomerPayment → Dr Bank / Cr AR) and, once fully
 * settled, the invoice carries a Lunas stamp.
 */
final class ArInvoiceResource extends BaseResource
{
    protected static ?string $model = ArInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Piutang';

    protected static ?string $modelLabel = 'Faktur Piutang';

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
                TextColumn::make('number')->label('No.')->placeholder(fn (ArInvoice $r): string => 'dari klaim'),
                TextColumn::make('project_id')->label('Proyek')
                    ->formatStateUsing(fn (?string $state): string => $state ? (Project::find($state)?->name ?? '—') : '—'),
                TextColumn::make('gross_minor')->label('Bruto')->money('IDR'),
                TextColumn::make('net_minor')->label('Neto (DPP)')->money('IDR'),
                TextColumn::make('status')->badge()->colors(['warning' => 'open', 'success' => 'paid']),
                TextColumn::make('segel')->label('Segel')
                    ->state(fn (ArInvoice $record): ?string => $record->status === 'paid' ? 'lunas' : null)
                    ->formatStateUsing(fn (?string $state): HtmlString|string => $state
                        ? new HtmlString(Blade::render('<x-garis.stamp status="lunas" size="sm" />'))
                        : '—')
                    ->html(),
            ])
            ->actions([
                Action::make('receive')
                    ->label('Terima Pembayaran')
                    ->icon('heroicon-o-arrow-down-on-square')
                    ->color('success')
                    ->visible(fn (ArInvoice $record): bool => $record->status !== 'paid')
                    ->fillForm(fn (ArInvoice $record): array => [
                        'amount' => $record->net_minor,
                        'receipt_date' => now()->format('Y-m-d'),
                    ])
                    ->form([
                        TextInput::make('amount')->label('Jumlah Diterima (Rp)')->numeric()->required(),
                        TextInput::make('receipt_date')->label('Tanggal')->type('date')->required(),
                    ])
                    ->action(function (ArInvoice $record, array $data): void {
                        try {
                            app(ReceiveCustomerPayment::class)->execute($record, (int) $data['amount'], $data['receipt_date']);
                            Notification::make()->title('Penerimaan dicatat; jurnal kas via outbox.')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Gagal mencatat penerimaan')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->defaultSort('invoice_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArInvoices::route('/'),
        ];
    }
}
