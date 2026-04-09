<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    public static function sendToUser($user, string $title, string $body, array $data = []): void
    {
        if (empty($user->fcm_token)) {
            return;
        }

        try {
            self::send($user->fcm_token, $title, $body, $data);
        } catch (\Throwable $e) {
            Log::warning('FCM send failed: ' . $e->getMessage());
        }
    }

    public static function send(string $fcmToken, string $title, string $body, array $data = []): void
    {
        $credentials = self::getCredentials();
        if (!$credentials) {
            return;
        }

        $projectId = $credentials['project_id'] ?? null;
        if (!$projectId) {
            return;
        }

        $accessToken = self::getAccessToken($credentials);
        if (!$accessToken) {
            return;
        }

        $message = [
            'message' => [
                'token' => $fcmToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => array_map('strval', $data),
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'sound' => 'default',
                        'channel_id' => 'taksi_channel',
                    ],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'badge' => 1,
                        ],
                    ],
                ],
            ],
        ];

        $response = Http::withToken($accessToken)
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $message);

        if ($response->failed()) {
            Log::warning('FCM send failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }

    private static function getCredentials(): ?array
    {
        // Try env variable first (base64 encoded JSON)
        $base64 = env('FIREBASE_CREDENTIALS_BASE64');
        if ($base64) {
            $json = base64_decode($base64);
            $credentials = json_decode($json, true);
            if ($credentials) {
                return $credentials;
            }
        }

        // Fallback to file
        $path = env('FIREBASE_CREDENTIALS_PATH', 'storage/app/firebase-credentials.json');
        $fullPath = file_exists($path) ? $path : base_path($path);
        if (file_exists($fullPath)) {
            return json_decode(file_get_contents($fullPath), true);
        }

        Log::info('Firebase credentials not configured, skipping FCM');
        return null;
    }

    private static function getAccessToken(array $credentials): ?string
    {
        try {
            $client = new \Google\Auth\OAuth2([
                'issuer' => $credentials['client_email'],
                'signingKey' => $credentials['private_key'],
                'signingAlgorithm' => 'RS256',
                'tokenCredentialUri' => 'https://oauth2.googleapis.com/token',
                'audience' => 'https://oauth2.googleapis.com/token',
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            ]);

            $client->fetchAuthToken();
            return $client->getLastReceivedToken()['access_token'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('FCM auth failed: ' . $e->getMessage());
            return null;
        }
    }
}
