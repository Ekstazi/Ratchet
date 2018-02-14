<?php

namespace Reamp\Http;

use Reamp\ConnectionInterface;
use Reamp\MessageComponentInterface;

class HttpServer implements MessageComponentInterface {
    use CloseResponseTrait;

    /**
     * Buffers incoming HTTP requests returning a Guzzle Request when coalesced.
     * @var HttpRequestParser
     * @note May not expose this in the future, may do through facade methods
     */
    protected $_reqParser;

    /**
     * @var \Reamp\Http\HttpServerInterface
     */
    protected $_httpServer;

    /**
     * @param HttpServerInterface
     */
    public function __construct(HttpServerInterface $component) {
        $this->_httpServer = $component;
        $this->_reqParser  = new HttpRequestParser;
    }

    /**
     * {@inheritdoc}
     */
    public function onOpen(ConnectionInterface $conn) {
        $conn->httpHeadersReceived = false;
    }

    /**
     * {@inheritdoc}
     */
    public function onMessage(ConnectionInterface $from, $msg) {
        if (true !== $from->httpHeadersReceived) {
            try {
                if (null === ($request = $this->_reqParser->onMessage($from, $msg))) {
                    return;
                }
            } catch (\OverflowException $oe) {
                return $this->close($from, 413);
            }

            $from->httpHeadersReceived = true;
            // proxy component handler onOpen so it can use async or sync context
            return $this->_httpServer->onOpen($from, $request);
        }

        // proxy component handler onOpen so it can use async or sync context
        return $this->_httpServer->onMessage($from, $msg);
    }

    /**
     * {@inheritdoc}
     */
    public function onClose(ConnectionInterface $conn) {
        if ($conn->httpHeadersReceived) {
            // proxy component handler onOpen so it can use async or sync context
            return $this->_httpServer->onClose($conn);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onError(ConnectionInterface $conn, \Exception $e) {
        if ($conn->httpHeadersReceived) {
            // proxy component handler onOpen so it can use async or sync context
            return $this->_httpServer->onError($conn, $e);
        }
        return $this->close($conn, 500);
    }
}
