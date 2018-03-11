<?php

namespace tests\Wamp;

use PHPUnit\Framework\TestCase;
use Reamp\Wamp\Exception;
use Reamp\Wamp\JsonException;
use Reamp\Wamp\ServerProtocol;
use Reamp\Wamp\WampConnection;
use Reamp\Wamp\WampServerInterface;
use tests\helpers\Mock\Connection;
use tests\helpers\Mock\WampComponent as TestComponent;

/**
 * @covers \Reamp\Wamp\ServerProtocol
 * @covers \Reamp\Wamp\WampServerInterface
 * @covers \Reamp\Wamp\WampConnection
 */
class ServerProtocolTest extends TestCase {
    protected $_comp;

    protected $_app;

    public function setUp() {
        $this->_app  = new TestComponent;
        $this->_comp = new ServerProtocol($this->_app);
    }

    protected function newConn() {
        return new Connection;
    }

    public function invalidMessageProvider() {
        return [
            [0], [3], [4], [8], [9]
        ];
    }

    /**
     * @dataProvider invalidMessageProvider
     */
    public function testInvalidMessages($type) {
        $this->expectException(Exception::class);

        $conn = $this->newConn();
        $this->_comp->onOpen($conn);
        $this->_comp->onMessage($conn, \json_encode([$type]));
    }

    public function testWelcomeMessage() {
        $conn = $this->newConn();

        $this->_comp->onOpen($conn);

        $message = $conn->last['send'];
        $json    = \json_decode($message);

        $this->assertEquals(4, \count($json));
        $this->assertEquals(0, $json[0]);
        $this->assertInternalType('string', $json[1]);
        $this->assertEquals(1, $json[2]);
    }

    public function testSubscribe() {
        $uri = 'http://example.com';
        $clientMessage = [5, $uri];

        $conn = $this->newConn();

        $this->_comp->onOpen($conn);
        $this->_comp->onMessage($conn, \json_encode($clientMessage));

        $this->assertEquals($uri, $this->_app->last['onSubscribe'][1]);
    }

    public function testUnSubscribe() {
        $uri = 'http://example.com/endpoint';
        $clientMessage = [6, $uri];

        $conn = $this->newConn();

        $this->_comp->onOpen($conn);
        $this->_comp->onMessage($conn, \json_encode($clientMessage));

        $this->assertEquals($uri, $this->_app->last['onUnSubscribe'][1]);
    }

    public function callProvider() {
        return [
            [2, 'a', 'b'], [2, ['a', 'b']], [1, 'one'], [3, 'one', 'two', 'three'], [3, ['un', 'deux', 'trois']], [2, 'hi', ['hello', 'world']], [2, ['hello', 'world'], 'hi'], [2, ['hello' => 'world', 'herp' => 'derp']]
        ];
    }

    /**
     * @dataProvider callProvider
     */
    public function testCall() {
        $args     = \func_get_args();
        $paramNum = \array_shift($args);

        $uri = 'http://example.com/endpoint/' . \rand(1, 100);
        $id  = \uniqid('', false);
        $clientMessage = \array_merge([2, $id, $uri], $args);

        $conn = $this->newConn();

        $this->_comp->onOpen($conn);
        $this->_comp->onMessage($conn, \json_encode($clientMessage));

        $this->assertEquals($id, $this->_app->last['onCall'][1]);
        $this->assertEquals($uri, $this->_app->last['onCall'][2]);

        $this->assertEquals($paramNum, \count($this->_app->last['onCall'][3]));
    }

    public function testPublish() {
        $conn = $this->newConn();

        $topic = 'pubsubhubbub';
        $event = 'Here I am, publishing data';

        $clientMessage = [7, $topic, $event];

        $this->_comp->onOpen($conn);
        $this->_comp->onMessage($conn, \json_encode($clientMessage));

        $this->assertEquals($topic, $this->_app->last['onPublish'][1]);
        $this->assertEquals($event, $this->_app->last['onPublish'][2]);
        $this->assertEquals([], $this->_app->last['onPublish'][3]);
        $this->assertEquals([], $this->_app->last['onPublish'][4]);
    }

    public function testPublishAndExcludeMe() {
        $conn = $this->newConn();

        $this->_comp->onOpen($conn);
        $this->_comp->onMessage($conn, \json_encode([7, 'topic', 'event', true]));

        $this->assertEquals($conn->WAMP->sessionId, $this->_app->last['onPublish'][3][0]);
    }

    public function testPublishAndEligible() {
        $conn = $this->newConn();

        $buddy  = \uniqid('', false);
        $friend = \uniqid('', false);

        $this->_comp->onOpen($conn);
        $this->_comp->onMessage($conn, \json_encode([7, 'topic', 'event', false, [$buddy, $friend]]));

        $this->assertEquals([], $this->_app->last['onPublish'][3]);
        $this->assertEquals(2, \count($this->_app->last['onPublish'][4]));
    }

    public function eventProvider() {
        return [
            ['http://example.com', ['one', 'two']], ['curie', [['hello' => 'world', 'herp' => 'derp']]]
        ];
    }

    /**
     * @dataProvider eventProvider
     */
    public function testEvent($topic, $payload) {
        $conn = new WampConnection($this->newConn());
        $conn->event($topic, $payload);

        $eventString = $conn->last['send'];

        $this->assertSame([8, $topic, $payload], \json_decode($eventString, true));
    }

    public function testOnClosePropagation() {
        $conn = new Connection;

        $this->_comp->onOpen($conn);
        $this->_comp->onClose($conn);

        $class  = new \ReflectionClass(WampConnection::class);
        $method = $class->getMethod('getConnection');
        $method->setAccessible(true);

        $check = $method->invokeArgs($this->_app->last['onClose'][0], []);

        $this->assertSame($conn, $check);
    }

    public function testOnErrorPropagation() {
        $conn = new Connection;

        $e = new \Exception('Nope');

        $this->_comp->onOpen($conn);
        $this->_comp->onError($conn, $e);

        $class  = new \ReflectionClass(WampConnection::class);
        $method = $class->getMethod('getConnection');
        $method->setAccessible(true);

        $check = $method->invokeArgs($this->_app->last['onError'][0], []);

        $this->assertSame($conn, $check);
        $this->assertSame($e, $this->_app->last['onError'][1]);
    }

    public function testPrefix() {
        $conn = new WampConnection($this->newConn());
        $this->_comp->onOpen($conn);

        $prefix  = 'incoming';
        $fullURI   = "http://example.com/$prefix";
        $method = 'call';

        $this->_comp->onMessage($conn, \json_encode([1, $prefix, $fullURI]));

        $this->assertEquals($fullURI, $conn->WAMP->prefixes[$prefix]);
        $this->assertEquals("$fullURI#$method", $conn->getUri("$prefix:$method"));
    }

    public function testMessageMustBeJson() {
        $this->expectException(JsonException::class);

        $conn = new Connection;

        $this->_comp->onOpen($conn);
        $this->_comp->onMessage($conn, 'Hello World!');
    }

    public function testGetSubProtocolsReturnsArray() {
        $this->assertInternalType('array', $this->_comp->getSubProtocols());
    }

    public function testGetSubProtocolsGetFromApp() {
        $this->_app->protocols = ['hello', 'world'];

        $this->assertGreaterThanOrEqual(3, \count($this->_comp->getSubProtocols()));
    }

    public function testWampOnMessageApp() {
        $app = $this->createMock(WampServerInterface::class);
        $wamp = new ServerProtocol($app);

        $this->assertContains('wamp', $wamp->getSubProtocols());
    }

    public function badFormatProvider() {
        return [
            [\json_encode(true)], ['{"valid":"json", "invalid": "message"}'], ['{"0": "fail", "hello": "world"}']
        ];
    }

    /**
     * @dataProvider badFormatProvider
     */
    public function testValidJsonButInvalidProtocol($message) {
        $this->expectException(Exception::class);

        $conn = $this->newConn();
        $this->_comp->onOpen($conn);
        $this->_comp->onMessage($conn, $message);
    }

    public function testBadClientInputFromNonStringTopic() {
        $this->expectException(Exception::class);

        $conn = new WampConnection($this->newConn());
        $this->_comp->onOpen($conn);

        $this->_comp->onMessage($conn, \json_encode([5, ['hells', 'nope']]));
    }

    public function testBadPrefixWithNonStringTopic() {
        $this->expectException(Exception::class);

        $conn = new WampConnection($this->newConn());
        $this->_comp->onOpen($conn);

        $this->_comp->onMessage($conn, \json_encode([1, ['hells', 'nope'], ['bad', 'input']]));
    }

    public function testBadPublishWithNonStringTopic() {
        $this->expectException(Exception::class);

        $conn = new WampConnection($this->newConn());
        $this->_comp->onOpen($conn);

        $this->_comp->onMessage($conn, \json_encode([7, ['bad', 'input'], 'Hider']));
    }
}
