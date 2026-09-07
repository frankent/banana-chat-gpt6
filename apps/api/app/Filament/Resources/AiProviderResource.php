<?php
namespace App\Filament\Resources;
use App\Domain\Ai\AiProviderClient;
use App\Models\AiProvider;
use Filament\{Forms,Tables,Notifications\Notification};
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;

class AiProviderResource extends Resource {
    protected static ?string $model=AiProvider::class;
    protected static ?string $navigationIcon='heroicon-o-sparkles';
    protected static ?string $navigationLabel='AI providers';
    protected static ?int $navigationSort=5;
    public static function form(Form $form):Form{return $form->schema([
        Forms\Components\TextInput::make('name')->required()->maxLength(60),
        Forms\Components\Select::make('provider_type')->options(['openai_compatible'=>'OpenAI compatible'])->default('openai_compatible')->required(),
        Forms\Components\TextInput::make('base_url')->url()->required()->maxLength(255)->dehydrateStateUsing(fn($state)=>preg_replace('#/(chat/completions)?$#','',rtrim($state,'/'))),
        Forms\Components\TextInput::make('api_key_encrypted')->label('API key')->password()->revealable()->afterStateHydrated(fn($component)=>$component->state(''))->required(fn(string $operation)=>$operation==='create')->dehydrated(fn($state)=>filled($state)),
        Forms\Components\TextInput::make('model')->required()->maxLength(100),
        Forms\Components\Select::make('model_source')->options(['list'=>'Provider list','custom'=>'Custom'])->default('custom')->required(),
        Forms\Components\TextInput::make('window_size')->numeric()->minValue(4096)->maxValue(10000000)->default(200000)->required(),
        Forms\Components\TextInput::make('max_output_tokens')->numeric()->minValue(256)->maxValue(131072)->default(4096)->required(),
        Forms\Components\TextInput::make('temperature')->numeric()->minValue(0)->maxValue(2)->default(.7)->required(),
        Forms\Components\Textarea::make('system_prompt')->maxLength(4000)->rows(5),
        Forms\Components\TextInput::make('memory_model')->maxLength(100),
        Forms\Components\TextInput::make('timeout_seconds')->numeric()->minValue(10)->maxValue(300)->default(60)->required(),
        Forms\Components\Select::make('allowed_workspace_ids')->options(fn()=>\App\Models\Workspace::orderBy('name')->pluck('name','id'))->multiple()->searchable()->preload()->helperText('Empty allows every workspace.'),
        Forms\Components\TextInput::make('daily_message_limit_per_user')->numeric()->minValue(1),
        Forms\Components\Toggle::make('is_enabled')->default(true),Forms\Components\Toggle::make('is_default')->default(false),
    ])->columns(2);}
    public static function table(Table $table):Table{return $table->columns([Tables\Columns\TextColumn::make('name')->searchable(),Tables\Columns\TextColumn::make('model')->searchable(),Tables\Columns\IconColumn::make('is_enabled')->boolean(),Tables\Columns\IconColumn::make('is_default')->boolean(),Tables\Columns\TextColumn::make('api_key_last4')->formatStateUsing(fn($state)=>$state?'••••'.$state:'')])->actions([Tables\Actions\Action::make('test')->icon('heroicon-o-signal')->action(function(AiProvider $record){$result=app(AiProviderClient::class)->testConnection($record);$record->update(['last_tested_at'=>now(),'last_test_status'=>$result]);Notification::make()->title($result['ok']?'Connection successful':'Connection failed')->body($result['error']??($result['latency_ms'].' ms'))->color($result['ok']?'success':'danger')->send();}),Tables\Actions\EditAction::make()]);}
    public static function mutateFormDataBeforeCreate(array $data):array{$data['created_by']=auth('admin')->id();$data['updated_by']=auth('admin')->id();return $data;}
    public static function getPages():array{return ['index'=>AiProviderResource\Pages\ManageAiProviders::route('/')];}
}
