<?php

/*
 * This file is part of the php-gelf package.
 *
 * (c) Benjamin Zikarsky <http://benjamin-zikarsky.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Gelf\Test\Transport;

use Gelf\Transport\StreamSocketClient;
use PHPUnit_Framework_TestCase as TestCase;

class StreamSocketClientTcpTest extends TestCase
{

    /**
     * @var StreamSocketClient
     */
    protected $socketClient;

    /**
     * @var resource
     */
    protected $serverSocket;

    public function setUp()
    {
        $host = "127.0.0.1";
        $this->serverSocket = stream_socket_server(
            "tcp://$host:0",
            $errNo,
            $errMsg
        );

        if (!$this->serverSocket) {
            throw new \RuntimeException("Failed to create test-server-socket");
        }

        // get random port
        $socketName = stream_socket_get_name(
            $this->serverSocket, 
            $peerName = false
        );
        list(, $port) = explode(":", $socketName);

        $this->socketClient = new StreamSocketClient('tcp', $host, $port);
    }

    public function tearDown()
    {
        unset($this->socketClient);
        fclose($this->serverSocket);
    }


    public function testGetSocket()
    {
        $this->assertTrue(is_resource($this->socketClient->getSocket()));
    }

    public function testWrite()
    {
        $testData = "Hello World!";
        $numBytes = $this->socketClient->write($testData);

        $this->assertEquals(strlen($testData), $numBytes);

        // check that message is sent to server
        $connection = stream_socket_accept($this->serverSocket);
        $readData = fread($connection, $numBytes);

        $this->assertEquals($testData, $readData);
    }

    public function testDestructorWithoutSocket()
    {
        unset($this->socketClient);
    }

    public function testDestructorWithSocket()
    {
        $this->socketClient->getSocket();
        unset($this->socketClient);
    }
}
