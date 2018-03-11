<?php

namespace Reamp\Logger;

use Amp\Promise;
use Psr\Log\LoggerInterface;
use Reamp\AbstractConnectionDecorator;
use Reamp\ConnectionInterface;

class MessageLoggedConnection extends AbstractConnectionDecorator {
    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(ConnectionInterface $conn, LoggerInterface $logger) {
        parent::__construct($conn);
        $this->logger = $logger;
    }

    public function send($data): Promise {
        $this->logger->debug(\strtr("[{conn}] <Transmitted> {msg}", [
            '{conn}' => $this->id(),
            '{msg}' => $data
        ]));

        return $this->getConnection()->send($data);
    }

    public function close($data = null): Promise {
        $this->logger->debug(\strtr("[{conn}] <Closing> {msg}", [
            '{conn}' => $this->id(),
            '{msg}' => $data
        ]));

        return $this->getConnection()->close($data);
    }
}
