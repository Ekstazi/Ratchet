<?php
/**
 * Created by PhpStorm.
 * User: Maxim Furtuna
 * Date: 11.03.18
 * Time: 0:41.
 */

namespace Reamp\Logger;

use Reamp\AbstractMessageComponentTestCase;
use Reamp\MessageComponentInterface;
use Reamp\Mock\Logger;

class MessageLoggerTest extends AbstractMessageComponentTestCase {
    /**
     * @var Logger
     */
    protected $logger;

    public function setUp() {
        parent::setUp();
        $this->logger = new Logger();
        $this->_serv->setLogger($this->logger);
    }


    public function getConnectionClassString() {
        return MessageLoggedConnection::class;
    }

    public function getDecoratorClassString() {
        return MessageLogger::class;
    }

    public function getComponentClassString() {
        return MessageComponentInterface::class;
    }

    public function testOnOpenLog() {
        $this->doOpen($this->_conn);
        $this->assertCount(1, $this->logger->written);
    }


    public function testOnMessageLog() {
        $this->_serv->onMessage($this->_conn, 'test');
        $this->assertCount(1, $this->logger->written);
    }

    public function testOnCloseLog() {
        $this->_serv->onClose($this->_conn);
        $this->assertCount(1, $this->logger->written);
    }

    public function testOnErrorLog() {
        $this->_serv->onError($this->_conn, new \Exception());
        $this->assertCount(1, $this->logger->written);
    }

    public function testOnMessage() {
        $this->passthroughMessageTest('test');
    }
}
