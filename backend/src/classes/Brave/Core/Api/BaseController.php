<?php declare(strict_types=1);

namespace Brave\Core\Api;

use Brave\Core\Service\ObjectManager;
use Slim\Http\Response;

/**
 * @SWG\Swagger(
 *     schemes={"https", "http"},
 *     basePath="/api",
 *     @SWG\Info(
 *       title="Brave Collective Core Services API",
 *       description="Client library of Brave Collective Core Services API",
 *       version="0.6.0"
 *     ),
 *     @SWG\SecurityScheme(
 *         securityDefinition="Bearer",
 *         type="apiKey",
 *         name="Authorization",
 *         in="header",
 *         description="Example: Bearer ABC"
 *     ),
 *     @SWG\SecurityScheme(
 *         securityDefinition="Session",
 *         type="apiKey",
 *         name="Cookie",
 *         in="header",
 *         description="Example: BCSESS=123"
 *     )
 * )
 */
abstract class BaseController
{
    /**
     * @var Response
     */
    protected $response;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    public function __construct(Response $response, ObjectManager $objectManager)
    {
        $this->response = $response;
        $this->objectManager = $objectManager;
    }

    /**
     * @param int $status
     * @param mixed|null $json
     * @return Response
     */
    protected function flushAndReturn(int $status, $json = null): Response
    {
        if (! $this->objectManager->flush()) {
            return $this->response->withStatus(500);
        }

        $response = $this->response->withStatus($status);
        if ($json !== null) {
            return $response->withJson($json);
        } else {
            return $response;
        }
    }
}
