<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\Legal\LegalConfigPresenter;
use Illuminate\View\View;

/**
 * Public legal pages (Impressum, Datenschutz).
 */
class LegalPageController extends Controller
{
    public function __construct(
        private readonly LegalConfigPresenter $legal,
    ) {}

    public function imprint(): View
    {
        return view('legal.imprint', [
            'legal' => $this->legal,
            'missingRequired' => ! $this->legal->hasRequiredImprintFields(),
        ]);
    }

    public function privacy(): View
    {
        return view('legal.privacy', [
            'legal' => $this->legal,
            'sessionCookie' => (string) config('session.cookie'),
            'sessionLifetime' => (int) config('session.lifetime'),
            'sessionDriver' => (string) config('session.driver'),
            'captchaEnabled' => (bool) config('auth_security.captcha.enabled'),
            'deleteUploadAfterImport' => (bool) config('accounting.upload.delete_after_import'),
        ]);
    }
}
