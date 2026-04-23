<?php

namespace Fywolf\Billing\Providers;

use App\Enums\TabPosition;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\Role;
use App\Models\User;
use Fywolf\Billing\Console\Commands\CheckOrdersCommand;
use Fywolf\Billing\Filament\Admin\Resources\Users\RelationManagers\UserOrderRelationManager;
use Fywolf\Billing\Models\Customer;
use Fywolf\Billing\Models\Order;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

class BillingPluginProvider extends ServiceProvider
{
    public function register(): void
    {
        if (!empty(config('billing.stripe.secret'))) {
            $this->app->bind(StripeClient::class, fn () => new StripeClient(config('billing.stripe.secret')));
        }

        Role::registerCustomDefaultPermissions('customer');
        Role::registerCustomModelIcon('customer', 'tabler-user-dollar');

        Role::registerCustomDefaultPermissions('product');
        Role::registerCustomModelIcon('product', 'tabler-category');

        Role::registerCustomDefaultPermissions('pack');
        Role::registerCustomModelIcon('pack', 'tabler-package');

        Role::registerCustomDefaultPermissions('expansion');
        Role::registerCustomModelIcon('expansion', 'tabler-puzzle');
    }

    public function boot(): void
    {
        $this->warnMissingConfig();
        $this->registerUserRelationships();
        $this->extendUserResource();

        Schedule::command(CheckOrdersCommand::class)->everyFiveMinutes()->withoutOverlapping();
    }

    private function registerUserRelationships(): void
    {
        User::resolveRelationUsing('billingCustomer', fn (User $user) => $user->hasOne(Customer::class, 'user_id', 'id'));
        User::resolveRelationUsing('billingOrders', fn (User $user) => $user->hasManyThrough(Order::class, Customer::class, 'user_id', 'customer_id', 'id', 'id'));
    }

    private function extendUserResource(): void
    {
        UserResource::registerCustomTabs(TabPosition::After, $this->buildBillingTab());
        UserResource::registerCustomRelations(UserOrderRelationManager::class);
    }

    private function buildBillingTab(): Tab
    {
        return Tab::make('billing')
            ->label('Billing')
            ->icon('tabler-user-dollar')
            ->schema([
                Section::make('Billing Account')
                    ->relationship('billingCustomer')
                    ->columns(2)
                    ->schema([
                        TextInput::make('first_name')
                            ->label('First Name')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('last_name')
                            ->label('Last Name')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('balance')
                            ->label('Balance')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->suffix(config('billing.currency'))
                            ->columnSpanFull(),
                    ]),

                Section::make('Billing Address')
                    ->relationship('billingCustomer')
                    ->columns(2)
                    ->schema([
                        TextInput::make('company_name')
                            ->label('Company Name')
                            ->maxLength(255),
                        TextInput::make('vat_number')
                            ->label('VAT Number')
                            ->maxLength(50),
                        TextInput::make('address')
                            ->label('Address')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('address2')
                            ->label('Address Line 2')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('city')
                            ->label('City')
                            ->maxLength(100),
                        TextInput::make('zip')
                            ->label('ZIP / Postal Code')
                            ->maxLength(20),
                        Select::make('country')
                            ->label('Country')
                            ->searchable()
                            ->options(function (): array {
                                $codes = ['AF','AL','DZ','AD','AO','AR','AM','AU','AT','AZ','BS','BH','BD','BB','BY','BE','BZ','BJ','BT','BO','BA','BW','BR','BN','BG','BF','BI','CV','KH','CM','CA','CF','TD','CL','CN','CO','KM','CG','CD','CR','HR','CU','CY','CZ','DK','DJ','DM','DO','EC','EG','SV','GQ','ER','EE','SZ','ET','FJ','FI','FR','GA','GM','GE','DE','GH','GR','GD','GT','GN','GW','GY','HT','HN','HU','IS','IN','ID','IR','IQ','IE','IL','IT','JM','JP','JO','KZ','KE','KI','KP','KR','KW','KG','LA','LV','LB','LS','LR','LY','LI','LT','LU','MG','MW','MY','MV','ML','MT','MH','MR','MU','MX','FM','MD','MC','MN','ME','MA','MZ','MM','NA','NR','NP','NL','NZ','NI','NE','NG','MK','NO','OM','PK','PW','PA','PG','PY','PE','PH','PL','PT','QA','RO','RU','RW','KN','LC','VC','WS','SM','ST','SA','SN','RS','SC','SL','SG','SK','SI','SB','SO','ZA','SS','ES','LK','SD','SR','SE','CH','SY','TW','TJ','TZ','TH','TL','TG','TO','TT','TN','TR','TM','TV','UG','UA','AE','GB','US','UY','UZ','VU','VE','VN','YE','ZM','ZW'];
                                $countries = [];
                                foreach ($codes as $code) {
                                    $name = \Locale::getDisplayRegion("-{$code}", 'en');
                                    if ($name && $name !== $code) {
                                        $countries[$code] = $name;
                                    }
                                }
                                asort($countries);

                                return $countries;
                            }),
                        TextInput::make('siret')
                            ->label('SIRET')
                            ->maxLength(14),
                    ]),
            ]);
    }

    private function warnMissingConfig(): void
    {
        if (empty(config('billing.stripe.secret'))) {
            Log::warning(
                'Billing plugin: STRIPE_SECRET is not set. Stripe payments will not work. '
                . 'Configure it in Settings → Billing or set the STRIPE_SECRET environment variable.'
            );
        }
    }
}
