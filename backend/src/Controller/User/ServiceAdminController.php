<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\Service;
use Neucore\Entity\ServiceConfiguration;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @OA\Tag(
 *     name="ServiceAdmin",
 *     description="Service administration."
 * )
 */
class ServiceAdminController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/user/service-admin/list",
     *     operationId="serviceAdminList",
     *     summary="List all services.",
     *     description="Needs role: service-admin",
     *     tags={"ServiceAdmin"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of services.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Service"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function list(): ResponseInterface
    {
        return $this->withJson($this->repositoryFactory->getServiceRepository()->findBy([], ['name' => 'ASC']));
    }

    /**
     * @OA\Post(
     *     path="/user/service-admin/create",
     *     operationId="serviceAdminCreate",
     *     summary="Create a service.",
     *     description="Needs role: service-admin",
     *     tags={"ServiceAdmin"},
     *     security={{"Session"={}}},
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"name"},
     *                 @OA\Property(
     *                     property="name",
     *                     description="Name of the service.",
     *                     type="string",
     *                     maxLength=255,
     *                 )
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response="201",
     *         description="The new service.",
     *         @OA\JsonContent(ref="#/components/schemas/Service")
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Service name is missing."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $name = $this->getBodyParam($request, 'name', '');
        if (empty($name)) {
            return $this->response->withStatus(400);
        }

        $service = (new Service())->setName($name);
        $this->objectManager->persist($service);

        return $this->flushAndReturn(201, $service);
    }

    /**
     * @OA\Put(
     *     path="/user/service-admin/{id}/rename",
     *     operationId="serviceAdminRename",
     *     summary="Rename a service.",
     *     description="Needs role: service-admin",
     *     tags={"ServiceAdmin"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the service.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"name"},
     *                 @OA\Property(
     *                     property="name",
     *                     description="New name for the service.",
     *                     type="string",
     *                     maxLength=255
     *                 )
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Service was renamed.",
     *         @OA\JsonContent(ref="#/components/schemas/Service")
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Service name is missing."
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
    public function rename(string $id, ServerRequestInterface $request): ResponseInterface
    {
        $service = $this->repositoryFactory->getServiceRepository()->find((int)$id);
        if ($service === null) {
            return $this->response->withStatus(404);
        }

        $name = $this->getBodyParam($request, 'name', '');
        if (empty($name)) {
            return $this->response->withStatus(400);
        }

        $service->setName($name);

        return $this->flushAndReturn(200, $service);
    }

    /**
     * @OA\Delete(
     *     path="/user/service-admin/{id}/delete",
     *     operationId="serviceAdminDelete",
     *     summary="Delete a service.",
     *     description="Needs role: service-admin",
     *     tags={"ServiceAdmin"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the service.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Service was deleted."
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
    public function delete(string $id): ResponseInterface
    {
        $service = $this->repositoryFactory->getServiceRepository()->find((int)$id);
        if ($service === null) {
            return $this->response->withStatus(404);
        }

        $this->objectManager->remove($service);

        return $this->flushAndReturn(204);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/service-admin/{id}/save-configuration",
     *     operationId="serviceAdminSaveConfiguration",
     *     summary="Save the service configuration.",
     *     description="Needs role: service-admin",
     *     tags={"ServiceAdmin"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the service.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(
     *                     property="configuration",
     *                     ref="#/components/schemas/ServiceConfiguration"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Configuration changed.",
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Invalid input."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Variable not found."
     *     )
     * )
     */
    public function saveConfiguration(string $id, ServerRequestInterface $request): ResponseInterface
    {
        $service = $this->repositoryFactory->getServiceRepository()->find((int)$id);
        if ($service === null) {
            return $this->response->withStatus(404);
        }

        $configuration = $this->getBodyParam($request, 'configuration', '');

        if (!is_string($configuration)) {
            return $this->response->withStatus(400);
        }
        $data = \json_decode($configuration, true);
        if (is_array($data)) {
            $service->setConfiguration(ServiceConfiguration::fromArray($data));
        } else {
            return $this->response->withStatus(400);
        }

        return $this->flushAndReturn(204);
    }
}
