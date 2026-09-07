<?php
namespace App\Filament\Pages\Auth;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Component;
class Login extends \Filament\Pages\Auth\Login {
 protected function getEmailFormComponent():Component{return TextInput::make('username')->label('Username')->required()->autofocus()->autocomplete('username');}
 protected function getCredentialsFromFormData(array $data):array{return ['username'=>strtolower(trim($data['username'])),'password'=>$data['password'],'status'=>'active','is_system_admin'=>true,'must_change_password'=>false];}
 protected function throwFailureValidationException():never{throw \Illuminate\Validation\ValidationException::withMessages(['data.username'=>__('filament-panels::pages/auth/login.messages.failed')]);}
}
