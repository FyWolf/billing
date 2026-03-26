<?php

namespace Boy132\Billing\Filament\Admin\Resources\Coupons\Pages;

use Boy132\Billing\Enums\CouponType;
use Boy132\Billing\Filament\Admin\Resources\Coupons\CouponResource;
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
