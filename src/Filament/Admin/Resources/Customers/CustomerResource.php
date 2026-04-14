<?php

namespace Fywolf\Billing\Filament\Admin\Resources\Customers;

use App\Models\User;
use Fywolf\Billing\Filament\Admin\Resources\Customers\Pages\CreateCustomer;
use Fywolf\Billing\Filament\Admin\Resources\Customers\Pages\EditCustomer;
use Fywolf\Billing\Filament\Admin\Resources\Customers\Pages\ListCustomers;
use Fywolf\Billing\Models\Customer;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use NumberFormatter;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-user-dollar';

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count() ?: null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account')
                    ->columns(2)
                    ->schema([
                        Select::make('user_id')
                            ->prefixIcon('tabler-user')
                            ->label('User')
                            ->required()
                            ->selectablePlaceholder(false)
                            ->relationship('user', 'username')
                            ->searchable(['username', 'email'])
                            ->getOptionLabelFromRecordUsing(fn (User $user) => $user->email . ' | ' . $user->username)
                            ->preload()
                            ->columnSpanFull(),
                        TextInput::make('first_name')
                            ->required(),
                        TextInput::make('last_name')
                            ->required(),
                        TextInput::make('balance')
                            ->required()
                            ->suffix(config('billing.currency'))
                            ->numeric()
                            ->minValue(0)
                            ->columnSpanFull(),
                    ]),

                Section::make('Billing Address')
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

    public static function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->columns([
                TextColumn::make('first_name')
                    ->sortable(),
                TextColumn::make('last_name')
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('E-Mail')
                    ->sortable(),
                TextColumn::make('balance')
                    ->numeric()
                    ->formatStateUsing(function ($state) {
                        $formatter = new NumberFormatter(auth()->user()->language, NumberFormatter::CURRENCY);

                        return $formatter->formatCurrency($state, config('billing.currency'));
                    }),
                TextColumn::make('orders_count')
                    ->label('Orders')
                    ->counts('orders')
                    ->badge(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No Customers')
            ->emptyStateDescription('')
            ->emptyStateIcon('tabler-user-dollar');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
