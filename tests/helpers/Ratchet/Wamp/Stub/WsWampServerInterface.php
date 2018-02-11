<?php

namespace Reamp\Wamp\Stub;

use Reamp\Wamp\WampServerInterface;
use Reamp\WebSocket\WsServerInterface;

interface WsWampServerInterface extends WsServerInterface, WampServerInterface {
}
