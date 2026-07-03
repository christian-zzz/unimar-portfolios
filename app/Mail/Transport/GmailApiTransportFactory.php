<?php

namespace App\Mail\Transport;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

class GmailApiTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $clientId = $dsn->getUser();
        $clientSecret = $dsn->getPassword();
        $refreshToken = $dsn->getOption('refresh_token');

        return new GmailApiTransport(
            $clientId,
            $clientSecret,
            $refreshToken,
            $this->dispatcher,
            $this->logger
        );
    }

    protected function getSupportedSchemes(): array
    {
        return ['gmail+api'];
    }
}
