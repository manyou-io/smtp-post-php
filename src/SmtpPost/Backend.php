<?php

declare(strict_types=1);

namespace App\SmtpPost;

interface Backend
{
    public function send(Message $message): void;
}
