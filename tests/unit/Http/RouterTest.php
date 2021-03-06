<?php

namespace tests\Http;

use PHPUnit\Framework\Constraint\IsInstanceOf;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Reamp\ConnectionInterface;
use Reamp\Http\HttpServer;
use Reamp\Http\Router;
use Reamp\WebSocket\WsServer;
use Reamp\WebSocket\WsServerInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use tests\helpers\Mock\Connection;

/**
 * @covers \Reamp\Http\Router
 */
class RouterTest extends TestCase {
    protected $_router;
    protected $_matcher;
    protected $_conn;
    protected $_uri;
    protected $_req;

    public function setUp() {
        $this->_conn = $this->createMock(ConnectionInterface::class);
        $this->_uri  = $this->createMock(UriInterface::class);
        $this->_req  = $this->createMock(ServerRequestInterface::class);
        $this->_req
            ->expects($this->any())
            ->method('getUri')
            ->will($this->returnValue($this->_uri));
        $this->_matcher = $this->createMock(UrlMatcherInterface::class);
        $this->_matcher
            ->expects($this->any())
            ->method('getContext')
            ->will($this->returnValue($this->createMock(RequestContext::class)));
        $this->_router  = new Router($this->_matcher);

        $this->_uri->expects($this->any())->method('getPath')->will($this->returnValue('ws://doesnt.matter/'));

        $query = [];
        $this->_uri->expects($this->any())->method('withQuery')->with($this->callback(function ($val) use (&$query) {
            $query = $val;
            return true;
        }))->will($this->returnSelf());
        $this->_uri->expects($this->any())->method('getQuery')->will($this->returnCallback(function () use (&$query) {
            return $query;
        }));
        $this->_req->expects($this->any())->method('withUri')->will($this->returnSelf());

        $queryParams = [];
        $this->_req->expects($this->any())->method('getQueryParams')->will($this->returnCallback(function () use (&$queryParams) {
            return $queryParams;
        }));
        $this->_req->expects($this->any())->method('withQueryParams')->with($this->callback(function ($params) use (&$queryParams) {
            $queryParams = $params;
            return true;
        }))->will($this->returnSelf());
    }

    public function testFourOhFour() {
        $this->_conn->expects($this->once())->method('close');

        $nope = new ResourceNotFoundException;
        $this->_matcher->expects($this->any())->method('match')->will($this->throwException($nope));

        $this->_router->onOpen($this->_conn, $this->_req);
    }

    public function testNullRequest() {
        $this->expectException('\UnexpectedValueException');
        $this->_router->onOpen($this->_conn);
    }

    public function testControllerIsMessageComponentInterface() {
        $this->expectException('\UnexpectedValueException');
        $this->_matcher->expects($this->any())->method('match')->will($this->returnValue(['_controller' => new \stdClass]));
        $this->_router->onOpen($this->_conn, $this->_req);
    }

    public function testControllerOnOpen() {
        $controller = $this->getMockBuilder(WsServer::class)->disableOriginalConstructor()->getMock();
        $this->_matcher->expects($this->any())->method('match')->will($this->returnValue(['_controller' => $controller]));
        $this->_router->onOpen($this->_conn, $this->_req);

        $expectedConn = new IsInstanceOf(ConnectionInterface::class);
        $controller->expects($this->once())->method('onOpen')->with($expectedConn, $this->_req);

        $this->_matcher->expects($this->any())->method('match')->will($this->returnValue(['_controller' => $controller]));
        $this->_router->onOpen($this->_conn, $this->_req);
    }

    public function testControllerOnMessageBubbles() {
        $message = "The greatest trick the Devil ever pulled was convincing the world he didn't exist";
        $controller = $this->getMockBuilder(WsServer::class)->disableOriginalConstructor()->getMock();
        $controller->expects($this->once())->method('onMessage')->with($this->_conn, $message);

        $this->_conn->controller = $controller;

        $this->_router->onMessage($this->_conn, $message);
    }

    public function testControllerOnCloseBubbles() {
        $controller = $this->getMockBuilder(WsServer::class)->disableOriginalConstructor()->getMock();
        $controller->expects($this->once())->method('onClose')->with($this->_conn);

        $this->_conn->controller = $controller;

        $this->_router->onClose($this->_conn);
    }

    public function testControllerOnErrorBubbles() {
        $e= new \Exception('One cannot be betrayed if one has no exceptions');
        $controller = $this->getMockBuilder(WsServer::class)->disableOriginalConstructor()->getMock();
        $controller->expects($this->once())->method('onError')->with($this->_conn, $e);

        $this->_conn->controller = $controller;

        $this->_router->onError($this->_conn, $e);
    }

    public function testRouterGeneratesRouteParameters() {
        /** @var $controller WsServerInterface */
        $controller = $this->getMockBuilder(WsServer::class)->disableOriginalConstructor()->getMock();
        /** @var $matcher UrlMatcherInterface */
        $this->_matcher->expects($this->any())->method('match')->will(
            $this->returnValue(['_controller' => $controller, 'foo' => 'bar', 'baz' => 'qux'])
        );
        $conn = $this->createMock(Connection::class);

        $router = new Router($this->_matcher);

        $router->onOpen($conn, $this->_req);

        $this->assertEquals(\GuzzleHttp\Psr7\parse_query('foo=bar&baz=qux'), $this->_req->getQueryParams());
    }

    public function testQueryParams() {
        $controller = $this->getMockBuilder(WsServer::class)->disableOriginalConstructor()->getMock();
        $this->_matcher->expects($this->any())->method('match')->will(
            $this->returnValue(['_controller' => $controller, 'foo' => 'bar', 'baz' => 'qux'])
        );

        $conn    = $this->createMock(Connection::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $uri = new \GuzzleHttp\Psr7\Uri('ws://doesnt.matter/endpoint?hello=world&foo=nope');
        $queryParams = \GuzzleHttp\Psr7\parse_query($uri->getQuery());

        $request->expects($this->any())->method('getQueryParams')->will($this->returnCallback(function () use (&$queryParams) {
            return $queryParams;
        }));

        $request->expects($this->any())->method('getUri')->will($this->returnCallback(function () use (&$uri) {
            return $uri;
        }));
        $request->expects($this->any())->method('withQueryParams')->with($this->callback(function ($params) use (&$queryParams) {
            $queryParams = $params;

            return true;
        }))->will($this->returnSelf());

        $router = new Router($this->_matcher);
        $router->onOpen($conn, $request);

        $this->assertEquals(\GuzzleHttp\Psr7\parse_query('foo=nope&baz=qux&hello=world'), $request->getQueryParams());
        $this->assertEquals('ws', $request->getUri()->getScheme());
        $this->assertEquals('doesnt.matter', $request->getUri()->getHost());
    }

    public function testImpatientClientOverflow() {
        $this->_conn->expects($this->once())->method('close');

        $header = "GET /nope HTTP/1.1
Upgrade: websocket                                   
Connection: upgrade                                  
Host: localhost                                 
Origin: http://localhost                        
Sec-WebSocket-Version: 13\r\n\r\n";

        $app = new HttpServer(new Router(new UrlMatcher(new RouteCollection, new RequestContext)));
        $app->onOpen($this->_conn);
        $app->onMessage($this->_conn, $header);
        $app->onMessage($this->_conn, 'Silly body');
    }
}
