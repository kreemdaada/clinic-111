<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateUserLocaleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Authenticated user language preference (foundation for en/de/ar).
 */
class UserLocaleController extends Controller
{
    public function edit(): View
    {
        return view('settings.language');
    }

    public function update(UpdateUserLocaleRequest $request): RedirectResponse
    {
        $locale = (string) $request->validated('locale');

        $request->user()?->update(['locale' => $locale]);
        $request->session()->put('locale', $locale);

        return redirect()
            ->back(fallback: route('settings.language.edit'))
            ->with('status', __('settings.language.saved'));
    }
}
