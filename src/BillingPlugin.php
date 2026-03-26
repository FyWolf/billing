<?php

namespace Boy132\Billing;

use App\Contracts\Plugins\HasPluginSettings;
use App\Enums\CustomizationKey;
use App\Filament\App\Resources\Servers\ServerResource;
use App\Filament\Pages\Auth\EditProfile;
use App\Traits\EnvironmentWriterTrait;
use Filament\Contracts\Plugin;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Notification;
use Filament\Panel;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;

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

        $panel->discoverResources(plugin_path($this->getId(), "src/Filament/$id/Resources"), "Boy132\\Billing\\Filament\\$id\\Resources");
        $panel->discoverPages(plugin_path($this->getId(), "src/Filament/$id/Pages"), "Boy132\\Billing\\Filament\\$id\\Pages");
        $panel->discoverWidgets(plugin_path($this->getId(), "src/Filament/$id/Widgets"), "Boy132\\Billing\\Filament\\$id\\Widgets");
    }

    public function boot(Panel $panel): void {}

    public function getSettingsForm(): array
    {
        return [
            // ------------------------------------------------------------------
            // General
            // ------------------------------------------------------------------
            Select::make('active_gateway')
                ->label('Active Payment Gateway')
                ->required()
                ->default(fn () => config('billing.active_gateway', 'stripe'))
                ->options([
                    'stripe' => 'Stripe',
                    'paypal' => 'PayPal',
                ])
                ->live()
                ->helperText('Switch between gateways at any time. Existing orders remember which gateway was used.'),

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
                ->maxValue(168) // 1 week max
                ->default(fn () => config('billing.grace_period_hours', 24))
                ->helperText('Hours a server stays online after order expiry before being suspended. 0 = suspend immediately.'),

            TagsInput::make('deployment_tags')
                ->label('Default Node Tags for Deployment'),

            // ------------------------------------------------------------------
            // Stripe
            // ------------------------------------------------------------------
            Fieldset::make('Stripe')
                ->visible(fn (Get $get) => $get('active_gateway') === 'stripe')
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
                        ->helperText('Found in Stripe Dashboard → Developers → Webhooks. Required for secure event processing.'),

                    Placeholder::make('stripe_webhook_url')
                        ->label('Your Stripe Webhook URL')
                        ->content(fn () => url('/webhooks/stripe')),
                ]),

            // ------------------------------------------------------------------
            // PayPal
            // ------------------------------------------------------------------
            Fieldset::make('PayPal')
                ->visible(fn (Get $get) => $get('active_gateway') === 'paypal')
                ->schema([
                    Select::make('paypal_mode')
                        ->label('Mode')
                        ->required()
                        ->default(fn () => config('billing.paypal.mode', 'sandbox'))
                        ->options([
                            'sandbox' => 'Sandbox (testing)',
                            'live'    => 'Live (production)',
                        ]),

                    TextInput::make('paypal_client_id')
                        ->label('Client ID')
                        ->required()
                        ->default(fn () => config('billing.paypal.client_id')),

                    TextInput::make('paypal_secret')
                        ->label('Secret')
                        ->required()
                        ->password()
                        ->revealable()
                        ->default(fn () => config('billing.paypal.secret')),

                    TextInput::make('paypal_webhook_id')
                        ->label('Webhook ID')
                        ->default(fn () => config('billing.paypal.webhook_id'))
                        ->helperText('From PayPal Developer Dashboard → Apps → Webhooks. Required for secure event processing.'),

                    Placeholder::make('paypal_webhook_url')
                        ->label('Your PayPal Webhook URL')
                        ->content(fn () => url('/webhooks/paypal')),
                ]),
        ];
    }

    public function saveSettings(array $data): void
    {
        $env = [
            'BILLING_GATEWAY'            => $data['active_gateway'],
            'BILLING_CURRENCY'           => $data['currency'],
            'BILLING_GRACE_PERIOD_HOURS' => $data['grace_period_hours'],
            'BILLING_DEPLOYMENT_TAGS'    => implode(',', $data['deployment_tags'] ?? []),
        ];

        // Stripe
        if (!empty($data['stripe_key']))            $env['STRIPE_KEY']            = $data['stripe_key'];
        if (!empty($data['stripe_secret']))         $env['STRIPE_SECRET']         = $data['stripe_secret'];
        if (!empty($data['stripe_webhook_secret'])) $env['STRIPE_WEBHOOK_SECRET'] = $data['stripe_webhook_secret'];

        // PayPal
        if (!empty($data['paypal_mode']))       $env['PAYPAL_MODE']       = $data['paypal_mode'];
        if (!empty($data['paypal_client_id'])) $env['PAYPAL_CLIENT_ID']  = $data['paypal_client_id'];
        if (!empty($data['paypal_secret']))    $env['PAYPAL_SECRET']      = $data['paypal_secret'];
        if (!empty($data['paypal_webhook_id'])) $env['PAYPAL_WEBHOOK_ID'] = $data['paypal_webhook_id'];

        $this->writeToEnvironment($env);

        Notification::make()
            ->title('Billing settings saved')
            ->success()
            ->send();
    }
}
