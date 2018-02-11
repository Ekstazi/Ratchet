<?php

namespace Reamp\Wamp;

use Reamp\ComponentInterface;
use Reamp\ConnectionInterface;

/**
 * An extension of ReAmp\ComponentInterface to server a WAMP application
 * onMessage is replaced by various types of messages for this protocol (pub/sub or rpc).
 */
interface WampServerInterface extends ComponentInterface {
    /**
     * An RPC call has been received.
     * @param \Reamp\ConnectionInterface $conn
     * @param string                       $id The unique ID of the RPC, required to respond to
     * @param string|Topic                 $topic The topic to execute the call against
     * @param array                        $params Call parameters received from the client
     */
    public function onCall(ConnectionInterface $conn, $id, $topic, array $params);

    /**
     * A request to subscribe to a topic has been made.
     * @param \Reamp\ConnectionInterface $conn
     * @param string|Topic                 $topic The topic to subscribe to
     */
    public function onSubscribe(ConnectionInterface $conn, $topic);

    /**
     * A request to unsubscribe from a topic has been made.
     * @param \Reamp\ConnectionInterface $conn
     * @param string|Topic                 $topic The topic to unsubscribe from
     */
    public function onUnSubscribe(ConnectionInterface $conn, $topic);

    /**
     * A client is attempting to publish content to a subscribed connections on a URI.
     * @param \Reamp\ConnectionInterface $conn
     * @param string|Topic                 $topic The topic the user has attempted to publish to
     * @param string                       $event Payload of the publish
     * @param array                        $exclude A list of session IDs the message should be excluded from (blacklist)
     * @param array                        $eligible A list of session Ids the message should be send to (whitelist)
     */
    public function onPublish(ConnectionInterface $conn, $topic, $event, array $exclude, array $eligible);
}
