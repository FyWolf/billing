<?php

namespace Fywolf\Billing\Filament\Admin\Resources\Packs\Pages;

use Fywolf\Billing\Filament\Admin\Resources\Packs\PackResource;
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
}
