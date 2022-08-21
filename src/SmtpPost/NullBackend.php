<?php

declare(strict_types=1);

namespace App\SmtpPost;

class NullBackend implements Backend
{
    public function send(Message $message): void
    {
        dump($message);
    }
}
