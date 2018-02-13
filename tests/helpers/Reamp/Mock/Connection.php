<?php

namespace Reamp\Mock;

use Reamp\ConnectionInterface;

class Connection implements ConnectionInterface {
    public $last = [
        'send'  => '', 'close' => false
    ];

    public $remoteAddress = '127.0.0.1';

    public function send($data) {
        $this->last[__FUNCTION__] = $data;
    }

    public function close() {
        $this->last[__FUNCTION__] = true;
    }

    public function id() {
        return 1;
    }

    public function getRemoteAddress() {
        return $this->remoteAddress;
    }
}