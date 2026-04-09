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
        $credentialsPath = env('FIREBASE_CREDENTIALS_PATH');
        if (!$credentialsPath || !file_exists(base_path($credentialsPath)) && !file_exists($credentialsPath)) {
            Log::info('Firebase credentials not configured, skipping FCM');
            return;
        }

        $fullPath = file_exists($credentialsPath) ? $credentialsPath : base_path($credentialsPath);
        $credentials = json_decode(file_get_contents($fullPath), true);
        $projectId = $credentials['project_id'] ?? null;

        if (!$projectId) {
            return;
        }

        $accessToken = self::getAccessToken($fullPath);
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

    private static function getAccessToken(string $credentialsPath): ?string
    {
        try {
            $client = new \Google\Auth\OAuth2([
                'issuer' => json_decode(file_get_contents($credentialsPath), true)['client_email'],
                'signingKey' => json_decode(file_get_contents($credentialsPath), true)['private_key'],
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
