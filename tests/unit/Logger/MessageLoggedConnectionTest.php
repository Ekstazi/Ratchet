<?php

namespace Reamp\Logger;

use PHPUnit\Framework\TestCase;
use Reamp\Mock\Connection;
use Reamp\Mock\Logger;

class MessageLoggedConnectionTest extends TestCase {
    /**
     * @var MessageLoggedConnection
     */
    protected $connection;

    /**
     * @var Connection
     */
    protected $original;

    /**
     * @var Logger
     */
    protected $logger;

    protected function setUp() {
        parent::setUp();
        $this->original = new Connection();
        $this->logger = new Logger();
        $this->connection = new MessageLoggedConnection($this->original, $this->logger);
    }


    public function testSend() {
        $this->connection->send('ghhhhhh');
        $this->assertEquals($this->original->last['send'], 'ghhhhhh');
    }

    public function testClose() {
        $this->connection->close('ghhhhhh');
        $this->assertEquals($this->original->last['send'], 'ghhhhhh');
        $this->assertTrue($this->original->last['close']);
    }

    public function testSendLog() {
        $this->connection->send('ghhhhhh');
        $this->assertCount(1, $this->logger->written);
    }

    public function testCloseLog() {
        $this->connection->close('ghhhhh');
        $this->assertCount(1, $this->logger->written);
    }
}
