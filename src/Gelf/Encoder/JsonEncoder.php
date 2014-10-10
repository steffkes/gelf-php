<?php

namespace Gelf\Encoder;

use Gelf\MessageInterface;

class JsonEncoder implements EncoderInterface
{
    public function encode(MessageInterface $message)
    {
        return json_encode($message->toArray());
    }
}
