<?php

namespace tests\helpers\Wamp\Stub;

use Reamp\Wamp\WampServerInterface;
use Reamp\WebSocket\WsServerInterface;

interface WsWampServerInterface extends WsServerInterface, WampServerInterface {
}
