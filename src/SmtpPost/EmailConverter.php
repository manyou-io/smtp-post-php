<?php

declare(strict_types=1);

namespace App\SmtpPost;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\MailboxHeader;
use Symfony\Component\Mime\Header\MailboxListHeader;
use Symfony\Component\Mime\Header\ParameterizedHeader;
use Symfony\Component\Mime\Header\PathHeader;
use Symfony\Component\Mime\Message as MimeMessage;
use Symfony\Component\Mime\Part\AbstractMultipartPart;
use Symfony\Component\Mime\Part\AbstractPart;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\AlternativePart;
use Symfony\Component\Mime\Part\Multipart\DigestPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Mime\Part\Multipart\MixedPart;
use Symfony\Component\Mime\Part\Multipart\RelatedPart;
use Symfony\Component\Mime\Part\TextPart;
use ZBateson\MailMimeParser\Header\AddressHeader;
use ZBateson\MailMimeParser\Header\DateHeader;
use ZBateson\MailMimeParser\Header\GenericHeader;
use ZBateson\MailMimeParser\Header\IdHeader;
use ZBateson\MailMimeParser\Header\IHeader;
use ZBateson\MailMimeParser\Header\ParameterHeader;
use ZBateson\MailMimeParser\Header\Part\ParameterPart;
use ZBateson\MailMimeParser\Header\ReceivedHeader;
use ZBateson\MailMimeParser\Header\SubjectHeader;
use ZBateson\MailMimeParser\IMessage;
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Message\IMessagePart;
use ZBateson\MailMimeParser\Message\IMultiPart;

use function in_array;
use function is_string;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;

/** @experimental */
class EmailConverter
{
    private function convertAddressHeader(AddressHeader $header): MailboxListHeader|PathHeader|MailboxHeader
    {
        $name = strtolower($header->getName());

        if ('return-path' === $name) {
            return new PathHeader($header->getName(), new Address($header->getEmail(), $header->getPersonName()));
        }

        if ('sender' === $name) {
            return new MailboxHeader($header->getName(), new Address($header->getEmail(), $header->getPersonName()));
        }

        $addresses = [];

        foreach ($header->getAddresses() as $address) {
            $addresses[] = new Address($address->getEmail(), $address->getName());
        }

        return new MailboxListHeader($header->getName(), $addresses);
    }

    private function convertParameterHeader(ParameterHeader $header): ParameterizedHeader
    {
        $value  = '';
        $params = [];

        foreach ($header->getParts() as $part) {
            if ($part instanceof ParameterPart) {
                $params[$part->getName()] = $part->getValue();
            } else {
                $value = $part->getValue();
            }
        }

        return new ParameterizedHeader($header->getName(), $value, $params);
    }

    private function convertPart(IMessagePart $part): AbstractPart
    {
        $contentType = $part->getContentType('application/octet-stream');

        if ($part instanceof IMultiPart && str_starts_with($contentType, 'multipart/')) {
            $parts = [];

            foreach ($part->getChildParts() as $child) {
                $parts[] = $this->convertPart($child);
            }

            if ($contentType === 'multipart/mixed') {
                return new MixedPart(...$parts);
            }

            if ($contentType === 'multipart/digest') {
                return new DigestPart(...$parts);
            }

            if ($contentType === 'multipart/alternative') {
                return new AlternativePart(...$parts);
            }

            if ($contentType === 'multipart/related') {
                return new RelatedPart(...$parts);
            }

            if ($contentType === 'multipart/form-data') {
                return new FormDataPart(...$parts);
            }

            return new class (substr($contentType, strlen('multipart/')), ...$parts) extends AbstractMultipartPart {
                public function __construct(private string $subtype, AbstractPart ...$parts)
                {
                    parent::__construct(...$parts);
                }

                public function getMediaSubtype(): string
                {
                    return $this->subtype;
                }
            };
        }

        if (str_starts_with($contentType, 'text/')) {
            $charset  = $part->getCharset() ?? MailMimeParser::DEFAULT_CHARSET;
            $encoding = $part->getContentTransferEncoding();
            if (! in_array($encoding, ['quoted-printable', 'base64', '8bit'], true)) {
                $encoding = null;
            }

            return new TextPart(
                $part->getContent($charset),
                strtolower($charset),
                substr($contentType, strlen('text/')),
                $encoding,
            );
        }

        return new DataPart($part->getBinaryContentResourceHandle(), $part->getFilename(), $contentType, $part->getContentTransferEncoding());
    }

    private function convertHeaders(IHeader ...$iHeaders): Headers
    {
        $headers = new Headers();

        foreach ($iHeaders as $header) {
            $name = strtolower($header->getName());
            if ($name === 'in-reply-to') {
                $headers->addTextHeader($header->getName(), $header->getRawValue());
            } elseif ($header instanceof AddressHeader) {
                $headers->add($this->convertAddressHeader($header));
            } elseif ($header instanceof DateHeader) {
                $headers->addDateHeader($header->getName(), $header->getDateTimeImmutable());
            } elseif ($header instanceof GenericHeader) {
                $headers->addTextHeader($header->getName(), $header->getValue());
            } elseif ($header instanceof IdHeader) {
                $headers->addIdHeader($header->getName(), $header->getIds());
            } elseif ($header instanceof ParameterHeader && ! $header instanceof ReceivedHeader) {
                $headers->add($this->convertParameterHeader($header));
            } elseif ($header instanceof SubjectHeader) {
                $headers->addTextHeader($header->getName(), $header->getValue());
            }
        }

        return $headers;
    }

    private function convertMessage(IMessage $message): MimeMessage
    {
        return new MimeMessage(
            $this->convertHeaders(...$message->getAllHeaders()),
            $this->convertPart($message),
        );
    }

    public function __invoke(Message|string $message): MimeMessage
    {
        $message = $message instanceof Message ? $message->data : $message;
        $message = $this->parser->parse($message, is_string($message));

        return $this->convertMessage($message);
    }

    public function __construct(private MailMimeParser $parser)
    {
    }
}
