<?php

namespace Fywolf\Billing\Filament\Admin\Resources\Packs\Pages;

use Fywolf\Billing\Filament\Admin\Resources\Packs\PackResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPacks extends ListRecords
{
    protected static string $resource = PackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
