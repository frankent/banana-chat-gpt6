<?php
namespace App\Domain\Auth;
use App\Support\{ApiError,Settings};
class PasswordPolicy {
 public static function validate(string $password,string $username):void {
    $common=file_exists(resource_path('common-passwords.txt'))?file(resource_path('common-passwords.txt'),FILE_IGNORE_NEW_LINES):[];
    if(mb_strlen($password)<Settings::get('auth.password.min_length')||mb_strlen($password)>128||!preg_match('/\pL/u',$password)||!preg_match('/\pN/u',$password)||mb_strtolower($password)===mb_strtolower($username)||in_array(strtolower($password),$common,true))throw new ApiError('AUTH_PASSWORD_WEAK');
 }
}
