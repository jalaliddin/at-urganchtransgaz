<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locale priority: authenticated user's preference → Accept-Language → default (uz).
 */
class SetLocale
{
    public const SUPPORTED = ['uz', 'ru'];

    public function handle(Request $request, Closure $next): Response
    {
        $userLocale = $request->user()?->getAttribute('locale');

        $locale = is_string($userLocale) && in_array($userLocale, self::SUPPORTED, true)
            ? $userLocale
            : ($this->fromAcceptLanguage($request) ?? config('app.locale'));

        app()->setLocale($locale);

        return $next($request);
    }

    private function fromAcceptLanguage(Request $request): ?string
    {
        foreach ($request->getLanguages() as $language) {
            $primary = strtolower((string) preg_split('/[-_]/', $language)[0]);
            if (in_array($primary, self::SUPPORTED, true)) {
                return $primary;
            }
        }

        return null;
    }
}
