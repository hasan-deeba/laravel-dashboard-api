<?php

namespace App\Notifications\Messages;

class FcmMessage
{
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
    ) {
    }
}
