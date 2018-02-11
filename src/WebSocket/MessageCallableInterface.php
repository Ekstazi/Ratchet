<?php

namespace Reamp\WebSocket;

use Ratchet\RFC6455\Messaging\MessageInterface;
use Reamp\ConnectionInterface;

interface MessageCallableInterface {
    public function onMessage(ConnectionInterface $conn, MessageInterface $msg);
}
