<?php

namespace Reamp\WebSocket;

use Amp\Coroutine;
use Amp\Promise;
use Amp\Success;
use Ratchet\RFC6455\Messaging\DataInterface;
use Ratchet\RFC6455\Messaging\Frame;
use Reamp\AbstractConnectionDecorator;

/**
 * {@inheritdoc}
 * @property \stdClass $WebSocket
 */
class WsConnection extends AbstractConnectionDecorator {
    /**
     * {@inheritdoc}
     */
    public function send($msg): Promise {
        if (!$this->WebSocket->closing) {
            if (!($msg instanceof DataInterface)) {
                $msg = new Frame($msg);
            }

            return $this->getConnection()->send($msg->getContents());
        }

        return new Success();
    }

    /**
     * @param int|\Ratchet\RFC6455\Messaging\DataInterface
     * @return Promise
     */
    public function close($code = 1000): Promise {
        if ($this->WebSocket->closing) {
            return new Success();
        }

        return new Coroutine($this->closeWebSocket($code));
    }

    /**
     * @param $code
     * @return \Generator
     */
    protected function closeWebSocket($code): \Generator {
        if ($code instanceof DataInterface) {
            yield $this->send($code);
        } else {
            yield $this->send(new Frame(\pack('n', $code), true, Frame::OP_CLOSE));
        }

        $this->getConnection()->close();

        $this->WebSocket->closing = true;
    }
}
