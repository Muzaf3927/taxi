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
        $projectId = env('FIREBASE_PROJECT_ID');
        $clientEmail = env('FIREBASE_CLIENT_EMAIL');
        $privateKey = env('FIREBASE_PRIVATE_KEY');

        if (!$projectId || !$clientEmail || !$privateKey) {
            // Fallback to credentials file
            $path = env('FIREBASE_CREDENTIALS_PATH', 'storage/app/firebase-credentials.json');
            $fullPath = file_exists($path) ? $path : base_path($path);
            if (file_exists($fullPath)) {
                $creds = json_decode(file_get_contents($fullPath), true);
                $projectId = $creds['project_id'] ?? null;
                $clientEmail = $creds['client_email'] ?? null;
                $privateKey = $creds['private_key'] ?? null;
            }
        }

        if (!$projectId || !$clientEmail || !$privateKey) {
            return;
        }

        $accessToken = self::getAccessToken($clientEmail, $privateKey);
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

    private static function getAccessToken(string $clientEmail, string $privateKey): ?string
    {
        try {
            // Replace literal \n with actual newlines
            $privateKey = str_replace('\\n', "\n", $privateKey);

            $client = new \Google\Auth\OAuth2([
                'issuer' => $clientEmail,
                'signingKey' => $privateKey,
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
