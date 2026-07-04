<?php

declare(strict_types=1);

namespace App\Filament\Resources\ArInvoiceResource\Pages;

use App\Filament\Resources\ArInvoiceResource;
use Filament\Resources\Pages\ListRecords;

final class ListArInvoices extends ListRecords
{
    protected static string $resource = ArInvoiceResource::class;
}
