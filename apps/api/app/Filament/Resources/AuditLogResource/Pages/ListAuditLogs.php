<?php
namespace App\Filament\Resources\AuditLogResource\Pages;
class ListAuditLogs extends \Filament\Resources\Pages\ManageRecords {
protected static string $resource=\App\Filament\Resources\AuditLogResource::class;
protected function getHeaderActions():array{return [];}
}
