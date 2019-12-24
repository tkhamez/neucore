<?php

declare(strict_types=1);

namespace Tests;

use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Client extends \GuzzleHttp\Client
{
    /**
     * @var ResponseInterface[]
     */
    private $responses = [];

    /**
     * @var array
     */
    private $middleware;

    public function __construct(array $middleware = [])
    {
        parent::__construct();
        $this->middleware = $middleware;
    }

    public function setResponse(ResponseInterface ...$responses): void
    {
        $this->responses = $responses;
    }

    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        $response = array_shift($this->responses);
        $this->callMiddleware($request, $response);
        return $response;
    }

    public function request($method, $uri = '', array $options = []): ResponseInterface
    {
        $response = array_shift($this->responses);
        $this->callMiddleware(new Request($method, $uri), $response);
        return $response;
    }

    private function callMiddleware(RequestInterface $request, ResponseInterface $response)
    {
        foreach ($this->middleware as $callable) {
            $promise = new class($response) {
                private $response;
                public function __construct($response)
                {
                    $this->response = $response;
                }
                public function then(callable $onFulfilled)
                {
                    $onFulfilled($this->response);
                }
            };

            $function = $callable(function () use ($promise) {
                return $promise;
            });

            $function($request, []);
        }
    }
}
