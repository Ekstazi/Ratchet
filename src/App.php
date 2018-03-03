<?php

namespace Reamp;

use Reamp\Http\HttpServer;
use Reamp\Http\HttpServerInterface;
use Reamp\Http\OriginCheck;
use Reamp\Http\Router;
use Reamp\MessageComponentInterface as DataComponentInterface;
use Reamp\Server\IoServer;
use Reamp\Wamp\WampServer;
use Reamp\Wamp\WampServerInterface;
use Reamp\WebSocket\MessageComponentInterface;
use Reamp\WebSocket\WsServer;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * An opinionated facade class to quickly and easily create a WebSocket server.
 * A few configuration assumptions are made and some best-practice security conventions are applied by default.
 */
class App {
    /**
     * @var \Symfony\Component\Routing\RouteCollection
     */
    public $routes;


    /**
     * @var \Reamp\Server\IoServer
     */
    protected $_server;

    /**
     * The Host passed in construct used for same origin policy.
     * @var string
     */
    protected $httpHost;

    /***
     * The port the socket is listening
     * @var int
     */
    protected $port;

    /**
     * @var int
     */
    protected $_routeCounter = 0;

    /**
     * @param string        $httpHost   HTTP hostname clients intend to connect to. MUST match JS `new WebSocket('ws://$httpHost');`
     * @param int           $port       Port to listen on. If 80, assuming production, Flash on 843 otherwise expecting Flash to be proxied through 8843
     * @param string        $address    IP address to bind to. Default is localhost/proxy only. '0.0.0.0' for any machine.
     */
    public function __construct($httpHost = 'localhost', $port = 8080, $address = '127.0.0.1') {
        if (\extension_loaded('xdebug')) {
            \trigger_error('XDebug extension detected. Remember to disable this if performance testing or going live!', E_USER_WARNING);
        }

        $this->httpHost = $httpHost;
        $this->port = $port;

        $socket = \Amp\Socket\listen($address . ':' . $port);

        $this->routes  = new RouteCollection;
        $this->_server = new IoServer(new HttpServer(new Router(new UrlMatcher($this->routes, new RequestContext))), $socket);
    }

    /**
     * Add an endpoint/application to the server.
     * @param string             $path The URI the client will connect to
     * @param ComponentInterface $controller Your application to server for the route. If not specified, assumed to be for a WebSocket
     * @param array              $allowedOrigins An array of hosts allowed to connect (same host by default), ['*'] for any
     * @param string             $httpHost Override the $httpHost variable provided in the __construct
     * @return ComponentInterface|WsServer
     */
    public function route($path, ComponentInterface $controller, array $allowedOrigins = [], $httpHost = null) {
        if ($controller instanceof HttpServerInterface || $controller instanceof WsServer) {
            $decorated = $controller;
        } elseif ($controller instanceof WampServerInterface) {
            $decorated = new WsServer(new WampServer($controller));
            $decorated->enableKeepAlive();
        } elseif ($controller instanceof MessageComponentInterface || $controller instanceof DataComponentInterface) {
            $decorated = new WsServer($controller);
            $decorated->enableKeepAlive();
        } else {
            $decorated = $controller;
        }

        if ($httpHost === null) {
            $httpHost = $this->httpHost;
        }

        $allowedOrigins = \array_values($allowedOrigins);
        if (0 === \count($allowedOrigins)) {
            $allowedOrigins[] = $httpHost;
        }
        if ('*' !== $allowedOrigins[0]) {
            $decorated = new OriginCheck($decorated, $allowedOrigins);
        }

        $this->routes->add(
            'rr-' . ++$this->_routeCounter,
            new Route(
                $path,
                ['_controller' => $decorated],
                ['Origin' => $this->httpHost],
                [],
                $httpHost,
                [],
                ['GET']
            )
        );

        return $decorated;
    }

    /**
     * Run all server instances.
     */
    public static function run() {
        IoServer::run();
    }
}
