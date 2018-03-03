<?php

namespace Reamp\Http;

use Psr\Http\Message\ServerRequestInterface;
use Reamp\ConnectionInterface;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;

class Router implements HttpServerInterface {
    use CloseResponseTrait;

    /**
     * @var \Symfony\Component\Routing\Matcher\UrlMatcherInterface
     */
    protected $_matcher;

    private $_noopController;

    public function __construct(UrlMatcherInterface $matcher) {
        $this->_matcher = $matcher;
        $this->_noopController = new NoOpHttpServerController;
    }

    /**
     * {@inheritdoc}
     * @throws \UnexpectedValueException If a controller is not \Reamp\Http\HttpServerInterface
     */
    public function onOpen(ConnectionInterface $conn, ServerRequestInterface $request = null) {
        if (null === $request) {
            throw new \UnexpectedValueException('$request can not be null');
        }

        $conn->controller = $this->_noopController;

        $uri = $request->getUri();

        $context = $this->_matcher->getContext();
        $context->setMethod($request->getMethod());
        $context->setHost($uri->getHost());

        try {
            $route = $this->_matcher->match($uri->getPath());
        } catch (MethodNotAllowedException $nae) {
            return $this->close($conn, 405, ['Allow' => $nae->getAllowedMethods()]);
        } catch (ResourceNotFoundException $nfe) {
            return $this->close($conn, 404);
        }

        if (\is_string($route['_controller']) && \class_exists($route['_controller'])) {
            $route['_controller'] = new $route['_controller'];
        }

        if (!($route['_controller'] instanceof HttpServerInterface)) {
            throw new \UnexpectedValueException('All routes must implement Reamp\Http\HttpServerInterface');
        }

        $parameters = [];
        foreach ($route as $key => $value) {
            if ((\is_string($key)) && ('_' !== \substr($key, 0, 1))) {
                $parameters[$key] = $value;
            }
        }
        $parameters = \array_merge($parameters, $request->getQueryParams());

        $request = $request->withQueryParams($parameters);

        $conn->controller = $route['_controller'];
        // proxy component handler onOpen so it can use async or sync context
        return $conn->controller->onOpen($conn, $request);
    }

    /**
     * {@inheritdoc}
     */
    public function onMessage(ConnectionInterface $from, $msg) {
        // proxy component handler onOpen so it can use async or sync context
        return $from->controller->onMessage($from, $msg);
    }

    /**
     * {@inheritdoc}
     */
    public function onClose(ConnectionInterface $conn) {
        if (isset($conn->controller)) {
            // proxy component handler onOpen so it can use async or sync context
            return $conn->controller->onClose($conn);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function onError(ConnectionInterface $conn, \Throwable $e) {
        if (isset($conn->controller)) {
            // proxy component handler onOpen so it can use async or sync context
            return $conn->controller->onError($conn, $e);
        }
    }
}
