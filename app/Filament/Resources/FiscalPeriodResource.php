<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\BaseResource;
use App\Filament\Resources\FiscalPeriodResource\Pages;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Billing\Models\ProgressClaim;
use Modules\Finance\Actions\CloseFiscalPeriod;
use Modules\Finance\Actions\ReopenFiscalPeriod;
use Modules\Finance\Models\FiscalPeriod;
use Modules\Projects\Models\BudgetLine;
use Modules\Projects\Models\Project;
use Throwable;

/**
 * Fiscal periods and the month-end close. The *Close* action is the app-tier
 * orchestration the Finance closer deliberately can't do itself: it gathers each
 * active project's commercials (contract value from Projects, estimated total cost
 * from the control budget, billed-to-date from Billing) — all above Finance in the
 * dependency law — and hands them to CloseFiscalPeriod, which owns the PSAK 72
 * recognition, the posting, and the lock.
 */
final class FiscalPeriodResource extends BaseResource
{
    protected static ?string $model = FiscalPeriod::class;

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';

    protected static ?string $navigationGroup = 'Keuangan';

    protected static ?string $modelLabel = 'Periode Fiskal';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('label')->label('Periode')->placeholder('2026-07')->required()->maxLength(16),
            TextInput::make('starts_on')->label('Mulai')->type('date')->required(),
            TextInput::make('ends_on')->label('Selesai')->type('date')->required(),
            Select::make('status')->options(['open' => 'Terbuka', 'closed' => 'Ditutup'])
                ->default('open')->disabled()->dehydrated(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->label('Periode')->sortable(),
                TextColumn::make('starts_on')->label('Mulai')->date(),
                TextColumn::make('ends_on')->label('Selesai')->date(),
                TextColumn::make('status')->badge()->colors(['success' => 'open', 'gray' => 'closed']),
                TextColumn::make('closed_at')->label('Ditutup')->dateTime()->placeholder('—'),
            ])
            ->actions([
                Action::make('close')
                    ->label('Tutup Buku')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Menjalankan pengakuan pendapatan PSAK 72 lalu mengunci periode.')
                    ->visible(fn (FiscalPeriod $record): bool => $record->status === 'open')
                    ->action(fn (FiscalPeriod $record) => self::closePeriod($record)),
                Action::make('reopen')
                    ->label('Buka Kembali')
                    ->icon('heroicon-o-lock-open')
                    ->requiresConfirmation()
                    ->visible(fn (FiscalPeriod $record): bool => $record->status === 'closed')
                    ->action(function (FiscalPeriod $record): void {
                        app(ReopenFiscalPeriod::class)->execute($record->company_id, $record->label);
                        Notification::make()->title('Periode dibuka kembali.')->success()->send();
                    }),
            ])
            ->defaultSort('label', 'desc');
    }

    private static function closePeriod(FiscalPeriod $record): void
    {
        try {
            $projects = Project::query()
                ->where('company_id', $record->company_id)
                ->where('status', 'active')
                ->get()
                ->map(fn (Project $p): array => [
                    'project_id' => $p->id,
                    'contract_value_minor' => (int) $p->contract_value_minor,
                    'estimated_total_cost_minor' => (int) BudgetLine::where('project_id', $p->id)->sum('budget_minor'),
                    'billed_to_date_minor' => (int) ProgressClaim::where('project_id', $p->id)
                        ->where('status', 'invoiced')->sum('work_value_minor'),
                ])
                ->all();

            $runs = app(CloseFiscalPeriod::class)->execute(
                $record->company_id,
                $record->label,
                $record->ends_on->format('Y-m-d'),
                $projects,
            );

            Notification::make()
                ->title('Tutup buku selesai.')
                ->body(count($runs).' proyek diakui pendapatannya; periode dikunci.')
                ->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Tutup buku gagal')->body($e->getMessage())->danger()->send();
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFiscalPeriods::route('/'),
            'create' => Pages\CreateFiscalPeriod::route('/create'),
        ];
    }
}
