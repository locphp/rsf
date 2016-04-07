<?php

namespace Rsf\Http;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

class Request implements ServerRequestInterface {

    use MessageTrait;

    protected $server;
    protected $get;
    protected $post;
    protected $files;
    protected $cookies;
    protected $method;
    protected $uri;
    protected $allow_client_proxy_ip = false;

    public function __construct($server, $headers, $get, $post, $files, $cookies) {
        $this->server = $server;
        $this->headers = $headers;

        $this->get = $get;
        $this->post = $post;
        $this->files = $files;
        $this->cookies = $cookies;

        $this->body = new ResourceStream(fopen('php://input', 'r'));
    }

    public function getRequestTarget() {
        return isset($this->server['REQUEST_URI']) ? $this->server['REQUEST_URI'] : '/';
    }

    public function withRequestTarget($requestTarget) {
        $result = clone $this;
        $result->server['REQUEST_URI'] = $requestTarget;
        return $result;
    }

    public function getMethod() {
        if ($this->method !== null) {
            return $this->method;
        }
        $method = isset($this->server['REQUEST_METHOD']) ? strtoupper($this->server['REQUEST_METHOD']) : 'GET';
        if ($method !== 'POST') {
            return $this->method = $method;
        }
        $override = $this->getHeader('x-http-method-override') ?: $this->post('_method');
        if ($override) {
            if (is_array($override)) {
                $override = array_shift($override);
            }
            $method = $override;
        }
        return $this->method = strtoupper($method);
    }

    public function withMethod($method) {
        $result = clone $this;
        $result->method = strtoupper($method);

        return $result;
    }

    public function getUri() {
        if ($this->uri) {
            return $this->uri;
        }
        $scheme = $this->getServerParam('HTTPS') ? 'https' : 'http';
        $user = $this->getServerParam('PHP_AUTH_USER');
        $password = $this->getServerParam('PHP_AUTH_PW');
        $host = $this->getServerParam('SERVER_NAME') ?: $this->getServerParam('SERVER_ADDR') ?: '127.0.0.1';
        $port = $this->getServerParam('SERVER_PORT');
        return $this->uri = (new Uri($this->getRequestTarget()))
            ->withScheme($scheme)
            ->withUserInfo($user, $password)
            ->withHost($host)
            ->withPort($port);
    }

    public function withUri(UriInterface $uri, $preserveHost = false) {
        throw new \Exception('Request::withUri() not implemented');
    }

    public function getServerParams() {
        return $this->server;
    }

    public function getCookieParams() {
        return $this->cookies;
    }

    public function withCookieParams(array $cookies) {
        $result = clone $this;
        $result->cookies = $cookies;
        return $result;
    }

    public function getQueryParams() {
        return $this->get;
    }

    public function withQueryParams(array $query) {
        $result = clone $this;
        $result->get = $query;
        return $result;
    }

    public function getUploadedFiles() {
        return $this->files;
    }

    public function withUploadedFiles(array $uploadFiles) {
        throw new \Exception('Request::withUploadedFiles() not implemented');
    }

    public function getParsedBody() {
        $content_type = $this->getHeaderLine('content-type');
        $method = $this->getServerParam('REQUEST_METHOD');
        if ($method === 'POST' && ($content_type === 'application/x-www-form-urlencoded' || $content_type === 'multipart/form-data')) {
            return $this->post;
        }
        $body = (string)$this->body;
        if ($body === '') {
            return;
        }
        if ($content_type === 'application/json') {
            return json_decode($body, true);
        }
        return $body;
    }

    public function withParsedBody($data) {
        throw new \Exception('Request::withParsedBody() not implemented');
    }

}
