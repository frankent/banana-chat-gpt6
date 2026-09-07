<?php
namespace Tests\Unit;
use App\Domain\Auth\PasswordPolicy;
use App\Support\ApiError;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase {
    public static function weak():array{return [['short1','tony'],['onlylettersxx','tony'],['12345678901','tony'],['Tony1','tony'],['password1','tony']];}
    #[DataProvider('weak')]
    public function test_TC_AUTH_020_023_weak_passwords_are_rejected(string $password,string $username):void{$this->expectException(ApiError::class);PasswordPolicy::validate($password,$username);}
    public function test_TC_AUTH_023_unicode_password_is_supported():void{PasswordPolicy::validate('กล้วยแชท2026','tony');$this->assertTrue(true);}
}
