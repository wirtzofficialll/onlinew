<?php

namespace App\Mail;

use Illuminate\Mail\Transport\Transport;
use Illuminate\Support\Facades\Http;
use Swift_Mime_SimpleMessage;

class ResendTransport extends Transport
{
    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $this->beforeSendPerformed($message);

        $response = Http::withToken(env('RESEND_API_KEY'))
            ->post('https://api.resend.com/emails', [
                'from' => 'noreply@your-verified-domain.com', // MUST BE VERIFIED DOMAIN
                'to' => array_keys($message->getTo()),
                'subject' => $message->getSubject(),
                'html' => $message->getBody(),
            ]);

        $this->sendPerformed($message);

        return $this->numberOfRecipients($message);
    }
}