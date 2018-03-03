<?php

namespace Reamp;

interface MessageInterface {
    /**
     * Triggered when a client sends data through the socket.
     * @param  \Reamp\ConnectionInterface $from The socket/connection that sent the message to your application
     * @param  string                       $msg  The message received
     * @throws \Throwable
     */
    public function onMessage(ConnectionInterface $from, $msg);
}
