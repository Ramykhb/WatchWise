<?php

namespace App\Auth\Mail;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

/** Sends Laravel mailables through Brevo's transactional email API. */
class BrevoTransport extends AbstractTransport
{
    public function __construct(private readonly string $apiKey)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        if (trim($this->apiKey) === '') {
            throw new TransportException('Brevo is not configured. Set BREVO_API_KEY.');
        }

        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $address = static fn (Address $address): array => array_filter([
            'email' => $address->getAddress(),
            'name' => $address->getName(),
        ], static fn (string $value): bool => $value !== '');

        $payload = [
            'sender' => $address($email->getFrom()[0]),
            'to' => array_map($address, $email->getTo()),
            'subject' => $email->getSubject(),
        ];

        foreach (['cc' => $email->getCc(), 'bcc' => $email->getBcc()] as $key => $addresses) {
            if ($addresses !== []) {
                $payload[$key] = array_map($address, $addresses);
            }
        }
        if ($email->getReplyTo() !== []) {
            $payload['replyTo'] = $address($email->getReplyTo()[0]);
        }
        if ($email->getHtmlBody() !== null) {
            $payload['htmlContent'] = $email->getHtmlBody();
        }
        if ($email->getTextBody() !== null) {
            $payload['textContent'] = $email->getTextBody();
        }
        foreach ($email->getAttachments() as $attachment) {
            $payload['attachment'][] = [
                'name' => $attachment->getFilename(),
                'content' => str_replace("\r\n", '', $attachment->bodyToString()),
            ];
        }

        $response = Http::acceptJson()
            ->withHeaders(['api-key' => $this->apiKey])
            ->connectTimeout(5)
            ->timeout(15)
            ->post('https://api.brevo.com/v3/smtp/email', $payload);

        // Keep email bodies, signed links, and credentials out of transport errors.
        if (! $response->successful() || ! is_string($response->json('messageId'))) {
            throw new TransportException('Brevo rejected the email (HTTP '.$response->status().'). Check your API key, verified sender, and Brevo transactional logs.');
        }

        $message->setMessageId($response->json('messageId'));
    }

    public function __toString(): string
    {
        return 'brevo';
    }
}
