<?php

declare(strict_types=1);

namespace App\SmtpPost;

class Message
{
    public function __construct(
        public string $from,
        /** @var string[] */
        public array $to,
        /** @var string|resource */
        public $data,
    ) {
    }
}
