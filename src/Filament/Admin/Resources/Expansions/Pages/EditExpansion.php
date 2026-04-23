<?php

namespace Fywolf\Billing\Filament\Admin\Resources\Expansions\Pages;

use Fywolf\Billing\Filament\Admin\Resources\Expansions\ExpansionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExpansion extends EditRecord
{
    protected static string $resource = ExpansionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
