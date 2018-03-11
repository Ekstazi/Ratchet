<?php
/**
 * Created by PhpStorm.
 * User: Maxim Furtuna
 * Date: 11.03.18
 * Time: 11:37.
 */

namespace Reamp\Logger;

use PHPUnit\Framework\TestCase;
use Reamp\Mock\Component;

class WsMessageLoggerTest extends TestCase {
    public function testGetSubProtocols() {
        $wsServer = new Component();
        $wsServer->protocols = ['test'];
        $server = new WsMessageLogger($wsServer);
        $this->assertEquals($server->getSubProtocols(), $wsServer->protocols);
    }
}
