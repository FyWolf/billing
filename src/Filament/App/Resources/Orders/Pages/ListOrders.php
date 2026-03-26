<?php

namespace Boy132\Billing\Filament\App\Resources\Orders\Pages;

use Boy132\Billing\Filament\App\Resources\Orders\OrdersResource;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrdersResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
