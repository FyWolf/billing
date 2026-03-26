<?php

namespace Boy132\Billing\Filament\Admin\Resources\Customers\Pages;

use Boy132\Billing\Filament\Admin\Resources\Customers\CustomerResource;
use Boy132\Billing\Filament\Admin\Resources\Customers\RelationManagers\OrderRelationManager;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

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
            OrderRelationManager::class,
        ];
    }
}
