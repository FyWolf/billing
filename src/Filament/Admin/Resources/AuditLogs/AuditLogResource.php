<?php

namespace Fywolf\Billing\Filament\Admin\Resources\AuditLogs;

use Fywolf\Billing\Filament\Admin\Resources\AuditLogs\Pages\ListAuditLogs;
use Fywolf\Billing\Models\AuditLog;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Audit Log';

    // Audit logs are read-only; no create/edit pages
    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable()
                    ->since(),
                TextColumn::make('action')
                    ->label('Event')
                    ->badge()
                    ->color(fn (string $state) => match (true) {
                        str_contains($state, 'failed')    => 'danger',
                        str_contains($state, 'expired')   => 'warning',
                        str_contains($state, 'activated') => 'success',
                        str_contains($state, 'created')   => 'success',
                        str_contains($state, 'closed')    => 'gray',
                        str_contains($state, 'denied')    => 'danger',
                        str_contains($state, 'refunded')  => 'info',
                        default                           => 'primary',
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order.id')
                    ->label('Order')
                    ->prefix('#')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('customer.first_name')
                    ->label('Customer')
                    ->formatStateUsing(fn ($state, AuditLog $record) => $record->customer?->getLabel() ?? '—')
                    ->placeholder('—'),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->placeholder('—'),
                TextColumn::make('metadata')
                    ->label('Details')
                    ->formatStateUsing(function ($state, AuditLog $record) {
                        $metadata = $record->metadata;
                        if (!$metadata || !is_array($metadata)) return '—';
                        $parts = [];
                        foreach ($metadata as $k => $v) {
                            if (is_scalar($v)) {
                                $parts[] = "{$k}: {$v}";
                            }
                        }
                        return implode(' · ', array_slice($parts, 0, 4));
                    })
                    ->wrap()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->options(function () {
                        return AuditLog::distinct('action')
                            ->pluck('action', 'action')
                            ->toArray();
                    }),
            ])
            ->emptyStateHeading('No audit events yet')
            ->emptyStateDescription('Events are recorded automatically when payments, orders, and servers are processed.')
            ->emptyStateIcon('tabler-shield-check');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
        ];
    }
}
