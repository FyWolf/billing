<?php

namespace Fywolf\Billing\Filament\Admin\Resources\Customers\Pages;

use Fywolf\Billing\Filament\Admin\Resources\Customers\CustomerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Create Customer'),
        ];
    }
}
