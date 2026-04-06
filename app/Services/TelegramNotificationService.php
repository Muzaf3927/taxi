<?php

namespace App\Services;

class TelegramNotificationService
{
    public static function notifyPassenger(string $chatId, string $driverName, string $driverPhone, string $from, string $to)
    {
        $telegram = new TelegramService(env('TELEGRAM_PASSENGER_BOT_TOKEN'));
        $telegram->sendMessage((int) $chatId,
            "🚗 <b>Haydovchi topildi!</b>\n\n" .
            "👤 Haydovchi: <b>$driverName</b>\n" .
            "📱 Telefon: <b>$driverPhone</b>\n\n" .
            "📍 $from → $to\n\n" .
            "Ilovaga kirib batafsil ko'ring."
        );
    }

    public static function notifyDriverBooked(string $chatId, string $passengerName, string $passengerPhone, int $seats, string $amount, string $from, string $to)
    {
        $telegram = new TelegramService(env('TELEGRAM_DRIVER_BOT_TOKEN'));
        $telegram->sendMessage((int) $chatId,
            "🎉 <b>Yo'lovchi band qildi!</b>\n\n" .
            "👤 Yo'lovchi: <b>$passengerName</b>\n" .
            "📱 Telefon: <b>$passengerPhone</b>\n" .
            "💺 O'rinlar: <b>$seats</b>\n" .
            "💰 Narx: <b>$amount sum</b>\n\n" .
            "📍 $from → $to\n\n" .
            "Ilovaga kirib batafsil ko'ring."
        );
    }

    public static function notifyDriverOffered(string $chatId, string $passengerName, string $passengerPhone, int $seats, string $offeredPrice, string $from, string $to)
    {
        $telegram = new TelegramService(env('TELEGRAM_DRIVER_BOT_TOKEN'));
        $telegram->sendMessage((int) $chatId,
            "📩 <b>Yangi narx taklifi!</b>\n\n" .
            "👤 Yo'lovchi: <b>$passengerName</b>\n" .
            "📱 Telefon: <b>$passengerPhone</b>\n" .
            "💺 O'rinlar: <b>$seats</b>\n" .
            "💰 Taklif narxi: <b>$offeredPrice sum</b> (1 o'rin uchun)\n\n" .
            "📍 $from → $to\n\n" .
            "Ilovaga kirib qabul qiling yoki rad eting."
        );
    }
}
