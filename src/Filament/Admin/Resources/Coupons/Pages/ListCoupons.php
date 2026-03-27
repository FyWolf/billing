<?php

namespace Fywolf\Billing\Filament\Admin\Resources\Coupons\Pages;

use Fywolf\Billing\Filament\Admin\Resources\Coupons\CouponResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCoupons extends ListRecords
{
    protected static string $resource = CouponResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Create Coupon'),
        ];
    }
}
