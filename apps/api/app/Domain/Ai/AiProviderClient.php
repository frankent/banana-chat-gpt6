<?php
namespace App\Domain\Ai;
use App\Models\AiProvider;

interface AiProviderClient {
    public function complete(AiProvider $provider, array $messages): array;
    public function listModels(AiProvider $provider): array;
    public function testConnection(AiProvider $provider): array;
}
