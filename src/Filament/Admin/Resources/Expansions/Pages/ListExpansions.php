<?php

namespace Fywolf\Billing\Filament\Admin\Resources\Expansions\Pages;

use Fywolf\Billing\Filament\Admin\Resources\Expansions\ExpansionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExpansions extends ListRecords
{
    protected static string $resource = ExpansionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
