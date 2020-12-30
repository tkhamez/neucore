<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\Character;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\ObjectManager;
use Neucore\Service\ServiceRegistration;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * @OA\Tag(
 *     name="Service",
 *     description="Service management."
 * )
 */
class ServiceController extends BaseController
{
    /**
     * @var LoggerInterface
     */
    private $log;

    public function __construct(
        ResponseInterface $response,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        LoggerInterface $log
    ) {
        parent::__construct($response, $objectManager, $repositoryFactory);
        $this->log = $log;
    }

    /**
     * @OA\Get(
     *     path="/user/service/service/{id}",
     *     operationId="serviceService",
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
    public function service(string $id): ResponseInterface
    {
        $service = $this->repositoryFactory->getServiceRepository()->find((int) $id);

        if ($service === null) {
            return $this->response->withStatus(404);
        }

        return $this->withJson($service->jsonSerialize(false));
    }

    /**
     * @OA\Get(
     *     path="/user/service/service-accounts/{serviceId}/{playerId}",
     *     operationId="serviceServiceAccounts",
     *     summary="Returns all service accounts for one player and one service.",
     *     description="Needs role: user",
     *     tags={"Service"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="serviceId",
     *         in="path",
     *         required=true,
     *         description="Service ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="playerId",
     *         in="path",
     *         required=true,
     *         description="Player ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Service accounts.",
     *         description="The player property contains only the id and name.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/AccountData"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Service or Player not found."
     *     )
     * )
     */
    public function serviceAccounts(
        string $serviceId,
        string $playerId,
        ServiceRegistration $serviceRegistration
    ): ResponseInterface {
        $service = $this->repositoryFactory->getServiceRepository()->find((int) $serviceId);
        $player = $this->repositoryFactory->getPlayerRepository()->find((int) $playerId);

        if ($service === null || $player === null) {
            return $this->response->withStatus(404);
        }

        $serviceObject = $serviceRegistration->getServiceObject($service);
        if ($serviceObject === null) {
            $this->log->error(
                "ServiceController: The configured service class does not exist of does not ".
                "implement Neucore\Plugin\ServiceInterface."
            );
            return $this->withJson([]);
        }

        $characterIds = array_map(function (Character $character) {
            return $character->getId();
        }, $player->getCharacters());
        $accountData = $serviceRegistration->getAccounts($serviceObject, $characterIds);

        return $this->withJson($accountData);
    }
}
