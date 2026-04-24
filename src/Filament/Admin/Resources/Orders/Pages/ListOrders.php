<?php

namespace Fywolf\Billing\Filament\Admin\Resources\Orders\Pages;

use Fywolf\Billing\Enums\OrderStatus;
use Fywolf\Billing\Filament\Admin\Resources\Orders\OrderResource;
use Fywolf\Billing\Models\Order;
use Filament\Actions\CreateAction;
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
                ->mutateFormDataBeforeCreate(fn (array $data) => array_merge(
                    ['payment_gateway' => 'manual', 'status' => OrderStatus::Pending->value],
                    $data
                )),
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
