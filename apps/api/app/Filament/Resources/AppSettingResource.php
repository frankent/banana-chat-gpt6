<?php
namespace App\Filament\Resources;
use App\Models\AppSetting;
use App\Support\Settings;
use Filament\{Forms,Tables};
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
class AppSettingResource extends Resource {
 protected static ?string $model=AppSetting::class;protected static ?string $navigationLabel='System settings';protected static ?string $navigationIcon='heroicon-o-cog-6-tooth';
 public static function form(Form $form):Form{return $form->schema([Forms\Components\TextInput::make('key')->disabled(),Forms\Components\Toggle::make('value')->visible(fn($record)=>is_bool(Settings::DEFAULTS[$record?->key]??null)),Forms\Components\TextInput::make('value')->numeric()->minValue(1)->maxValue(100000)->required()->visible(fn($record)=>!is_bool(Settings::DEFAULTS[$record?->key]??null)),Forms\Components\Hidden::make('updated_at')->dehydrateStateUsing(fn()=>now()),Forms\Components\Hidden::make('updated_by')->dehydrateStateUsing(fn()=>auth('admin')->id())]);}
 public static function table(Table $t):Table{return $t->columns([Tables\Columns\TextColumn::make('key')->searchable(),Tables\Columns\TextColumn::make('value')])->actions([Tables\Actions\EditAction::make()]);}
 public static function canCreate():bool{return false;}public static function canDelete($r):bool{return false;}
 public static function getPages():array{return ['index'=>AppSettingResource\Pages\ManageSettings::route('/')];}
}
