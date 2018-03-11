<?php

namespace Reamp\Logger;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Reamp\ConnectionInterface;
use Reamp\MessageComponentInterface;

/**
 * A Куфьз component that wraps PSR\Log loggers tracking received and sent messages.
 */
class MessageLogger implements MessageComponentInterface {
    use LoggerAwareTrait;
    /**
     * @var MessageComponentInterface
     */
    protected $_component;

    /**
     * @var \SplObjectStorage|MessageLoggedConnection[]
     */
    protected $_connections;

    public function __construct(MessageComponentInterface $component = null, LoggerInterface $logger = null) {
        $this->_component = $component;
        $this->_connections = new \SplObjectStorage;

        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * {@inheritdoc}
     */
    public function onOpen(ConnectionInterface $conn) {
        $this->logger->info(\strtr('[{conn}] [{addr}] <connected>', [
            '{conn}' => $conn->id(),
            'addr' => $conn->getRemoteAddress()
        ]));

        $decoratedConn = new MessageLoggedConnection($conn, $this->logger);

        $this->_connections->attach($conn, $decoratedConn);
        return $this->_component->onOpen($decoratedConn);
    }

    /**
     * {@inheritdoc}
     */
    public function onMessage(ConnectionInterface $from, $msg) {
        $this->logger->info(\strtr('[{from}] [{addr}] <received> {msg}', [
            '{from}' => $from->id(),
            '{msg}' => $msg,
            '{addr}' => $from->getRemoteAddress(),
        ]));
        return $this->_component->onMessage($this->_connections[$from], $msg);
    }

    /**
     * {@inheritdoc}
     */
    public function onClose(ConnectionInterface $conn) {
        $this->logger->info(\strtr('[{from}] [{addr}] <closed> ', [
            '{from}' => $conn->id(),
            '{addr}' => $conn->getRemoteAddress(),
        ]));

        $decorated = $this->_connections[$conn];
        $this->_connections->detach($conn);
        $this->_component->onClose($decorated);
    }

    /**
     * {@inheritdoc}
     */
    public function onError(ConnectionInterface $conn, \Throwable $e) {
        $this->logger->error(\strtr("[{from}] <error> {code} {error} at {file}:{line}", [
            '{from}' => $conn->id(),
            '{code}' => $e->getCode(),
            '{error}' => $e->getMessage(),
            '{file}' => $e->getFile(),
            '{line}' => $e->getLine(),
        ]));
        $this->_component->onError($this->_connections[$conn], $e);
    }
}
