<?php

namespace tests\helpers;

use Reamp\ConnectionInterface;
use Reamp\MessageComponentInterface;
use Reamp\Wamp\WampServerInterface;
use Reamp\WebSocket\WsServerInterface;

class NullComponent implements MessageComponentInterface, WsServerInterface, WampServerInterface {
    public function onOpen(ConnectionInterface $conn) {
    }

    public function onMessage(ConnectionInterface $conn, $msg) {
    }

    public function onClose(ConnectionInterface $conn) {
    }

    public function onError(ConnectionInterface $conn, \Throwable $e) {
    }

    public function onCall(ConnectionInterface $conn, $id, $topic, array $params) {
    }

    public function onSubscribe(ConnectionInterface $conn, $topic) {
    }

    public function onUnSubscribe(ConnectionInterface $conn, $topic) {
    }

    public function onPublish(ConnectionInterface $conn, $topic, $event, array $exclude = [], array $eligible = []) {
    }

    public function getSubProtocols() {
        return [];
    }
}
