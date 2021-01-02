<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\Group;
use Neucore\Factory\RepositoryFactory;
use Neucore\Plugin\CoreGroup;
use Neucore\Plugin\ServiceAccountData;
use Neucore\Service\ObjectManager;
use Neucore\Service\ServiceRegistration;
use Neucore\Service\UserAuth;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * @OA\Tag(
 *     name="Service",
 *     description="Service management."
 * )
 */
class ServiceController extends BaseController
{
    private const SERVICE_OBJECT_ERROR =
        "ServiceController: The configured service class does not exist of " .
        "does not implement Neucore\Plugin\ServiceInterface.";

    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var ServiceRegistration
     */
    private $serviceRegistration;

    public function __construct(
        ResponseInterface $response,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        LoggerInterface $log,
        ServiceRegistration $serviceRegistration
    ) {
        parent::__construct($response, $objectManager, $repositoryFactory);
        $this->log = $log;
        $this->serviceRegistration = $serviceRegistration;
    }

    /**
     * @OA\Get(
     *     path="/user/service/{id}/get",
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

        if (!$this->serviceRegistration->hasRequiredGroups($service)) {
            return $this->response->withStatus(403);
        }

        return $this->withJson($service->jsonSerialize(false));
    }

    /**
     * @OA\Post(
     *     path="/user/service/{id}/register",
     *     operationId="serviceRegister",
     *     summary="Registers a new account with a service.",
     *     description="Needs role: group-user",
     *     tags={"Service"},
     *     security={{"Session"={}}},
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(
     *                     property="email",
     *                     description="E-mail address.",
     *                     type="string",
     *                     maxLength=255
     *                 )
     *             ),
     *         ),
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Service ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Registered successfully.",
     *         @OA\JsonContent(ref="#/components/schemas/ServiceAccountData")
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Service not found."
     *     ),
     *     @OA\Response(
     *         response="409",
     *         description="Already registered or account does not have a main character."
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="Registration failed."
     *     )
     * )
     */
    public function register(string $id, ServerRequestInterface $request, UserAuth $userAuth): ResponseInterface
    {
        $emailAddress = $this->sanitizePrintable($this->getBodyParam($request, 'email', ''));

        $service = $this->repositoryFactory->getServiceRepository()->find((int) $id);
        if ($service === null) {
            return $this->response->withStatus(404);
        }

        if (!$this->serviceRegistration->hasRequiredGroups($service)) {
            return $this->response->withStatus(403);
        }

        $player = $this->getUser($userAuth)->getPlayer();

        // get main character
        $main = $player->getMain();
        if (!$main) {
            return $this->response->withStatus(409);
        }

        $serviceObject = $this->serviceRegistration->getServiceObject($service);
        if ($serviceObject === null) {
            $this->log->error(self::SERVICE_OBJECT_ERROR);
            return $this->response->withStatus(500);
        }
        $accounts = $this->serviceRegistration->getAccounts($serviceObject, [$main], false);
        if (
            count($accounts) > 0 &&
            !in_array(
                $accounts[0]->getStatus(),
                [ServiceAccountData::STATUS_DEACTIVATED, ServiceAccountData::STATUS_UNKNOWN]
            )
        ) {
            return $this->response->withStatus(409);
        }

        $coreCharacter = $main->toCoreCharacter();
        $coreCharacter->groups = array_map(function (Group $group) {
            return new CoreGroup($group->getId(), $group->getName());
        }, $player->getGroups());
        $accountData = $serviceObject->register($coreCharacter, $emailAddress);
        if ($accountData === null) {
            return $this->response->withStatus(500);
        } else {
            return $this->withJson($accountData);
        }
    }

    /**
     * @OA\Get(
     *     path="/user/service/{id}/accounts",
     *     operationId="serviceAccounts",
     *     summary="Returns all player's service accounts for a service.",
     *     description="Needs role: user",
     *     tags={"Service"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Service ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Service accounts.",
     *         description="The player property contains only the id and name.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/ServiceAccountData"))
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
    public function accounts(string $id, UserAuth $userAuth): ResponseInterface
    {
        $service = $this->repositoryFactory->getServiceRepository()->find((int) $id);
        if ($service === null) {
            return $this->response->withStatus(404);
        }

        if (!$this->serviceRegistration->hasRequiredGroups($service)) {
            return $this->response->withStatus(403);
        }

        $serviceObject = $this->serviceRegistration->getServiceObject($service);
        if ($serviceObject === null) {
            $this->log->error(self::SERVICE_OBJECT_ERROR);
            return $this->withJson([]);
        }

        $accountData = $this->serviceRegistration->getAccounts(
            $serviceObject,
            $this->getUser($userAuth)->getPlayer()->getCharacters()
        );

        return $this->withJson($accountData);
    }
}
