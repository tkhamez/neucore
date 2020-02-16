<?php declare(strict_types=1);

namespace Neucore\Middleware\Psr15;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CORS headers.
 *
 * Checks the HTTP_ORIGIN request header and, if it matches one of the allowed
 * origins, adds Access-Control-Allow-* headers to the response.
 */
class Cors implements MiddlewareInterface
{
    /**
     * @var array
     */
    private $allowOrigin;

    /**
     * @param array $allowOrigin Example: ['https://frontend.domain.tld']
     */
    public function __construct(array $allowOrigin)
    {
        $this->allowOrigin = $allowOrigin;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        $origin = $request->getHeader('HTTP_ORIGIN')[0] ?? null;
        if ($origin !== null && in_array($origin, $this->allowOrigin)) {
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $origin)
                #->withHeader('Access-Control-Allow-Headers', 'Authorization')
                ->withHeader('Access-Control-Allow-Credentials', 'true')
            ;
        }

        return $response;
    }
}
