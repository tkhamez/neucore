<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface;

/**
 * @OA\Tag(
 *     name="Service",
 *     description="Service management."
 * )
 */
class ServiceController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/user/service/{id}",
     *     operationId="serviceGet",
     *     summary="Returns service.",
     *     description="Needs role: user",
     *     tags={"Service"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the service.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="The service.",
     *         @OA\JsonContent(ref="#/components/schemas/Service")
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Service not found."
     *     )
     * )
     */
    public function get(string $id): ResponseInterface
    {
        $service = $this->repositoryFactory->getServiceRepository()->find((int) $id);

        if ($service === null) {
            return $this->response->withStatus(404);
        }

        return $this->withJson($service->jsonSerialize(false));
    }
}
