<?php

namespace App\Mail\Transport;

use Google\Client as GoogleClient;
use Google\Service\Gmail as GoogleGmail;
use Google\Service\Gmail\Message as GoogleMessage;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Message;

class GmailApiTransport extends AbstractTransport
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $refreshToken;

    public function __construct(
        string $clientId,
        string $clientSecret,
        string $refreshToken,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($dispatcher, $logger);

        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->refreshToken = $refreshToken;
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();

        if (!$email instanceof Message) {
            throw new \InvalidArgumentException('Message must be an instance of Symfony\Component\Mime\Message');
        }

        // Get the raw RFC 2822 email string
        $rawMessageString = $email->toString();

        // Encode using base64url (RFC 4648 section 5)
        $safeRawMessage = strtr(base64_encode($rawMessageString), '+/', '-_');
        $safeRawMessage = rtrim($safeRawMessage, '=');

        // Setup the Google Client
        $client = new GoogleClient();
        $client->setClientId($this->clientId);
        $client->setClientSecret($this->clientSecret);
        $client->setScopes([GoogleGmail::GMAIL_SEND]);
        $client->setAccessType('offline');

        // Refresh the access token using the refresh token
        $client->refreshToken($this->refreshToken);

        // Send message using Google Gmail service
        $gmailService = new GoogleGmail($client);
        $gmailMessage = new GoogleMessage();
        $gmailMessage->setRaw($safeRawMessage);

        $gmailService->users_messages->send('me', $gmailMessage);
    }

    public function __toString(): string
    {
        return 'gmail+api';
    }
}
