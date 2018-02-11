<?php

namespace Reamp\Http;

use Psr\Http\Message\RequestInterface;
use Reamp\ConnectionInterface;
use Reamp\MessageComponentInterface;

interface HttpServerInterface extends MessageComponentInterface {
    /**
     * @param \Reamp\ConnectionInterface          $conn
     * @param \Psr\Http\Message\RequestInterface    $request null is default because PHP won't let me overload; don't pass null!!!
     * @throws \UnexpectedValueException if a RequestInterface is not passed
     */
    public function onOpen(ConnectionInterface $conn, RequestInterface $request = null);
}
