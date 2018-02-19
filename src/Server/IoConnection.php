<?php

namespace Reamp\Server;

use Amp\Promise;
use Amp\Socket\ServerSocket as AmpConn;
use Reamp\ConnectionInterface;

/**
 * {@inheritdoc}
 */
class IoConnection implements ConnectionInterface {
    /**
     * @var AmpConn
     */
    protected $conn;

    /**
     * @param AmpConn $conn
     */
    public function __construct(AmpConn $conn) {
        $this->conn = $conn;
    }

    /**
     * {@inheritdoc}
     */
    public function send($data): Promise {
        return $this->conn->write($data);
    }

    /**
     * {@inheritdoc}
     */
    public function close(): Promise {
        return $this->conn->end();
    }

    public function id() {
        return (int) $this->conn->getResource();
    }

    public function getRemoteAddress() {
        return $this->conn->getRemoteAddress();
    }

    public function getLocalAddress() {
        return $this->conn->getLocalAddress();
    }
}
