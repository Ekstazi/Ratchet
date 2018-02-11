<?php

namespace Ratchet\Server;

use Amp\Promise;
use Amp\Socket\ServerSocket as AmpConn;
use Ratchet\ConnectionInterface;

/**
 * {@inheritdoc}
 */
class IoConnection implements ConnectionInterface {
    /**
     * @var AmpConn
     */
    protected $conn;

    protected $promises = [];

    /**
     * @param AmpConn $conn
     */
    public function __construct(AmpConn $conn) {
        $this->conn = $conn;
    }

    /**
     * {@inheritdoc}
     */
    public function send($data) {
        $this->promises[] = $this->conn->write($data);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function close() {
        $this->promises[] = $this->conn->end();
    }

    public function id() {
        return (int) $this->conn->getResource();
    }

    public function getRemoteAddress() {
        return $this->conn->getRemoteAddress();
    }

    public function flushAll(): Promise {
        $active = $this->promises;
        $this->promises = [];
        return \Amp\Promise\all($active);
    }
}
