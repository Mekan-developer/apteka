<?php

namespace App\Http\Controllers;

use App\Http\Requests\Locale\UpdateLocaleRequest;
use Illuminate\Http\RedirectResponse;

class LocaleController extends Controller
{
    /**
     * Switch the interface language for the current session.
     */
    public function __invoke(UpdateLocaleRequest $request): RedirectResponse
    {
        $locale = $request->validated('locale');

        $request->session()->put('locale', $locale);

        notify('success', __('messages.locale_updated', locale: $locale));

        return back();
    }
}
