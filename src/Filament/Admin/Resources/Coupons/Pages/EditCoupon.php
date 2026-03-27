<?php

namespace Fywolf\Billing\Filament\Admin\Resources\Coupons\Pages;

use Fywolf\Billing\Enums\CouponType;
use Fywolf\Billing\Filament\Admin\Resources\Coupons\CouponResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCoupon extends EditRecord
{
    protected static string $resource = CouponResource::class;

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

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['coupon_type'] = $data['amount_off'] ? CouponType::Amount : CouponType::Percentage;

        return $data;
    }
}
