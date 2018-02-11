<?php

namespace Reamp\WebSocket;

use Reamp\ConnectionInterface;
use Ratchet\RFC6455\Messaging\MessageInterface;

interface MessageCallableInterface {
    public function onMessage(ConnectionInterface $conn, MessageInterface $msg);
}
