<?php

namespace Reamp\Http;

use Psr\Http\Message\ServerRequestInterface;
use Reamp\ConnectionInterface;
use Reamp\MessageComponentInterface;

/**
 * A middleware to ensure JavaScript clients connecting are from the expected domain.
 * This protects other websites from open WebSocket connections to your application.
 * Note: This can be spoofed from non-web browser clients.
 */
class OriginCheck implements HttpServerInterface {
    use CloseResponseTrait;

    /**
     * @var \Reamp\MessageComponentInterface
     */
    protected $_component;

    public $allowedOrigins = [];

    /**
     * @param MessageComponentInterface $component Component/Application to decorate
     * @param array                     $allowed   An array of allowed domains that are allowed to connect from
     */
    public function __construct(MessageComponentInterface $component, array $allowed = []) {
        $this->_component = $component;
        $this->allowedOrigins += $allowed;
    }

    /**
     * {@inheritdoc}
     */
    public function onOpen(ConnectionInterface $conn, ServerRequestInterface $request = null) {
        $header = (string) $request->getHeader('Origin')[0];
        $origin = \parse_url($header, PHP_URL_HOST) ?: $header;

        if (!\in_array($origin, $this->allowedOrigins)) {
            return $this->close($conn, 403);
        }

        // proxy component handler onOpen so it can use async or sync context
        return $this->_component->onOpen($conn, $request);
    }

    /**
     * {@inheritdoc}
     */
    public function onMessage(ConnectionInterface $from, $msg) {
        // proxy component handler onOpen so it can use async or sync context
        return $this->_component->onMessage($from, $msg);
    }

    /**
     * {@inheritdoc}
     */
    public function onClose(ConnectionInterface $conn) {
        // proxy component handler onOpen so it can use async or sync context
        return $this->_component->onClose($conn);
    }

    /**
     * {@inheritdoc}
     */
    public function onError(ConnectionInterface $conn, \Throwable $e) {
        // proxy component handler onOpen so it can use async or sync context
        return $this->_component->onError($conn, $e);
    }
}
