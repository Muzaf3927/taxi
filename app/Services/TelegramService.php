<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TelegramService
{
    protected string $token;
    protected string $baseUrl;

    public function __construct(string $token)
    {
        $this->token = $token;
        $this->baseUrl = "https://api.telegram.org/bot{$this->token}";
    }

    public function sendMessage(int $chatId, string $text, array $replyMarkup = [])
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if (!empty($replyMarkup)) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }

        return Http::post("{$this->baseUrl}/sendMessage", $params);
    }

    public function requestContact(int $chatId, string $text)
    {
        return $this->sendMessage($chatId, $text, [
            'keyboard' => [
                [['text' => '📱 Telefon raqamni yuborish', 'request_contact' => true]],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ]);
    }

    public function removeKeyboard(int $chatId, string $text)
    {
        return $this->sendMessage($chatId, $text, [
            'remove_keyboard' => true,
        ]);
    }
}
