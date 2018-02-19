<?php

namespace Reamp\Http;

use GuzzleHttp\Psr7 as gPsr;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Reamp\ConnectionInterface;
use Reamp\MessageInterface;

/**
 * This class receives streaming data from a client request
 * and parses HTTP headers, returning a PSR-7 Request object
 * once it's been buffered.
 */
class HttpRequestParser implements MessageInterface {
    const EOM = "\r\n\r\n";

    /**
     * The maximum number of bytes the request can be
     * This is a security measure to prevent attacks.
     * @var int
     */
    public $maxSize = 4096;

    /**
     * @param \Reamp\ConnectionInterface $context
     * @param string $data Data stream to buffer
     * @return \Psr\Http\Message\RequestInterface
     * @throws \OverflowException If the message buffer has become too large
     */
    public function onMessage(ConnectionInterface $context, $data) {
        if (!isset($context->httpBuffer)) {
            $context->httpBuffer = '';
        }

        $context->httpBuffer .= $data;

        if (\strlen($context->httpBuffer) > (int) $this->maxSize) {
            throw new \OverflowException("Maximum buffer size of {$this->maxSize} exceeded parsing HTTP header");
        }

        if ($this->isEom($context->httpBuffer)) {
            $request = $this->parse($context->httpBuffer);

            unset($context->httpBuffer);

            return $request;
        }
    }

    /**
     * Determine if the message has been buffered as per the HTTP specification.
     * @param  string $message
     * @return boolean
     */
    public function isEom($message) {
        return (bool) \strpos($message, static::EOM);
    }

    /**
     * @param string $headers
     * @param array $serverParams
     * @return \Psr\Http\Message\RequestInterface
     */
    public function parse($headers, $serverParams = []) {
        $request = gPsr\parse_request($headers);
        // @todo support original target and asterisk in request uri
        // create new obj implementing ServerRequestInterface by preserving all
        // previous properties and restoring original request-target
        $serverRequest = new gPsr\ServerRequest(
            $request->getMethod(),
            $request->getUri(),
            $request->getHeaders(),
            null,
            $request->getProtocolVersion(),
            $serverParams
        );

        $this->validateRequestTarget($request);
        $this->validateHost($request);

        $serverRequest = $serverRequest
            ->withCookieParams($this->parseCookies($serverRequest))
            ->withQueryParams($this->parseQueryParams($serverRequest))
            ->withUri(
                $this
                    // set URI components from socket address if not already filled via Host header
                    ->uriWithHost(
                        $serverRequest->getUri(),
                        $serverParams['SERVER_ADDR'] ?? '127.0.0.1',
                        $serverParams['SERVER_PORT'] ?? 80
                    )
                    // Do not assume this is HTTPS when this happens to be port 443
                    // detecting HTTPS is left up to the socket layer (TLS detection)
                    // i.e. server params
                    ->withScheme(($serverParams['HTTPS'] ?? null) ? 'https' : 'http')
                    // always sanitize Host header because it contains critical routing information
                    ->withUserInfo('', '')
            );
        return $serverRequest;
    }

    /**
     * @param $request
     * @throws \Exception
     */
    protected function validateRequestTarget(RequestInterface $request) {
        // ensure absolute-form request-target contains a valid URI
        $requestTarget = $request->getRequestTarget();
        if (\strpos($requestTarget, '://') !== false && \substr($requestTarget, 0, 1) !== '/') {
            $parts = \parse_url($requestTarget);

            // make sure value contains valid host component (IP or hostname), but no fragment
            if (!isset($parts['scheme'], $parts['host']) || $parts['scheme'] !== 'http' || isset($parts['fragment'])) {
                throw new \Exception('Invalid absolute-form request-target');
            }
        }
    }

    /**
     * Optional Host header value MUST be valid (host and optional port).
     * @param $request
     * @throws \Exception
     */
    protected function validateHost(RequestInterface $request) {
        if (!$request->hasHeader('Host')) {
            return;
        }
        $parts = \parse_url('http://' . $request->getHeaderLine('Host'));

        // make sure value contains valid host component (IP or hostname)
        if (!$parts || !isset($parts['scheme'], $parts['host'])) {
            $parts = false;
        }

        // make sure value does not contain any other URI component
        unset($parts['scheme'], $parts['host'], $parts['port']);
        if ($parts === false || $parts) {
            throw new \Exception('Invalid Host header value');
        }
    }

    /**
     * @param ServerRequestInterface $request
     * @return array
     */
    protected function parseCookies(ServerRequestInterface $request) {
        $cookie = $request->getHeaderLine("Cookie");
        // PSR-7 `getHeaderLine('Cookies')` will return multiple
        // cookie header comma-separated. Multiple cookie headers
        // are not allowed according to https://tools.ietf.org/html/rfc6265#section-5.4
        if (\strpos($cookie, ',') !== false) {
            return [];
        }

        $cookieArray = \explode(';', $cookie);
        $result = [];

        foreach ($cookieArray as $pair) {
            $pair = \trim($pair);
            $nameValuePair = \explode('=', $pair, 2);

            if (\count($nameValuePair) === 2) {
                $key = \urldecode($nameValuePair[0]);
                $value = \urldecode($nameValuePair[1]);
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * @param RequestInterface $request
     * @return array
     */
    protected function parseQueryParams(RequestInterface $request) {
        $queryString = $request->getUri()->getQuery();
        if ($queryString === '') {
            return [];
        }

        $queryParams = [];
        \parse_str($queryString, $queryParams);
        return $queryParams;
    }

    /**
     *
     * @param UriInterface $uri
     * @param string $serverAddress
     * @param  string $serverPort
     * @return UriInterface
     */
    protected function uriWithHost(UriInterface $uri, $serverAddress, $serverPort) {
        // set URI components from socket address if not already filled via Host header
        if ($uri->getHost() !== '') {
            return $uri;
        }

        return $uri->withScheme('http')->withHost($serverAddress)->withPort($serverPort);
    }

    protected function getServerParams(ConnectionInterface $connection) {
        $serverParams = [
            'REQUEST_TIME'       => \time(),
            'REQUEST_TIME_FLOAT' => \microtime(true)
        ];

        $remoteAddress = \parse_url($connection->getRemoteAddress());
        $serverParams['REMOTE_ADDR'] = $remoteAddress['host'];
        $serverParams['REMOTE_PORT'] = $remoteAddress['port'];

        $localAddress = \parse_url($connection->getLocalAddress());
        $serverParams['SERVER_ADDR'] = $localAddress['host'];
        $serverParams['SERVER_PORT'] = $localAddress['port'];

        // @todo check
        if ($localAddress['scheme'] == 'tls') {
            $serverParams['HTTPS'] = 'on';
        }
        return $serverParams;
    }
}
