<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $this->forwardNotification($request);

        $locale = App::getLocale();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'locale' => $locale,
            'availableLocales' => config('app.available_locales'),
            'translations' => Inertia::once(fn (): array => $this->translations($locale))
                ->as("translations.{$locale}"),
        ];
    }

    /**
     * Pass the session "notify" flash (set via the notify() helper) to Inertia flash data,
     * so the frontend shows it exactly once and it never lands in browser history.
     */
    protected function forwardNotification(Request $request): void
    {
        $notification = $request->session()->get('notify');

        if (is_array($notification)) {
            Inertia::flash('notify', $notification);
        }
    }

    /**
     * Load the frontend translations from lang/{locale}.json.
     *
     * @return array<string, string>
     */
    protected function translations(string $locale): array
    {
        $path = lang_path("{$locale}.json");

        if (! is_file($path)) {
            return [];
        }

        return File::json($path, JSON_THROW_ON_ERROR);
    }
}
