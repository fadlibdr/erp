<?php

declare(strict_types=1);

namespace App\Filament\Resources\PayRunResource\Pages;

use App\Filament\Resources\PayRunResource;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Modules\Payroll\Actions\RunPayroll;
use Modules\Payroll\Models\Employee;
use Modules\Projects\Models\Project;
use Throwable;

final class ListPayRuns extends ListRecords
{
    protected static string $resource = PayRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('run')
                ->label('Jalankan Penggajian')
                ->icon('heroicon-o-play')
                ->form([
                    TextInput::make('period')->label('Periode (YYYY-MM)')->placeholder('2026-07')->required()->maxLength(7),
                    Select::make('project_id')->label('Proyek')
                        ->options(fn (): array => Project::query()->pluck('name', 'id')->all())
                        ->searchable()
                        ->helperText('Beban upah dibebankan ke proyek ini.'),
                    TextInput::make('cost_code')->label('Kode Biaya')->default('LAB')->required()->maxLength(32),
                    Select::make('employee_ids')->label('Karyawan')
                        ->multiple()->required()
                        ->options(fn (): array => Employee::query()
                            ->where('company_id', Filament::getTenant()?->getKey())
                            ->pluck('name', 'id')->all()),
                ])
                ->action(function (array $data): void {
                    $tenant = Filament::getTenant();
                    if ($tenant === null) {
                        return;
                    }
                    try {
                        $run = app(RunPayroll::class)->execute(
                            companyId: (string) $tenant->getKey(),
                            projectId: $data['project_id'] ?? null,
                            wbsId: null,
                            costCode: $data['cost_code'],
                            period: $data['period'],
                            employeeIds: $data['employee_ids'],
                        );
                        Notification::make()
                            ->title('Penggajian diproses.')
                            ->body('Neto Rp '.number_format((int) $run->net_minor, 0, ',', '.').' — jurnal upah diposting via outbox.')
                            ->success()->send();
                    } catch (Throwable $e) {
                        Notification::make()->title('Penggajian gagal')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
