<?php

/*
 * This file is part of the php-gelf package.
 *
 * (c) Benjamin Zikarsky <http://benjamin-zikarsky.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gelf\Transport;

use Gelf\MessageInterface;
use Gelf\Encoder\CompressedJsonEncoder;
use Gelf\Encoder\JsonEncoder as DefaultEncoder;

use RuntimeException;

/**
 * HttpTransport allows the transfer of GELF-messages to an compatible 
 * GELF-HTTP-backend as described in 
 * http://www.graylog2.org/resources/documentation/sending/gelfhttp
 *
 * It can also act as a direct publisher
 *
 * @author Benjamin Zikarsky <benjamin@zikarsky.de>
 */
class HttpTransport extends AbstractTransport
{
    /**
     * @var string
     */
    protected $host = "127.0.0.1";

    /**
     * @var int
     */
    protected $port = 12202;

    /**
     * @var string
     */
    protected $path = "/gelf";

    /**
     * @var StreamSocketClient
     */
    protected $socketClient;

    /**
     * Class constructor
     *
     * @param string $host      when NULL or empty default-host is used
     * @param int $port         when NULL or empty default-port is used
     * @param string $path      when NULL or empty default-path is used
     */
    public function __construct($host = null, $port = null, $path = null)
    {
        $this->host = $host ?: $this->host;
        $this->port = $port ?: $this->port;
        $this->path = $path ?: $this->path;

        $this->socketClient = new StreamSocketClient("tcp", $this->host, $this->port);
        $this->messageEncoder = new DefaultEncoder();
    }

    /**
     * Sends a Message over this transport
     *
     * @param Message $message
     *
     * @return int the number of bytes sent
     */
    public function send(MessageInterface $message)
    {
        $messageEncoder = $this->getMessageEncoder();
        $rawMessage = $messageEncoder->encode($message);

        $request = array(
            sprintf("POST %s HTTP/1.0", $this->path),
            sprintf("Host: %s:%d", $this->host, $this->port),
            sprintf("Content-Length: %d", strlen($rawMessage)),
            "Content-Type: application/json",
            "Connection: Close",
            "Accept: */*"
        );

        if($messageEncoder instanceof CompressedJsonEncoder) {
            $request[] = "Content-Encoding: gzip";
        }

        $request[] = ""; // blank line to separate headers from body
        $request[] = $rawMessage;

        $request = implode($request, "\r\n");

        $byteCount = $this->socketClient->write($request);
        $content = $this->socketClient->read($byteCount);

        $expected = "HTTP/1.1 202 Accepted";
        if(strpos($content, $expected) !== 0) {
            throw new RuntimeException(
                sprintf(
                    "Graylog-Server didn't answer properly, expected '%s' got '%s'",
                    $expected,
                    trim($content)
                )
            );
        }

        return $byteCount;        
    }
}
