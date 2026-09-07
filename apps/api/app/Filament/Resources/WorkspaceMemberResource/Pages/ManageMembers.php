<?php
namespace App\Filament\Resources\WorkspaceMemberResource\Pages;
class ManageMembers extends \Filament\Resources\Pages\ManageRecords {
protected static string $resource=\App\Filament\Resources\WorkspaceMemberResource::class;
protected function getHeaderActions():array{return [\Filament\Actions\CreateAction::make()];}
}
