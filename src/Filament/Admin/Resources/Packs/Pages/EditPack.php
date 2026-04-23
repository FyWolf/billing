<?php

namespace Fywolf\Billing\Filament\Admin\Resources\Packs\Pages;

use Fywolf\Billing\Filament\Admin\Resources\Packs\PackResource;
use Fywolf\Billing\Filament\Admin\Resources\Packs\RelationManagers\PackExpansionRelationManager;
use Fywolf\Billing\Filament\Admin\Resources\Packs\RelationManagers\PackPriceRelationManager;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPack extends EditRecord
{
    protected static string $resource = PackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    public function getRelationManagers(): array
    {
        return [
            PackPriceRelationManager::class,
            PackExpansionRelationManager::class,
        ];
    }
}
