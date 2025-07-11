<?php

namespace Errly\LaravelErrly\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ErrorContextService
{
    public function gather(): array
    {
        $context = [
            'timestamp' => now()->toISOString(),
            'environment' => app()->environment(),
        ];

        if (config('errly.notifications.include_server_info')) {
            $context['server'] = gethostname();
        }

        if (config('errly.context.include_user') && Auth::check()) {
            $context['user'] = $this->getUserContext();
        }

        if (config('errly.context.include_request') && request()) {
            $context['request'] = $this->getRequestContext(request());
        }

        return $context;
    }

    protected function getUserContext(): array
    {
        $user = Auth::user();

        return array_filter([
            'id' => $user?->getAuthIdentifier(),
            'email' => $this->getUserEmail($user),
            'name' => $this->getUserName($user),
        ]);
    }

    protected function getUserEmail($user): ?string
    {
        if (! $user) {
            return null;
        }

        // Try different methods to get email
        if (isset($user->email)) {
            return $user->email;
        }

        if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail) {
            return $user->getEmailForVerification();
        }

        return null;
    }

    protected function getUserName($user): ?string
    {
        if (! $user) {
            return null;
        }

        return $user->name ?? $user->username ?? null;
    }

    protected function getRequestContext(Request $request): array
    {
        $context = [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        if (config('errly.context.include_headers')) {
            $context['headers'] = $this->getSafeHeaders($request);
        }

        $context['input'] = $this->getSafeInput($request);

        return $context;
    }

    protected function getSafeHeaders(Request $request): array
    {
        $headers = $request->headers->all();

        $sensitiveHeaders = ['authorization', 'cookie', 'x-api-key', 'x-auth-token'];

        foreach ($sensitiveHeaders as $header) {
            unset($headers[$header]);
        }

        return $headers;
    }

    protected function getSafeInput(Request $request): array
    {
        $input = $request->all();
        $sensitiveFields = config('errly.context.sensitive_fields', []);

        foreach ($sensitiveFields as $field) {
            if (isset($input[$field])) {
                $input[$field] = '[REDACTED]';
            }
        }

        return $input;
    }
}
