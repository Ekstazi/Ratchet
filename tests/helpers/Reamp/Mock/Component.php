<?php

namespace Reamp\Mock;

use Reamp\ComponentInterface;
use Reamp\ConnectionInterface;
use Reamp\MessageComponentInterface;
use Reamp\WebSocket\WsServerInterface;

class Component implements MessageComponentInterface, WsServerInterface {
    public $last = [];

    public $protocols = [];

    public function __construct(ComponentInterface $app = null) {
        $this->last[__FUNCTION__] = \func_get_args();
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->last[__FUNCTION__] = \func_get_args();
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $this->last[__FUNCTION__] = \func_get_args();
    }

    public function onClose(ConnectionInterface $conn) {
        $this->last[__FUNCTION__] = \func_get_args();
    }

    public function onError(ConnectionInterface $conn, \Throwable $e) {
        $this->last[__FUNCTION__] = \func_get_args();
    }

    public function getSubProtocols() {
        return $this->protocols;
    }
}
