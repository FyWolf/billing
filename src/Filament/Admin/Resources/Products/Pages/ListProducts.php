<?php

namespace Fywolf\Billing\Filament\Admin\Resources\Products\Pages;

use Fywolf\Billing\Filament\Admin\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Create Product'),
        ];
    }
}
