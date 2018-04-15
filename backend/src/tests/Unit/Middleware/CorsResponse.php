<?php
namespace Tests\Unit\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class CorsResponse implements ResponseInterface
{
    private $headers;

    public function __construct($headers = [])
    {
        $this->headers = $headers;
    }

    public function withStatus($code, $reasonPhrase = '')
    {
    }

    public function hasHeader($name)
    {
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getBody()
    {
    }

    public function withProtocolVersion($version)
    {
    }

    public function withoutHeader($name)
    {
    }

    public function getHeaderLine($name)
    {
    }

    public function withHeader($name, $value)
    {
        $headers = $this->headers;
        $headers[$name][] = $value;

        return new CorsResponse($headers);
    }

    public function withBody(StreamInterface $body)
    {
    }

    public function getReasonPhrase()
    {
    }

    public function getHeader($name)
    {
    }

    public function getProtocolVersion()
    {
    }

    public function getStatusCode()
    {
    }

    public function withAddedHeader($name, $value)
    {
    }
}
