<?php

namespace Gelf\Transport;

use Gelf\MessageInterface;
use Gelf\Encoder\CompressedJsonEncoder;
use Gelf\Encoder\JsonEncoder as DefaultEncoder;

use RuntimeException;

class HttpTransport extends AbstractTransport
{
    protected $host = "127.0.0.1";
    protected $port = 12202;
    protected $path = "/gelf";

    protected $socketClient;

    public function __construct($host = null, $port = null, $path = null)
    {
        $this->host = $host ?: $this->host;
        $this->port = $port ?: $this->port;
        $this->path = $path ?: $this->path;

        $this->socketClient = new StreamSocketClient("tcp", $this->host, $this->port);
        $this->messageEncoder = new DefaultEncoder();
    }

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
