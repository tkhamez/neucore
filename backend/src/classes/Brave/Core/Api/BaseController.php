<?php declare(strict_types=1);

namespace Brave\Core\Api;

use Brave\Core\Service\ObjectManager;
use Slim\Http\Response;
use Swagger\Annotations as SWG;

/**
 * @SWG\Swagger(
 *     schemes={"https", "http"},
 *     host="localhost",
 *     basePath="/api",
 *     produces={"application/json"},
 *     @SWG\Info(
 *       title="Neucore API",
 *       description="Client library of Neucore API",
 *       version=BRAVE_CORE_VERSION
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
