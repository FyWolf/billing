<?php

namespace Fywolf\Billing\Filament\App\Pages;

use App\Filament\Pages\Auth\EditProfile as BaseEditProfile;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Fywolf\Billing\Models\Customer;

class EditProfile extends BaseEditProfile
{
    protected function getDefaultTabs(): array
    {
        $tabs = parent::getDefaultTabs();

        // Account tab is always the first tab; append billing fields to it.
        $accountTab = $tabs[0];
        $existing = $accountTab->getDefaultChildComponents();
        $accountTab->schema([
            ...(is_array($existing) ? $existing : []),
            Section::make('Billing Profile')
                ->description('Used on your invoices and receipts.')
                ->columns(2)
                ->schema([
                    TextInput::make('billing_first_name')
                        ->label('First Name')
                        ->maxLength(255),
                    TextInput::make('billing_last_name')
                        ->label('Last Name')
                        ->maxLength(255),
                    TextInput::make('billing_company_name')
                        ->label('Company Name (optional)')
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('billing_address')
                        ->label('Address')
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('billing_address2')
                        ->label('Address Line 2')
                        ->maxLength(255)
                        ->columnSpanFull(),
                    TextInput::make('billing_city')
                        ->label('City')
                        ->maxLength(100),
                    TextInput::make('billing_zip')
                        ->label('ZIP / Postal Code')
                        ->maxLength(20),
                    TextInput::make('billing_country')
                        ->label('Country')
                        ->maxLength(100)
                        ->columnSpanFull(),
                    TextInput::make('billing_vat_number')
                        ->label('VAT Number')
                        ->placeholder('FR12345678901')
                        ->maxLength(20),
                    TextInput::make('billing_siret')
                        ->label('SIRET')
                        ->placeholder('12345678901234')
                        ->maxLength(14),
                ]),
        ]);

        return $tabs;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = parent::mutateFormDataBeforeFill($data);

        $customer = Customer::where('user_id', $this->getUser()->id)->first();
        $data['billing_first_name']   = $customer?->first_name;
        $data['billing_last_name']    = $customer?->last_name;
        $data['billing_company_name'] = $customer?->company_name;
        $data['billing_address']      = $customer?->address;
        $data['billing_address2']     = $customer?->address2;
        $data['billing_city']         = $customer?->city;
        $data['billing_zip']          = $customer?->zip;
        $data['billing_country']      = $customer?->country;
        $data['billing_vat_number']   = $customer?->vat_number;
        $data['billing_siret']        = $customer?->siret;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $billingFields = [
            'first_name'   => $data['billing_first_name'] ?? null,
            'last_name'    => $data['billing_last_name'] ?? null,
            'company_name' => $data['billing_company_name'] ?? null,
            'address'      => $data['billing_address'] ?? null,
            'address2'     => $data['billing_address2'] ?? null,
            'city'         => $data['billing_city'] ?? null,
            'zip'          => $data['billing_zip'] ?? null,
            'country'      => $data['billing_country'] ?? null,
            'vat_number'   => $data['billing_vat_number'] ?? null,
            'siret'        => $data['billing_siret'] ?? null,
        ];

        unset(
            $data['billing_first_name'], $data['billing_last_name'],
            $data['billing_company_name'], $data['billing_address'],
            $data['billing_address2'], $data['billing_city'],
            $data['billing_zip'], $data['billing_country'],
            $data['billing_vat_number'], $data['billing_siret'],
        );

        $data = parent::mutateFormDataBeforeSave($data);

        Customer::updateOrCreate(
            ['user_id' => $this->getUser()->id],
            array_merge($billingFields, [
                'first_name' => $billingFields['first_name'] ?: $this->getUser()->username,
                'last_name'  => $billingFields['last_name']  ?: $this->getUser()->username,
            ])
        );

        return $data;
    }
}
