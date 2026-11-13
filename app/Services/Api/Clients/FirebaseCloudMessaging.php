<?php

namespace App\Services\Api\Clients;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FirebaseCloudMessaging
{
    public function send(string $deviceToken, string $title, string $body, array $data = []): void
    {
        $this->sendMessage(['token' => $deviceToken], $title, $body, $data);
    }

    private function sendMessage(array $target, string $title, string $body, array $data): void
    {
        $credentials = $this->credentials();
        $projectId = $credentials['project_id'] ?? null;
        if (!is_string($projectId) || $projectId === '') {
            throw new RuntimeException('Firebase service account is missing project_id.');
        }

        $response = Http::acceptJson()
            ->withToken($this->accessToken($credentials))
            ->timeout(15)
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => array_merge($target, [
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => array_map(
                        fn ($value) => is_scalar($value) ? (string) $value : json_encode($value),
                        $data,
                    ),
                ]),
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Firebase Cloud Messaging rejected the send request (HTTP '.$response->status().').');
        }
    }

    private function credentials(): array
    {
        $path = config('firebase.credentials');
        if (!is_string($path) || $path === '' || !is_readable($path)) {
            throw new RuntimeException('Firebase credentials are not configured.');
        }

        $credentials = json_decode(file_get_contents($path), true);
        if (!is_array($credentials) || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            throw new RuntimeException('Firebase service-account credentials are invalid.');
        }

        return $credentials;
    }

    private function accessToken(array $credentials): string
    {
        $cacheKey = 'firebase.messaging.access_token.'.sha1($credentials['client_email']);
        if ($token = Cache::get($cacheKey)) {
            return $token;
        }

        $issuedAt = time();
        $claims = [
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            'iat' => $issuedAt,
            'exp' => $issuedAt + 3600,
        ];
        $unsignedJwt = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
            .'.'.$this->base64UrlEncode(json_encode($claims));
        $privateKey = openssl_pkey_get_private($credentials['private_key']);
        if ($privateKey === false || !openssl_sign($unsignedJwt, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign Firebase service-account assertion.');
        }

        $response = Http::asForm()->acceptJson()->timeout(15)->post($claims['aud'], [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $unsignedJwt.'.'.$this->base64UrlEncode($signature),
        ]);
        if ($response->failed() || !is_string($response->json('access_token'))) {
            throw new RuntimeException('Unable to authenticate with Firebase (HTTP '.$response->status().').');
        }

        $token = $response->json('access_token');
        Cache::put($cacheKey, $token, max(60, (int) $response->json('expires_in', 3600) - 60));

        return $token;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
