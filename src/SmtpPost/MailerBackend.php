<?php

declare(strict_types=1);

namespace App\SmtpPost;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Symfony\Component\Mime\RawMessage;

use function array_map;
use function fseek;
use function is_resource;
use function is_string;
use function stream_get_contents;

class MailerBackend implements Backend
{
    public function __construct(private MailerInterface $mailer)
    {
    }

    public function send(Message $message): void
    {
        if (is_resource($data = $message->data)) {
            if (0 !== fseek($data, 0)) {
                throw new InvalidRequestException('Cannot seek to the beginning of the message data stream.');
            }

            if (false === $data = stream_get_contents($data)) {
                throw new InvalidRequestException('Cannot read the message data stream.');
            }
        }

        if (! is_string($data)) {
            throw new InvalidRequestException('The message data must be a string.');
        }

        $this->mailer->send(new RawMessage($data), $this->createEnvelope($message));
    }

    private function createEnvelope(Message $message): Envelope
    {
        try {
            return new Envelope(
                new Address($message->from),
                array_map(
                    static fn (string $address) => new Address($address),
                    $message->to,
                ),
            );
        } catch (RfcComplianceException $e) {
            throw new InvalidRequestException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
