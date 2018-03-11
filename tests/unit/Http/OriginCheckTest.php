<?php

namespace Reamp\Http;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ServerRequestInterface;
use Reamp\AbstractMessageComponentTestCase;
use Reamp\ConnectionInterface;

/**
 * @covers \Reamp\Http\OriginCheck
 */
class OriginCheckTest extends AbstractMessageComponentTestCase {
    /**
     * @var MockObject
     */
    protected $_reqStub;

    public function setUp() {
        /** @var MockObject _reqStub */
        $this->_reqStub = $this->createMock(ServerRequestInterface::class);
        $this->_reqStub->expects($this->any())->method('getHeader')->will($this->returnValue(['localhost']));

        parent::setUp();

        $this->_serv->allowedOrigins[] = 'localhost';
    }

    protected function doOpen($conn) {
        $this->_serv->onOpen($conn, $this->_reqStub);
    }

    public function getConnectionClassString() {
        return ConnectionInterface::class;
    }

    public function getDecoratorClassString() {
        return OriginCheck::class;
    }

    public function getComponentClassString() {
        return HttpServerInterface::class;
    }

    public function testCloseOnNonMatchingOrigin() {
        $this->_serv->allowedOrigins = ['socketo.me'];
        $this->_conn->expects($this->once())->method('close');

        $this->_serv->onOpen($this->_conn, $this->_reqStub);
    }

    public function testOnMessage() {
        $this->passthroughMessageTest('Hello World!');
    }

    public function testEmptyOrigin() {
        $request = new ServerRequest('get', '/');
        try {
            $e = null;
            $this->_serv->onOpen($this->_conn, $request);
        } catch (\Throwable $e) {
        }
        $this->assertEmpty($e);
    }

    public function testCloseOnEmptyOrigin() {
        $this->_serv->allowedOrigins = ['socketo.me'];
        $this->_conn->expects($this->once())->method('close');

        $this->_serv->onOpen($this->_conn, new ServerRequest('get', '/'));
    }
}
