<?php

namespace Boy132\Billing\Filament\Admin\Resources\Products\Pages;

use Boy132\Billing\Filament\Admin\Resources\Products\ProductResource;
use Boy132\Billing\Filament\Admin\Resources\Products\RelationManagers\PriceRelationManager;
use Boy132\Billing\Models\Product;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * @property Product $record
 */
class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            $this->getSaveFormAction()->formId('form'),
        ];
    }

    public function getRelationManagers(): array
    {
        return [
            PriceRelationManager::class,
        ];
    }
}
