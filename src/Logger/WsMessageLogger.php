<?php

namespace Reamp\Logger;

use Psr\Log\LoggerInterface;
use Reamp\WebSocket\WsServerInterface;

class WsMessageLogger extends MessageLogger implements WsServerInterface {
    public function __construct(WsServerInterface $component = null, LoggerInterface $logger = null) {
        parent::__construct($component, $logger);
    }

    public function getSubProtocols() {
        return $this->_component->getSubProtocols();
    }
}
