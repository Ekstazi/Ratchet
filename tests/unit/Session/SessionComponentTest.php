<?php

namespace Reamp\Session;

use Psr\Http\Message\RequestInterface;
use Reamp\AbstractMessageComponentTestCase;
use Reamp\ConnectionInterface;
use Reamp\Http\HttpServerInterface;
use Reamp\NullComponent;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NullSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

/**
 * @covers \Reamp\Session\SessionProvider
 * @covers \Reamp\Session\Storage\VirtualSessionStorage
 * @covers \Reamp\Session\Storage\Proxy\VirtualProxy
 */
class SessionComponentTest extends AbstractMessageComponentTestCase {
    public function setUp() {
        if (!\class_exists('Symfony\Component\HttpFoundation\Session\Session')) {
            return $this->markTestSkipped('Dependency of Symfony HttpFoundation failed');
        }

        parent::setUp();
        $this->_serv = new SessionProvider($this->_app, new NullSessionHandler);
    }

    public function tearDown() {
        \ini_set('session.serialize_handler', 'php');
    }

    public function getConnectionClassString() {
        return ConnectionInterface::class;
    }

    public function getDecoratorClassString() {
        return NullComponent::class;
    }

    public function getComponentClassString() {
        return HttpServerInterface::class;
    }

    public function classCaseProvider() {
        return [
            ['php', 'Php'], ['php_binary', 'PhpBinary']
        ];
    }

    /**
     * @dataProvider classCaseProvider
     */
    public function testToClassCase($in, $out) {
        $ref = new \ReflectionClass(SessionProvider::class);
        $method = $ref->getMethod('toClassCase');
        $method->setAccessible(true);

        $component = new SessionProvider($this->createMock($this->getComponentClassString()), $this->createMock(\SessionHandlerInterface::class));
        $this->assertEquals($out, $method->invokeArgs($component, [$in]));
    }

    /**
     * I think I have severely butchered this test...it's not so much of a unit test as it is a full-fledged component test.
     */
    public function testConnectionValueFromPdo() {
        if (!\extension_loaded('PDO') || !\extension_loaded('pdo_sqlite')) {
            return $this->markTestSkipped('Session test requires PDO and pdo_sqlite');
        }

        $sessionId = \md5('testSession');

        $dbOptions = [
            'db_table'    => 'sessions', 'db_id_col'   => 'sess_id', 'db_data_col' => 'sess_data', 'db_time_col' => 'sess_time', 'db_lifetime_col' => 'sess_lifetime'
        ];

        $pdo = new \PDO("sqlite::memory:");
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec(\vsprintf("CREATE TABLE %s (%s TEXT NOT NULL PRIMARY KEY, %s BLOB NOT NULL, %s INTEGER NOT NULL, %s INTEGER)", $dbOptions));

        $pdoHandler = new PdoSessionHandler($pdo, $dbOptions);
        $pdoHandler->write($sessionId, '_sf2_attributes|a:2:{s:5:"hello";s:5:"world";s:4:"last";i:1332872102;}_sf2_flashes|a:0:{}');

        $component  = new SessionProvider($this->createMock($this->getComponentClassString()), $pdoHandler, ['auto_start' => 1]);
        $connection = $this->createMock(ConnectionInterface::class);

        $headers = $this->createMock(RequestInterface::class);
        $headers->expects($this->once())->method('getHeader')->will($this->returnValue([\ini_get('session.name') . "={$sessionId};"]));

        $component->onOpen($connection, $headers);

        $this->assertEquals('world', $connection->Session->get('hello'));
    }

    protected function newConn() {
        $conn = $this->createMock(ConnectionInterface::class);

        $headers = $this->createMock(RequestInterface::class, ['getCookie'], ['POST', '/', []]);
        $headers->expects($this->once())->method('getCookie', [\ini_get('session.name')])->will($this->returnValue(null));

        return $conn;
    }

    public function testOnMessageDecorator() {
        $message = "Database calls are usually blocking  :(";
        $this->_app->expects($this->once())->method('onMessage')->with($this->isExpectedConnection(), $message);
        $this->_serv->onMessage($this->_conn, $message);
    }

    public function testRejectInvalidSeralizers() {
        if (!\function_exists('wddx_serialize_value')) {
            $this->markTestSkipped();
        }

        \ini_set('session.serialize_handler', 'wddx');
        $this->expectException('\RuntimeException');
        new SessionProvider($this->createMock($this->getComponentClassString()), $this->createMock(\SessionHandlerInterface::class));
    }

    protected function doOpen($conn) {
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->any())->method('getHeader')->will($this->returnValue([]));

        $this->_serv->onOpen($conn, $request);
    }
}
