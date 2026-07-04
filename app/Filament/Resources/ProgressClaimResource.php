<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ProgressClaimResource\Pages;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Modules\Billing\Actions\IssueTerminInvoice;
use Modules\Billing\Models\ProgressClaim;
use Throwable;

/**
 * Progress claims (opname → Berita Acara → termin). The *Issue Termin* action runs
 * IssueTerminInvoice, which computes the termin (work − uang muka − retensi + PPN,
 * PPh final withheld) and publishes the fact the ledger and e-Faktur both consume.
 */
final class ProgressClaimResource extends Resource
{
    protected static ?string $model = ProgressClaim::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';

    protected static ?string $navigationGroup = 'Penagihan';

    protected static ?string $modelLabel = 'Progress Claim';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('project_id')->label('Proyek')->relationship('project', 'name')->searchable()->required(),
            TextInput::make('sequence')->label('Termin ke-')->numeric()->required(),
            TextInput::make('claim_date')->label('Tanggal')->type('date')->required(),
            TextInput::make('work_value_minor')->label('Nilai Pekerjaan (Rp)')->numeric()->required(),
            Select::make('status')->options([
                'draft' => 'Draft', 'bapp' => 'BA Pembayaran', 'invoiced' => 'Ditagih',
            ])->default('draft')->disabled()->dehydrated(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('project.name')->label('Proyek')->searchable(),
                TextColumn::make('sequence')->label('Termin')->sortable(),
                TextColumn::make('work_value_minor')->label('Nilai Pekerjaan')->money('IDR'),
                TextColumn::make('net_receivable_minor')->label('Termin Bersih')->money('IDR')->placeholder('—'),
                TextColumn::make('status')->badge()->colors([
                    'gray' => 'draft', 'warning' => 'bapp', 'success' => 'invoiced',
                ]),
                // GARIS stamp: "Disetujui" once the termin is issued (final state).
                TextColumn::make('segel')->label('Segel')
                    ->state(fn ($record): ?string => $record->status === 'invoiced' ? 'setuju' : null)
                    ->formatStateUsing(fn (?string $state): HtmlString|string => $state
                        ? new HtmlString(Blade::render('<x-garis.stamp status="setuju" size="sm" />'))
                        : '—')
                    ->html(),
            ])
            ->actions([
                Action::make('issue')
                    ->label('Terbitkan Termin')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (ProgressClaim $record): bool => $record->status !== 'invoiced')
                    ->action(function (ProgressClaim $record): void {
                        try {
                            app(IssueTerminInvoice::class)->execute($record);
                            Notification::make()->title('Termin diterbitkan; jurnal & e-Faktur mengikuti.')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Gagal menerbitkan termin')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->defaultSort('sequence');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProgressClaims::route('/'),
            'create' => Pages\CreateProgressClaim::route('/create'),
            'edit' => Pages\EditProgressClaim::route('/{record}/edit'),
        ];
    }
}
