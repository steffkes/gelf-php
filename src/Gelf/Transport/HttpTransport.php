<?php

namespace Gelf\Test\Transport;

use Gelf\Encoder\EncoderInterface;
use Gelf\Encoder\JsonEncoder as DefaultEncoder;
use Gelf\MessageInterface;
use Gelf\Transport\StreamSocketClient;
use Gelf\Transport\TransportInterface;
use InvalidArgumentException;

class HttpTransport implements TransportInterface
{
    const NO_SSL                = 0x00;
    const SSL                   = 0x01;

    const SSL_ENABLED           = 0x01;
    const SSL_NO_VERIFY_PEER    = 0x02;
    const SSL_ALLOW_SELF_SIGNED = 0x04;

    const DEFAULT_PORT = 12202;
    const DEFAULT_HOST = "127.0.0.1";
    const DEFAULT_PATH = "/gelf";

    /**
     * The host the HTTP receiver is on
     *
     * @var string
     */
    protected $host;

    /**
     * The port the HTTP receiver listens on
     *
     * @var int
     */
    protected $port;

    /**
     * The URL path the reciver listens on
     *
     * @var string
     */
    protected $path;

    /**
     * The ssl mode to use for the connection
     *
     * @var int
     */
    protected $sslMode;

    /**
     * @var StreamSocketClient
     */
    protected $socketClient = null;

    /**
     * @var EncoderInterface
     */
    protected $messageEncoder = null;

    /**
     * Creates an HttpTransport which can send Gelf\Messages to a Graylog2 server via HTTP
     *
     * @param string $host
     * @param int $port
     * @param string $path
     * @param int $ssl
     * @throws InvalidArgumentException
     */
    public function __construct(
        $host = self::DEFAULT_HOST,
        $port = self::DEFAULT_PORT,
        $path = self::DEFAULT_PATH,
        $ssl  = self::NO_SSL
    )
    {
        if (!$host) {
            throw new InvalidArgumentException("\$host must not be empty");
        }

        if (!is_int($port) || $port < 1 || $port > 65535) {
            throw new InvalidArgumentException("\$port must be an int in the range 1-65535");
        }

        if (strlen($path) < 1 || $path[0] != '/') {
            throw new InvalidArgumentException("\$path must not be empty and start with a slash");
        }

        $this->host     = $host;
        $this->port     = $port;
        $this->path     = $path;
        $this->sslMode  = intval($ssl);
    }

    public function send(MessageInterface $message)
    {
        $requestTemplate = "POST %s HTTP/1.1\r\n"
            . "Host: %s\r\n"
            . "Content-Type: application/json\r\n"
            . "Content-Length: %d\r\n"
            . "Connection: Close\r\n"
            . "\r\n"
            . "%s\r\n"
        ;
        $body = $this->getMessageEncoder()->encode($message);
        $request = sprintf($requestTemplate, $this->path, $this->host, strlen($body), $body);

        $this->getSocketClient()->write($request);
    }

    /**
     * Sets a message encoder
     *
     * @param EncoderInterface $encoder
     */
    public function setMessageEncoder(EncoderInterface $encoder)
    {
        $this->messageEncoder = $encoder;
    }

    /**
     * Returns the current message encoder
     *
     * @return EncoderInterface
     */
    public function getMessageEncoder()
    {
        if (!$this->messageEncoder) {
            $this->messageEncoder = new DefaultEncoder();
        }

        return $this->messageEncoder;
    }

    /**
     * Lazy initialization of the StreamSocketClient
     *
     * @return StreamSocketClient
     */
    protected function getSocketClient()
    {
        if ($this->socketClient) {
            return $this->socketClient;
        }

        $this->socketClient = new StreamSocketClient(
            $this->getSocketScheme(),
            $this->host,
            $this->port,
            $this->getSocketContext()
        );

        return $this->socketClient;
    }

    /**
     * Returns the correct protocol-scheme for the given SSL config
     *
     * @return string
     */
    protected function getSocketScheme()
    {
        return ($this->sslMode & self::SSL_ENABLED) ? 'ssl' : 'tcp';
    }

    /**
     * Creates the correct stream context for the given SSL config
     *
     * @return array
     */
    protected function getSocketContext()
    {
        $context = [];

        // ssl options
        if ($this->sslMode & self::SSL_ENABLED) {
            $context['ssl'] = [
                'allow_self_signed' => (bool) $this->sslMode & self::SSL_ALLOW_SELF_SIGNED,
                'verify_peer'       => (bool) $this->sslMode & self::SSL_NO_VERIFY_PEER,
            ];
        }

        return $context;
    }
}
