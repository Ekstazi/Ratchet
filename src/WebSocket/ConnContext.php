<?php

namespace Reamp\WebSocket;

use Ratchet\RFC6455\Messaging\MessageBuffer;

class ConnContext {
    /**
     * @var \Reamp\WebSocket\WsConnection
     */
    public $connection;

    /**
     * @var \Ratchet\RFC6455\Messaging\MessageBuffer;
     */
    public $buffer;

    public function __construct(WsConnection $conn, MessageBuffer $buffer) {
        $this->connection = $conn;
        $this->buffer = $buffer;
    }
}
