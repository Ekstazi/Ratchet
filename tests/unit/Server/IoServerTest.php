<?php

namespace Reamp\Server;

use Amp\Loop;
use Amp\Socket\Server;
use Amp\Socket\ServerSocket;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Reamp\ConnectionInterface;
use Reamp\MessageComponentInterface;

/**
 * @covers \Reamp\Server\IoServer
 */
class IoServerTest extends TestCase {

    /**
     * @var  IoServer
     */
    protected $server;

    /**
     * @var MockObject|MessageComponentInterface
     */
    protected $app;

    protected $port;

    /**
     * @var Server
     */
    protected $reactor;

    public function setUp() {
        $this->app = $this->createMock(MessageComponentInterface::class);

        $loop = Loop::get();
        $this->reactor = \Amp\Socket\listen('0.0.0.0:0');

        $uri = $this->reactor->getAddress();
        $this->port = \parse_url((\strpos($uri, '://') === false ? 'tcp://' : '') . $uri, PHP_URL_PORT);
        $this->server = new IoServer($this->app, $this->reactor, $loop);
    }

    public function testOnOpen() {
        $this->app->expects($this->once())->method('onOpen')->with($this->isInstanceOf(ConnectionInterface::class));


        //$this->reactor->close();
        $this->server->loop->delay(100, [$this->reactor, 'close']);
        $this->server->loop->defer(function () {
            $client = \stream_socket_client("tcp://localhost:{$this->port}");
        });
        $this->server->run();
        //$this->server->loop->tick();

        //$this->assertTrue(is_string($this->app->last['onOpen'][0]->remoteAddress));
        //$this->assertTrue(is_int($this->app->last['onOpen'][0]->resourceId));
    }

    public function testOnData() {
        $msg = 'Hello World!';

        $this->app->expects($this->once())->method('onMessage')->with(
            $this->isInstanceOf(ConnectionInterface::class),
            $msg
        );

        $client = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        \socket_set_option($client, SOL_SOCKET, SO_REUSEADDR, 1);
        \socket_set_option($client, SOL_SOCKET, SO_SNDBUF, 4096);
        \socket_set_block($client);

        $this->server->loop->delay(100, [$this->reactor, 'close']);
        $this->server->loop->defer(function () use ($client, $msg) {
            \socket_connect($client, 'localhost', $this->port);

            //$this->server->loop->tick();

            \socket_write($client, $msg);
            //$this->server->loop->tick();

            \socket_shutdown($client, 1);
            \socket_shutdown($client, 0);
            \socket_close($client);
        });
        $this->server->run();
    }

    public function testOnClose() {
        $this->app->expects($this->once())->method('onClose')->with($this->isInstanceOf(ConnectionInterface::class));

        $client = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        \socket_set_option($client, SOL_SOCKET, SO_REUSEADDR, 1);
        \socket_set_option($client, SOL_SOCKET, SO_SNDBUF, 4096);
        \socket_set_block($client);

        $this->server->loop->delay(100, [$this->reactor, 'close']);
        $this->server->loop->defer(function () use ($client) {
            \socket_connect($client, 'localhost', $this->port);


            \socket_shutdown($client, 1);
            \socket_shutdown($client, 0);
            \socket_close($client);
        });
        $this->server->run();
    }

    public function testFactory() {
        $this->assertInstanceOf(IoServer::class, IoServer::factory($this->app, 0));
    }

    public function testNoLoopProvidedError() {
        $this->expectException('RuntimeException');

        $io = new IoServer($this->app, $this->reactor);
        $io->run();
    }

    public function testOnErrorPassesException() {
        //$conn = $this->createMock(ServerSocket::class);
        $decor = $this->createMock(ConnectionInterface::class);
        $err = new \Exception("Nope");

        $this->app->expects($this->once())->method('onError')->with($decor, $err);

        $this->server->handleError($err, $decor);
    }

    public function onErrorCalledWhenExceptionThrown() {
        $this->markTestIncomplete("Need to learn how to throw an exception from a mock");

        $conn = $this->createMock(ServerSocket::class);
        $this->server->handleConnect($conn);

        $e = new \Exception;
        $this->app->expects($this->once())->method('onMessage')->with($this->isInstanceOf(ConnectionInterface::class), 'f')->will($e);
        $this->app->expects($this->once())->method('onError')->with($this->instanceOf(ConnectionInterface::class, $e));

        $this->server->handleData('f', $conn);
    }
}
