<?php declare(strict_types=1);

namespace Tests;

use GuzzleHttp\Client;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class TestClient extends Client
{
    /**
     * @var ResponseInterface[]
     */
    private $responses = [];

    public function setResponse(ResponseInterface ...$responses): void
    {
        $this->responses = $responses;
    }

    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        return array_shift($this->responses);
    }

    public function request($method, $uri = '', array $options = []): ResponseInterface
    {
        return array_shift($this->responses);
    }
}
