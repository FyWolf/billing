<?php

namespace Fywolf\Billing\Filament\Admin\Resources\Orders\Pages;

use Fywolf\Billing\Enums\OrderStatus;
use Fywolf\Billing\Filament\Admin\Resources\Orders\OrderResource;
use Fywolf\Billing\Models\Customer;
use Fywolf\Billing\Models\Order;
use Fywolf\Billing\Models\PackPrice;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Create Order')
                ->createAnother(false)
                ->form([
                    Select::make('customer_id')
                        ->label('Customer')
                        ->required()
                        ->selectablePlaceholder(false)
                        ->options(fn () => Customer::with('user')
                            ->get()
                            ->mapWithKeys(fn (Customer $c) => [$c->id => $c->getLabel()])
                        )
                        ->searchable(),
                    Select::make('pack_price_id')
                        ->label('Pack')
                        ->required()
                        ->selectablePlaceholder(false)
                        ->options(fn () => PackPrice::with('pack')
                            ->get()
                            ->mapWithKeys(fn (PackPrice $p) => [$p->id => $p->pack->getLabel() . ' — ' . $p->getLabel()])
                        )
                        ->searchable(),
                    Toggle::make('manual_activation')
                        ->label('Admin activates (skip payment)')
                        ->helperText('Off = customer pays via Stripe. On = you activate the order manually, no payment required.')
                        ->default(false),
                ])
                ->using(fn (array $data) => Order::create([
                    'customer_id'     => $data['customer_id'],
                    'pack_price_id'   => $data['pack_price_id'],
                    'payment_gateway' => !empty($data['manual_activation']) ? 'manual' : null,
                    'status'          => OrderStatus::Pending->value,
                ])),
        ];
    }

    public function getDefaultActiveTab(): string
    {
        return OrderStatus::Active->value;
    }

    public function getTabs(): array
    {
        $tabs = [];

        foreach (OrderStatus::cases() as $orderStatus) {
            $tabs[$orderStatus->value] = Tab::make($orderStatus->getLabel())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $orderStatus->value))
                ->badge(fn () => Order::where('status', $orderStatus->value)->count())
                ->icon(fn () => $orderStatus->getIcon());
        }

        $tabs['all'] = Tab::make('All')->badge(fn () => Order::count());

        return $tabs;
    }
}
