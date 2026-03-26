<?php

namespace Boy132\Billing\Filament\Admin\Resources\AuditLogs\Pages;

use Boy132\Billing\Filament\Admin\Resources\AuditLogs\AuditLogResource;
use Filament\Resources\Pages\ListRecords;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;
}
