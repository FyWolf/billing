<?php

namespace Fywolf\Billing;

use App\Contracts\Plugins\HasPluginSettings;
use App\Enums\CustomizationKey;
use App\Filament\App\Resources\Servers\ServerResource;
use App\Filament\Pages\Auth\EditProfile;
use App\Traits\EnvironmentWriterTrait;
use Filament\Actions\Action;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Fieldset;
use Fywolf\Billing\Http\Middleware\CancellationWarningMiddleware;
use Fywolf\Billing\Models\Coupon;
use Fywolf\Billing\Models\Customer;
use Fywolf\Billing\Models\Expansion;
use Fywolf\Billing\Models\Pack;
use Fywolf\Billing\Models\PackPrice;

class BillingPlugin implements HasPluginSettings, Plugin
{
    use EnvironmentWriterTrait;

    public function getId(): string
    {
        return 'billing';
    }

    public function register(Panel $panel): void
    {
        $id = str($panel->getId())->title();

        if ($panel->getId() === 'app') {
            ServerResource::embedServerList();

            $panel->profile(\Fywolf\Billing\Filament\App\Pages\EditProfile::class, false);

            $panel->navigation(true);
            $panel->topbar(function () {
                $navigationType = user()?->getCustomization(CustomizationKey::TopNavigation);

                return $navigationType === 'topbar' || $navigationType === 'mixed' || $navigationType === true;
            });

            $panel->navigationItems([
                NavigationItem::make(fn () => trans('filament-panels::auth/pages/edit-profile.label'))
                    ->icon('tabler-user-circle')
                    ->url(fn () => EditProfile::getUrl(panel: 'app'))
                    ->isActiveWhen(fn () => request()->routeIs(EditProfile::getRouteName()))
                    ->sort(99),
            ]);

            $panel->clearCachedComponents();
        }

        if ($panel->getId() === 'server') {
            $panel->middleware([CancellationWarningMiddleware::class]);
        }

        $panel->discoverResources(plugin_path($this->getId(), "src/Filament/$id/Resources"), "Fywolf\\Billing\\Filament\\$id\\Resources");
        $panel->discoverPages(plugin_path($this->getId(), "src/Filament/$id/Pages"), "Fywolf\\Billing\\Filament\\$id\\Pages");
        $panel->discoverWidgets(plugin_path($this->getId(), "src/Filament/$id/Widgets"), "Fywolf\\Billing\\Filament\\$id\\Widgets");
    }

    public function boot(Panel $panel): void {}

    public function getSettingsForm(): array
    {
        return [
            Select::make('currency')
                ->label('Currency')
                ->required()
                ->default(fn () => config('billing.currency'))
                ->options([
                    'USD' => 'US Dollar (USD)',
                    'EUR' => 'Euro (EUR)',
                    'GBP' => 'British Pound (GBP)',
                    'CAD' => 'Canadian Dollar (CAD)',
                    'AUD' => 'Australian Dollar (AUD)',
                ]),

            TextInput::make('grace_period_hours')
                ->label('Grace Period (hours)')
                ->numeric()
                ->minValue(0)
                ->maxValue(168)
                ->default(fn () => config('billing.grace_period_hours', 24))
                ->helperText('Hours a server stays online after a failed renewal payment before being suspended. 0 = suspend immediately.'),

            TagsInput::make('deployment_tags')
                ->label('Default Node Tags for Deployment'),

            Fieldset::make('Company Information')
                ->schema([
                    TextInput::make('company_name')
                        ->label('Company Name')
                        ->default(fn () => config('billing.company.name'))
                        ->placeholder('Acme Hosting Ltd.'),

                    TextInput::make('company_address')
                        ->label('Address')
                        ->default(fn () => config('billing.company.address'))
                        ->placeholder('123 Main Street'),

                    TextInput::make('company_city')
                        ->label('City')
                        ->default(fn () => config('billing.company.city'))
                        ->placeholder('New York'),

                    TextInput::make('company_zip')
                        ->label('ZIP / Postal Code')
                        ->default(fn () => config('billing.company.zip'))
                        ->placeholder('10001'),

                    TextInput::make('company_country')
                        ->label('Country')
                        ->default(fn () => config('billing.company.country'))
                        ->placeholder('United States'),

                    TextInput::make('company_email')
                        ->label('Email')
                        ->email()
                        ->default(fn () => config('billing.company.email'))
                        ->placeholder('billing@example.com'),

                    TextInput::make('company_phone')
                        ->label('Phone')
                        ->default(fn () => config('billing.company.phone'))
                        ->placeholder('+1 555 123 4567'),

                    TextInput::make('company_vat')
                        ->label('VAT / Tax ID')
                        ->default(fn () => config('billing.company.vat'))
                        ->placeholder('EU123456789'),

                    TextInput::make('company_website')
                        ->label('Website')
                        ->url()
                        ->default(fn () => config('billing.company.website'))
                        ->placeholder('https://example.com'),
                ]),

            Fieldset::make('Stripe')
                ->schema([
                    TextInput::make('stripe_key')
                        ->label('Publishable Key')
                        ->required()
                        ->default(fn () => config('billing.stripe.key'))
                        ->placeholder('pk_live_…'),

                    TextInput::make('stripe_secret')
                        ->label('Secret Key')
                        ->required()
                        ->password()
                        ->revealable()
                        ->default(fn () => config('billing.stripe.secret'))
                        ->placeholder('sk_live_…'),

                    TextInput::make('stripe_webhook_secret')
                        ->label('Webhook Signing Secret')
                        ->password()
                        ->revealable()
                        ->default(fn () => config('billing.stripe.webhook_secret'))
                        ->placeholder('whsec_…')
                        ->helperText('Found in Stripe Dashboard → Developers → Webhooks.'),

                    Placeholder::make('stripe_webhook_url')
                        ->label('Your Stripe Webhook URL')
                        ->content(fn () => url('/webhooks/stripe')),

                    Placeholder::make('stripe_webhook_events')
                        ->label('Required Webhook Events')
                        ->content('checkout.session.completed, invoice.paid, invoice.payment_failed, customer.subscription.deleted'),

                    Actions::make([
                        Action::make('reset_stripe_ids')
                            ->label('Reset cached Stripe IDs')
                            ->icon('tabler-refresh')
                            ->color('danger')
                            ->requiresConfirmation()
                            ->modalHeading('Reset cached Stripe IDs')
                            ->modalDescription('Clears every stored Stripe customer, product, price and coupon ID. Use this after switching between test and live keys. Objects are recreated automatically on the next checkout or when re-saved. Existing test-mode subscriptions will no longer renew.')
                            ->modalSubmitActionLabel('Reset IDs')
                            ->action(function (): void {
                                $cleared = [
                                    'customers'  => Customer::whereNotNull('stripe_customer_id')->update(['stripe_customer_id' => null]),
                                    'packs'      => Pack::whereNotNull('stripe_id')->update(['stripe_id' => null]),
                                    'prices'     => PackPrice::whereNotNull('stripe_id')->update(['stripe_id' => null]),
                                    'expansions' => Expansion::whereNotNull('stripe_id')->update(['stripe_id' => null]),
                                    'coupons'    => Coupon::whereNotNull('stripe_coupon_id')->update(['stripe_coupon_id' => null]),
                                ];

                                Notification::make()
                                    ->title('Cached Stripe IDs cleared')
                                    ->body(sprintf(
                                        '%d customers, %d packs, %d prices, %d expansions, %d coupons reset.',
                                        $cleared['customers'],
                                        $cleared['packs'],
                                        $cleared['prices'],
                                        $cleared['expansions'],
                                        $cleared['coupons'],
                                    ))
                                    ->success()
                                    ->send();
                            }),
                    ]),
                ]),
        ];
    }

    public function saveSettings(array $data): void
    {
        $env = [
            'BILLING_CURRENCY'           => $data['currency'],
            'BILLING_GRACE_PERIOD_HOURS' => $data['grace_period_hours'],
            'BILLING_DEPLOYMENT_TAGS'    => implode(',', $data['deployment_tags'] ?? []),
        ];

        $env['BILLING_COMPANY_NAME']    = $data['company_name'] ?? '';
        $env['BILLING_COMPANY_ADDRESS'] = $data['company_address'] ?? '';
        $env['BILLING_COMPANY_CITY']    = $data['company_city'] ?? '';
        $env['BILLING_COMPANY_COUNTRY'] = $data['company_country'] ?? '';
        $env['BILLING_COMPANY_ZIP']     = $data['company_zip'] ?? '';
        $env['BILLING_COMPANY_EMAIL']   = $data['company_email'] ?? '';
        $env['BILLING_COMPANY_PHONE']   = $data['company_phone'] ?? '';
        $env['BILLING_COMPANY_VAT']     = $data['company_vat'] ?? '';
        $env['BILLING_COMPANY_WEBSITE'] = $data['company_website'] ?? '';

        if (!empty($data['stripe_key']))            $env['STRIPE_KEY']            = $data['stripe_key'];
        if (!empty($data['stripe_secret']))         $env['STRIPE_SECRET']         = $data['stripe_secret'];
        if (!empty($data['stripe_webhook_secret'])) $env['STRIPE_WEBHOOK_SECRET'] = $data['stripe_webhook_secret'];

        $this->writeToEnvironment($env);

        Notification::make()
            ->title('Billing settings saved')
            ->success()
            ->send();
    }
}
