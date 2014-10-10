<?php

namespace Gelf\Transport;

use Gelf\Encoder\EncoderInterface;
use Gelf\MessageInterface;
use Gelf\PublisherInterface;

abstract class AbstractTransport implements TransportInterface, PublisherInterface
{
    /**
     * Sets a message encoder
     *
     * @param EncoderInterface $encoder
     */
    public function setMessageEncoder(EncoderInterface $encoder)
    {
        $this->messageEncoder = $encoder;
        return $this;
    }

    /**
     * Returns the current message encoder
     *
     * @return EncoderInterface
     */
    public function getMessageEncoder()
    {
        return $this->messageEncoder;
    }

    /**
     * Sends a Message over this transport
     *
     * @param Message $message
     * @return int the number of UDP packets sent
     */
    abstract public function send(MessageInterface $message);

    public function publish(MessageInterface $message)
    {
      return $this->send($message);
    }
}