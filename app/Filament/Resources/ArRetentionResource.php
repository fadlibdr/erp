<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\BaseResource;
use App\Filament\Resources\ArRetentionResource\Pages;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Projects\Models\Project;
use Modules\Receivables\Actions\ReleaseRetention;
use Modules\Receivables\Models\ArRetention;
use Throwable;

/**
 * The retention sub-ledger — cash the customer holds until final hand-over. "Lepas
 * Retensi" releases a held retention (ReleaseRetention → Dr Bank / Cr Retention Rcv).
 */
final class ArRetentionResource extends BaseResource
{
    protected static ?string $model = ArRetention::class;

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';

    protected static ?string $navigationGroup = 'Piutang';

    protected static ?string $modelLabel = 'Retensi';

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
                TextColumn::make('project_id')->label('Proyek')
                    ->formatStateUsing(fn (?string $state): string => $state ? (Project::find($state)?->name ?? '—') : '—'),
                TextColumn::make('amount_minor')->label('Nilai Retensi')->money('IDR'),
                TextColumn::make('status')->badge()->colors(['warning' => 'held', 'success' => 'released']),
                TextColumn::make('expected_release_date')->label('Rencana Lepas')->date()->placeholder('—'),
                TextColumn::make('released_at')->label('Dilepas')->date()->placeholder('—'),
            ])
            ->actions([
                Action::make('release')
                    ->label('Lepas Retensi')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->visible(fn (ArRetention $record): bool => $record->status === 'held')
                    ->fillForm(fn (): array => ['release_date' => now()->format('Y-m-d')])
                    ->form([TextInput::make('release_date')->label('Tanggal Pelepasan')->type('date')->required()])
                    ->action(function (ArRetention $record, array $data): void {
                        try {
                            app(ReleaseRetention::class)->execute($record, $data['release_date']);
                            Notification::make()->title('Retensi dilepas; jurnal kas via outbox.')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Gagal melepas retensi')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArRetentions::route('/'),
        ];
    }
}
