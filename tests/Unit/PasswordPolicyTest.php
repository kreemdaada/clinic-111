<?php

namespace Tests\Unit;

use App\Support\SecurePassword;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    /**
     * @return array<string, list<string>>
     */
    private function passwordRules(): array
    {
        return [
            'password' => ['required', 'string', SecurePassword::rule()],
        ];
    }

    public function test_strong_password_passes_validation(): void
    {
        $validator = Validator::make([
            'password' => SecurePassword::example(),
        ], $this->passwordRules());

        $this->assertFalse($validator->fails());
    }

    public function test_short_password_is_rejected(): void
    {
        $validator = Validator::make([
            'password' => 'Short1!',
        ], $this->passwordRules());

        $this->assertTrue($validator->fails());
    }

    public function test_password_without_uppercase_is_rejected(): void
    {
        $validator = Validator::make([
            'password' => 'securepass1!',
        ], $this->passwordRules());

        $this->assertTrue($validator->fails());
    }

    public function test_password_without_lowercase_is_rejected(): void
    {
        $validator = Validator::make([
            'password' => 'SECUREPASS1!',
        ], $this->passwordRules());

        $this->assertTrue($validator->fails());
    }

    public function test_password_without_number_is_rejected(): void
    {
        $validator = Validator::make([
            'password' => 'SecurePassWord!',
        ], $this->passwordRules());

        $this->assertTrue($validator->fails());
    }

    public function test_password_without_special_character_is_rejected(): void
    {
        $validator = Validator::make([
            'password' => 'SecurePass1234',
        ], $this->passwordRules());

        $this->assertTrue($validator->fails());
    }
}
