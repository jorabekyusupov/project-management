<?php

namespace App\Library\Bot;

use Illuminate\Support\Facades\Http;

class InfoBot
{
    private $client;

    private string $configKey = 'services.telegram.logger_bot';


    public function __construct()
    {
        $token = '7832842040:AAGmxHwmRrqpEzhboFKuZSTUWnrOFdOQIRU';

        $this->client = Http::baseUrl("https://api.telegram.org/bot{$token}");
    }

    public function send(string $chat_id, string $text, string $threadId = null): \Illuminate\Http\Client\Response
    {
        return $this->client
            ->post('/sendMessage', [
                'chat_id' => $chat_id,
                'text' => mb_strcut($text, 0, 4096),
                'disable_web_page_preview' => true,
                'parse_mode' => 'HTML',
                'disable_notification' => true,
                'message_thread_id' => $threadId,
            ]);
    }


}