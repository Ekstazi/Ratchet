<?php

namespace Reamp\Http;

use Psr\Http\Message\ServerRequestInterface;
use Reamp\ConnectionInterface;

class NoOpHttpServerController implements HttpServerInterface {
    public function onOpen(ConnectionInterface $conn, ServerRequestInterface $request = null) {
    }

    public function onMessage(ConnectionInterface $from, $msg) {
    }

    public function onClose(ConnectionInterface $conn) {
    }

    public function onError(ConnectionInterface $conn, \Throwable $e) {
    }
}
