<?php

namespace Fywolf\Billing\Filament\Admin\Resources\Products\Pages;

use Fywolf\Billing\Filament\Admin\Resources\Products\ProductResource;
use Fywolf\Billing\Models\Product;
use Filament\Resources\Pages\CreateRecord;

/**
 * @property Product $record
 */
class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected static bool $canCreateAnother = false;

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getCreateFormAction()->formId('form'),
        ];
    }
}
