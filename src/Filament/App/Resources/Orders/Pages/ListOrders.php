<?php

namespace Fywolf\Billing\Filament\App\Resources\Orders\Pages;

use Fywolf\Billing\Filament\App\Resources\Orders\OrdersResource;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrdersResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
