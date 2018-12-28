<?php declare(strict_types=1);

namespace Tests;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Client extends \GuzzleHttp\Client
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
        $response = array_shift($this->responses);
        #var_Dump($request->getUri()->getPath());
        return $response;
    }

    public function request($method, $uri = '', array $options = []): ResponseInterface
    {
        return array_shift($this->responses);
    }
}
