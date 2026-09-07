<?php
namespace App\Filament\Resources\AiProviderResource\Pages;
class ManageAiProviders extends \Filament\Resources\Pages\ManageRecords {
    protected static string $resource=\App\Filament\Resources\AiProviderResource::class;
    protected function getHeaderActions():array{return [\Filament\Actions\CreateAction::make()->mutateFormDataUsing(fn(array $data)=>\App\Filament\Resources\AiProviderResource::mutateFormDataBeforeCreate($data))];}
}
