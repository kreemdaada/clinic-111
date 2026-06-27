<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

/**
 * Local-dev helpers when mail is logged (log/array) and cannot reach fake inboxes.
 */
final class EmailVerificationSupport
{
    public static function mailCanDeliver(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array'], true);
    }

    public static function shouldAutoVerifyWithoutDelivery(): bool
    {
        if (self::mailCanDeliver()) {
            return false;
        }

        $configured = config('auth_security.email_verification.auto_verify_without_delivery');

        if ($configured !== null) {
            return filter_var($configured, FILTER_VALIDATE_BOOL);
        }

        return app()->environment('local');
    }

    public static function shouldExposeVerificationLink(): bool
    {
        if (self::mailCanDeliver()) {
            return false;
        }

        $configured = config('auth_security.email_verification.show_link_without_delivery');

        if ($configured !== null) {
            return filter_var($configured, FILTER_VALIDATE_BOOL);
        }

        return app()->environment('local');
    }

    public static function signedVerificationUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );
    }
}
