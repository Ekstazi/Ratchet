<?php

namespace tests\helpers\Mock;

use Amp\Promise;
use Amp\Success;
use Reamp\ConnectionInterface;

class Connection implements ConnectionInterface {
    public $last = [
        'send'  => '', 'close' => false
    ];

    public $remoteAddress = '127.0.0.1';

    public function send($data): Promise {
        $this->last[__FUNCTION__] = $data;
        return new Success();
    }

    public function close($data = null): Promise {
        if ($data) {
            $this->send($data);
        }
        $this->last[__FUNCTION__] = true;
        return new Success();
    }

    public function id() {
        return 1;
    }

    public function getRemoteAddress() {
        return $this->remoteAddress;
    }

    public function getLocalAddress() {
        return '0.0.0.0';
    }
}
