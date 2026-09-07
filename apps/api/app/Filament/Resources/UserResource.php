<?php
namespace App\Filament\Resources;
use App\Models\User;
use Filament\{Forms,Tables};
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Hash;
use App\Domain\Auth\PasswordPolicy;
class UserResource extends Resource {
 protected static ?string $model=User::class;protected static ?string $navigationIcon='heroicon-o-users';protected static ?int $navigationSort=1;
 public static function form(Form $form):Form{return $form->schema([
  Forms\Components\TextInput::make('username')->required()->minLength(3)->maxLength(32)->regex('/^[a-z0-9._-]+$/')->unique(ignoreRecord:true),
  Forms\Components\TextInput::make('display_name')->required()->maxLength(80),
  Forms\Components\TextInput::make('password_hash')->label('Temporary password')->password()->revealable()->afterStateHydrated(fn($component)=>$component->state(''))->required(fn(string $operation)=>$operation==='create')->minLength(10)->maxLength(128)->dehydrated(fn($state)=>filled($state))->dehydrateStateUsing(function($state,Forms\Get $get){PasswordPolicy::validate($state,$get('username'));return Hash::make($state);})->helperText('Share this password securely. It is never stored in plain text.'),
  Forms\Components\Toggle::make('must_change_password')->default(true)->label('Require password change'),Forms\Components\Toggle::make('is_system_admin')->label('System administrator'),
  Forms\Components\Select::make('status')->options(['active'=>'Active','suspended'=>'Suspended','deactivated'=>'Deactivated'])->default('active')->required(),
  Forms\Components\Select::make('locale')->options(['th'=>'ไทย','en'=>'English'])->default('th')->required(),Forms\Components\TextInput::make('timezone')->default('Asia/Bangkok')->required()->rules(['timezone'])]);}
 public static function table(Table $table):Table{return $table->columns([Tables\Columns\TextColumn::make('username')->searchable()->sortable(),Tables\Columns\TextColumn::make('display_name')->searchable(),Tables\Columns\TextColumn::make('status')->badge(),Tables\Columns\IconColumn::make('is_system_admin')->boolean(),Tables\Columns\TextColumn::make('created_at')->dateTime()])->actions([Tables\Actions\EditAction::make(),Tables\Actions\Action::make('unlock')->requiresConfirmation()->action(fn(User $record)=>$record->update(['locked_until'=>null,'failed_login_count'=>0,'failed_login_at'=>null]))])->filters([Tables\Filters\SelectFilter::make('status')->options(['active'=>'Active','suspended'=>'Suspended','deactivated'=>'Deactivated'])]);}
 public static function getPages():array{return ['index'=>UserResource\Pages\ManageUsers::route('/')];}
 public static function canDelete($record):bool{return false;}
}
