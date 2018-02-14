<?php

namespace Reamp\Wamp;

use Reamp\ConnectionInterface;
use Reamp\MessageComponentInterface;
use Reamp\WebSocket\WsServerInterface;

/**
 * WebSocket Application Messaging Protocol.
 *
 * @link http://wamp.ws/spec
 * @link https://github.com/oberstet/autobahn-js
 *
 * +--------------+----+------------------+
 * | Message Type | ID | DIRECTION        |
 * |--------------+----+------------------+
 * | WELCOME      | 0  | Server-to-Client |
 * | PREFIX       | 1  | Bi-Directional   |
 * | CALL         | 2  | Client-to-Server |
 * | CALL RESULT  | 3  | Server-to-Client |
 * | CALL ERROR   | 4  | Server-to-Client |
 * | SUBSCRIBE    | 5  | Client-to-Server |
 * | UNSUBSCRIBE  | 6  | Client-to-Server |
 * | PUBLISH      | 7  | Client-to-Server |
 * | EVENT        | 8  | Server-to-Client |
 * +--------------+----+------------------+
 */
class ServerProtocol implements MessageComponentInterface, WsServerInterface {
    const MSG_WELCOME     = 0;
    const MSG_PREFIX      = 1;
    const MSG_CALL        = 2;
    const MSG_CALL_RESULT = 3;
    const MSG_CALL_ERROR  = 4;
    const MSG_SUBSCRIBE   = 5;
    const MSG_UNSUBSCRIBE = 6;
    const MSG_PUBLISH     = 7;
    const MSG_EVENT       = 8;

    /**
     * @var WampServerInterface
     */
    protected $_decorating;

    /**
     * @var \SplObjectStorage
     */
    protected $connections;

    /**
     * @param WampServerInterface $serverComponent An class to propagate calls through
     */
    public function __construct(WampServerInterface $serverComponent) {
        $this->_decorating = $serverComponent;
        $this->connections = new \SplObjectStorage;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubProtocols() {
        if ($this->_decorating instanceof WsServerInterface) {
            $subs   = $this->_decorating->getSubProtocols();
            $subs[] = 'wamp';

            return $subs;
        }

        return ['wamp'];
    }

    /**
     * {@inheritdoc}
     */
    public function onOpen(ConnectionInterface $conn) {
        $decor = new WampConnection($conn);
        $this->connections->attach($conn, $decor);

        // proxy component handler onOpen so it can use async or sync context
        return $this->_decorating->onOpen($decor);
    }

    /**
     * {@inheritdoc}
     * @throws \Reamp\Wamp\Exception
     * @throws \Reamp\Wamp\JsonException
     */
    public function onMessage(ConnectionInterface $from, $msg) {
        $from = $this->connections[$from];

        if (null === ($json = @\json_decode($msg, true))) {
            throw new JsonException;
        }

        if (!\is_array($json) || $json !== \array_values($json)) {
            throw new Exception("Invalid WAMP message format");
        }

        if (isset($json[1]) && !(\is_string($json[1]) || \is_numeric($json[1]))) {
            throw new Exception('Invalid Topic, must be a string');
        }

        switch ($json[0]) {
            case static::MSG_PREFIX:
                $from->WAMP->prefixes[$json[1]] = $json[2];
            break;

            case static::MSG_CALL:
                \array_shift($json);
                $callID  = \array_shift($json);
                $procURI = \array_shift($json);

                if (\count($json) == 1 && \is_array($json[0])) {
                    $json = $json[0];
                }

                // proxy component handler onOpen so it can use async or sync context
                return $this->_decorating->onCall($from, $callID, $from->getUri($procURI), $json);

            case static::MSG_SUBSCRIBE:
                // proxy component handler onOpen so it can use async or sync context
                return $this->_decorating->onSubscribe($from, $from->getUri($json[1]));

            case static::MSG_UNSUBSCRIBE:
                // proxy component handler onOpen so it can use async or sync context
                return $this->_decorating->onUnSubscribe($from, $from->getUri($json[1]));

            case static::MSG_PUBLISH:
                $exclude  = (\array_key_exists(3, $json) ? $json[3] : null);
                if (!\is_array($exclude)) {
                    if (true === (bool) $exclude) {
                        $exclude = [$from->WAMP->sessionId];
                    } else {
                        $exclude = [];
                    }
                }

                $eligible = (\array_key_exists(4, $json) ? $json[4] : []);

                // proxy component handler onOpen so it can use async or sync context
                return $this->_decorating->onPublish($from, $from->getUri($json[1]), $json[2], $exclude, $eligible);

            default:
                throw new Exception('Invalid WAMP message type');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onClose(ConnectionInterface $conn) {
        $decor = $this->connections[$conn];
        $this->connections->detach($conn);

        // proxy component handler onOpen so it can use async or sync context
        return $this->_decorating->onClose($decor);
    }

    /**
     * {@inheritdoc}
     */
    public function onError(ConnectionInterface $conn, \Exception $e) {
        // proxy component handler onOpen so it can use async or sync context
        return $this->_decorating->onError($this->connections[$conn], $e);
    }
}
