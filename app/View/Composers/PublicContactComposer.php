<?php

namespace App\View\Composers;

use App\Support\Legal\LegalConfigPresenter;
use Illuminate\View\View;

class PublicContactComposer
{
    public function __construct(
        private readonly LegalConfigPresenter $legal,
    ) {}

    public function compose(View $view): void
    {
        $view->with([
            'publicContactEmail' => $this->legal->has('email')
                ? trim((string) config('legal.email'))
                : null,
            'publicContactMailto' => $this->legal->contactMailtoUrl(),
        ]);
    }
}
