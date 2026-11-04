<?php

namespace App\Http\Middleware;

use App\Quotations\QuotationShareService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hashes the share token, removes it from the request URI used for logs,
 * and applies the public read or response rate limit.
 */
class GuardQuotationShare
{
    public function __construct(private readonly QuotationShareService $shares) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $mode = 'read'): Response
    {
        $token = (string) $request->route('token');
        $hash = hash('sha256', $token);
        $request->attributes->set('quotation_share_hash', $hash);
        $this->redact($request, $token);

        if ($this->limited($request, $hash, $mode)) {
            $this->shares->recordRateLimit($hash);

            return response()->view('errors.429', [], 429);
        }

        $response = $next($request);
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    private function limited(Request $request, string $hash, string $mode): bool
    {
        $respond = $mode === 'respond';
        $hashMax = $respond
            ? (int) config('asl.quotations.respond_per_hour', 5)
            : (int) config('asl.quotations.read_per_minute', 30);
        $ipMax = $respond
            ? (int) config('asl.quotations.respond_ip_per_minute', 10)
            : (int) config('asl.quotations.read_ip_per_minute', 60);
        $hashDecay = $respond ? 3600 : 60;
        $ipDecay = 60;
        $hashKey = 'quotation-share:'.$mode.':hash:'.$hash;
        $ipKey = 'quotation-share:'.$mode.':ip:'.($request->ip() ?? 'unknown');

        if (RateLimiter::tooManyAttempts($hashKey, $hashMax) || RateLimiter::tooManyAttempts($ipKey, $ipMax)) {
            return true;
        }

        RateLimiter::hit($hashKey, $hashDecay);
        RateLimiter::hit($ipKey, $ipDecay);

        return false;
    }

    private function redact(Request $request, string $token): void
    {
        if ($token === '') {
            return;
        }

        $path = $request->getPathInfo();
        $uri = $request->getRequestUri();
        $quoted = preg_quote($token, '#');
        $redactedPath = preg_replace('#'.$quoted.'#', '[redacted]', $path, 1) ?? $path;
        $redactedUri = preg_replace('#'.$quoted.'#', '[redacted]', $uri, 1) ?? $uri;

        $this->setRequestProperty($request, 'pathInfo', $redactedPath);
        $this->setRequestProperty($request, 'requestUri', $redactedUri);
    }

    private function setRequestProperty(Request $request, string $property, string $value): void
    {
        $reflection = new \ReflectionProperty($request, $property);
        $reflection->setValue($request, $value);
    }
}
