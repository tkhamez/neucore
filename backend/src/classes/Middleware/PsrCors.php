<?php declare(strict_types=1);

namespace Brave\Core\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * CORS headers.
 *
 * Checks HTTP_ORIGIN request header and if it matches one of the allow
 * origins, adds Access-Control-Allow-* headers to the response.
 */
class PsrCors
{
    private $allowOrigin;

    /**
     *
     * Option (required): allowed origins
     *
     * Example:
     * ['https://frontend.domain.tld']
     */
    public function __construct(array $allowOrigin = [])
    {
        $this->allowOrigin = $allowOrigin;
    }

    public function __invoke(ServerRequestInterface $req, ResponseInterface $res, callable $next): ResponseInterface
    {
        /** @var ResponseInterface $response */
        $response = $next($req, $res);

        $origin = $req->getHeader('HTTP_ORIGIN')[0] ?? null;
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
