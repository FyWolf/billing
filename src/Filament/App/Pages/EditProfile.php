<?php

namespace Fywolf\Billing\Filament\App\Pages;

use App\Filament\Pages\Auth\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Fywolf\Billing\Models\Customer;

class EditProfile extends BaseEditProfile
{
    protected function getDefaultTabs(): array
    {
        $tabs = parent::getDefaultTabs();

        // Account tab is always the first tab; append billing name fields to it.
        $accountTab = $tabs[0];
        $accountTab->schema([
            ...$accountTab->getChildComponents(),
            Section::make('Billing Profile')
                ->description('Used on invoices and receipts.')
                ->columns(2)
                ->schema([
                    TextInput::make('billing_first_name')
                        ->label('First Name')
                        ->maxLength(255),
                    TextInput::make('billing_last_name')
                        ->label('Last Name')
                        ->maxLength(255),
                ]),
        ]);

        return $tabs;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = parent::mutateFormDataBeforeFill($data);

        $customer = Customer::where('user_id', $this->getUser()->id)->first();
        $data['billing_first_name'] = $customer?->first_name;
        $data['billing_last_name']  = $customer?->last_name;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $firstName = $data['billing_first_name'] ?? null;
        $lastName  = $data['billing_last_name'] ?? null;

        unset($data['billing_first_name'], $data['billing_last_name']);

        $data = parent::mutateFormDataBeforeSave($data);

        Customer::updateOrCreate(
            ['user_id' => $this->getUser()->id],
            [
                'first_name' => $firstName ?: $this->getUser()->username,
                'last_name'  => $lastName  ?: $this->getUser()->username,
            ]
        );

        return $data;
    }
}
