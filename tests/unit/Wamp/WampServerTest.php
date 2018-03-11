<?php

namespace tests\Wamp;

use PHPUnit\Framework\Constraint\IsInstanceOf;
use Reamp\AbstractMessageComponentTestCase;
use Reamp\Wamp\Topic;
use Reamp\Wamp\WampConnection;
use Reamp\Wamp\WampServer;
use Reamp\Wamp\WampServerInterface;

/**
 * @covers \Reamp\Wamp\WampServer
 */
class WampServerTest extends AbstractMessageComponentTestCase {
    public function getConnectionClassString() {
        return WampConnection::class;
    }

    public function getDecoratorClassString() {
        return WampServer::class;
    }

    public function getComponentClassString() {
        return WampServerInterface::class;
    }

    public function testOnMessageToEvent() {
        $published = 'Client published this message';

        $this->_app->expects($this->once())->method('onPublish')->with(
            $this->isExpectedConnection(),
            new IsInstanceOf(Topic::class),
            $published,
            [],
            []
        );

        $this->_serv->onMessage($this->_conn, \json_encode([7, 'topic', $published]));
    }

    public function testGetSubProtocols() {
        // todo: could expand on this
        $this->assertInternalType('array', $this->_serv->getSubProtocols());
    }

    public function testConnectionClosesOnInvalidJson() {
        $this->_conn->expects($this->once())->method('close');
        $this->_serv->onMessage($this->_conn, 'invalid json');
    }

    public function testConnectionClosesOnProtocolError() {
        $this->_conn->expects($this->once())->method('close');
        $this->_serv->onMessage($this->_conn, \json_encode(['valid' => 'json', 'invalid' => 'protocol']));
    }
}
