<?php
namespace App\Domain\Ai;
use App\Models\AiProvider;
use App\Support\ApiError;
use Illuminate\Http\Client\{ConnectionException,RequestException};
use Illuminate\Support\Facades\Http;

class OpenAiCompatibleProvider implements AiProviderClient {
    private function base(AiProvider $provider): string {
        $url = rtrim($provider->base_url,'/');
        $parts = parse_url($url);
        if (!$parts || !isset($parts['host']) || (($parts['scheme'] ?? '') !== 'https' && !config('chat.ai_allow_private_hosts'))) throw new ApiError('AI_PROVIDER_ERROR',422,['reason'=>'Base URL must use HTTPS.']);
        $ips = gethostbynamel($parts['host']) ?: [];
        if (!config('chat.ai_allow_private_hosts')) foreach ($ips as $ip) if (!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) throw new ApiError('AI_PROVIDER_ERROR',422,['reason'=>'Private provider hosts are disabled.']);
        return $url;
    }
    private function request(AiProvider $provider) {
        $headers = array_merge($provider->extra_headers ?? [], ['Authorization'=>'Bearer '.$provider->api_key_encrypted,'User-Agent'=>'banana-chat/0.1.0']);
        return Http::withHeaders($headers)->acceptJson()->timeout($provider->timeout_seconds)->retry([2000,4000,8000],fn($e)=>$e instanceof ConnectionException || ($e instanceof RequestException && in_array($e->response?->status(),[429,500,502,503,504])));
    }
    public function complete(AiProvider $provider,array $messages): array {
        try {
            $response=$this->request($provider)->post($this->base($provider).'/chat/completions',['model'=>$provider->model,'messages'=>$messages,'stream'=>false,'temperature'=>(float)$provider->temperature,'max_tokens'=>$provider->max_output_tokens]);
            if (!$response->successful()) throw new ApiError($response->status()===408?'AI_PROVIDER_TIMEOUT':'AI_PROVIDER_ERROR',502,['provider_status'=>$response->status()]);
            $json=$response->json();$content=data_get($json,'choices.0.message.content');
            if (!is_string($content)) throw new ApiError('AI_PROVIDER_ERROR',502,['reason'=>'Provider returned no message.']);
            return ['content'=>$content,'model'=>$json['model']??$provider->model,'finish_reason'=>data_get($json,'choices.0.finish_reason','stop'),'tokens_prompt'=>(int)data_get($json,'usage.prompt_tokens',0),'tokens_completion'=>(int)data_get($json,'usage.completion_tokens',0)];
        } catch (ConnectionException) { throw new ApiError('AI_PROVIDER_TIMEOUT',504); }
    }
    public function listModels(AiProvider $provider): array {
        try {$response=$this->request($provider)->get($this->base($provider).'/models');if(!$response->successful())throw new ApiError('AI_PROVIDER_ERROR',502,['provider_status'=>$response->status()]);return collect($response->json('data',[]))->pluck('id')->filter()->values()->all();}
        catch(ConnectionException){throw new ApiError('AI_PROVIDER_TIMEOUT',504);}
    }
    public function testConnection(AiProvider $provider): array {
        $start=microtime(true);
        try {$models=$this->listModels($provider);return ['ok'=>true,'latency_ms'=>(int)((microtime(true)-$start)*1000),'models'=>$models];}
        catch(\Throwable $e){return ['ok'=>false,'latency_ms'=>(int)((microtime(true)-$start)*1000),'error'=>mb_substr($e->getMessage(),0,300)];}
    }
}
