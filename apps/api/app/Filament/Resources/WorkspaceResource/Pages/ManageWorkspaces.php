<?php
namespace App\Filament\Resources\WorkspaceResource\Pages;
class ManageWorkspaces extends \Filament\Resources\Pages\ManageRecords {
protected static string $resource=\App\Filament\Resources\WorkspaceResource::class;
protected function getHeaderActions():array{return [\Filament\Actions\CreateAction::make()];}
}
