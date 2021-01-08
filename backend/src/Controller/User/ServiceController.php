<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\Character;
use Neucore\Entity\Role;
use Neucore\Entity\Service;
use Neucore\Factory\RepositoryFactory;
use Neucore\Plugin\Exception;
use Neucore\Plugin\ServiceAccountData;
use Neucore\Plugin\ServiceInterface;
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
 *
 * The schema for Neucore\Plugin\ServiceAccountData:
 * @OA\Schema(
 *     schema="ServiceAccountData",
 *     required={"characterId", "username", "password", "email", "status"},
 *     @OA\Property(property="characterId", type="integer", format="int64"),
 *     @OA\Property(property="username", type="string", nullable=true),
 *     @OA\Property(property="password", type="string", nullable=true),
 *     @OA\Property(property="email", type="string", nullable=true),
 *     @OA\Property(property="status", type="string", nullable=true,
 *                  enum={"Pending", "Active", "Deactivated", "Unknown"})
 * )
 */
class ServiceController extends BaseController
{
    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var ServiceRegistration
     */
    private $serviceRegistration;

    /**
     * @var UserAuth
     */
    private $userAuth;

    /**
     * @var Character
     */
    private $validCharacter;

    /**
     * @var Service
     */
    private $service;

    /**
     * @var ServiceInterface
     */
    private $serviceImplementation;

    /**
     * @var ServiceAccountData
     */
    private $account;

    public function __construct(
        ResponseInterface $response,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        LoggerInterface $log,
        ServiceRegistration $serviceRegistration,
        UserAuth $userAuth
    ) {
        parent::__construct($response, $objectManager, $repositoryFactory);
        $this->log = $log;
        $this->serviceRegistration = $serviceRegistration;
        $this->userAuth = $userAuth;
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
    public function get(string $id, UserAuth $userAuth): ResponseInterface
    {
        $response = $this->getService((int) $id);
        if ($response instanceof ResponseInterface) {
            return $response;
        }

        $isAdmin = $this->getUser($userAuth)->getPlayer()->hasRole(Role::SERVICE_ADMIN);

        return $this->withJson($this->service->jsonSerialize(false, !$isAdmin));
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
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="In the event of an error when retrieving accounts."
     *     )
     * )
     */
    public function accounts(string $id, UserAuth $userAuth): ResponseInterface
    {
        $response = $this->getServiceAndServiceImplementation((int) $id);
        if ($response instanceof ResponseInterface) {
            return $response;
        }

        try {
            $accountData = $this->serviceRegistration->getAccounts(
                $this->serviceImplementation,
                $this->getUser($userAuth)->getPlayer()->getCharacters()
            );
        } catch (Exception $e) {
            return $this->response->withStatus(500);
        }

        return $this->withJson($accountData);
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
     *         description="Different errors, check reason phrase."
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

        // get main character
        $player = $this->getUser($userAuth)->getPlayer();
        $main = $player->getMain();
        if (!$main) {
            return $this->response->withStatus(409, 'no_main');
        }

        $result = $this->getServiceAndServiceImplementation((int) $id);
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        // check if account exists and has a valid state
        try {
            $accounts = $this->serviceRegistration->getAccounts($this->serviceImplementation, [$main], false);
        } catch (Exception $e) {
            return $this->response->withStatus(500);
        }
        if (
            !empty($accounts) &&
            !in_array(
                $accounts[0]->getStatus(),
                [ServiceAccountData::STATUS_DEACTIVATED, ServiceAccountData::STATUS_UNKNOWN]
            )
        ) {
            return $this->response->withStatus(409, 'already_registered');
        }

        try {
            $accountData = $this->serviceImplementation->register(
                $main->toCoreCharacter(),
                $player->getCoreGroups(),
                $emailAddress,
                $player->getCharactersId()
            );
        } catch (Exception $e) {
            if ($e->getMessage() !== '') {
                return $this->response->withStatus(409, $e->getMessage());
            } else {
                return $this->response->withStatus(500);
            }
        }
        return $this->withJson($accountData);
    }

    /**
     * @OA\Put(
     *     path="/user/service/{id}/update-account/{characterId}",
     *     operationId="serviceUpdateAccount",
     *     summary="Update an account.",
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
     *     @OA\Parameter(
     *         name="characterId",
     *         in="path",
     *         required=true,
     *         description="A character ID from the player's account.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Account updated."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Service, character or character's service account not found."
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="Error during update."
     *     )
     * )
     */
    public function updateAccount(string $id, string $characterId, UserAuth $userAuth): ResponseInterface
    {
        $response = $this->validateCharacterGetServiceImplementationAndAccount((int)$id, (int)$characterId, $userAuth);
        if ($response instanceof ResponseInterface) {
            return $response;
        }

        // update account
        try {
            $this->serviceImplementation->updateAccount(
                $this->validCharacter->toCoreCharacter(),
                $this->validCharacter->getPlayer()->getCoreGroups()
            );
        } catch (Exception $e) {
            return $this->response->withStatus(500);
        }

        return $this->response->withStatus(204);
    }

    /**
     * @OA\Put(
     *     path="/user/service/{id}/reset-password/{characterId}",
     *     operationId="serviceResetPassword",
     *     summary="Resets password for one account.",
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
     *     @OA\Parameter(
     *         name="characterId",
     *         in="path",
     *         required=true,
     *         description="A character ID from the player's account.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Password changed, returns the new password.",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Service, character or character's service account not found."
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="Password change failed."
     *     )
     * )
     */
    public function resetPassword(string $id, string $characterId, UserAuth $userAuth): ResponseInterface
    {
        $response = $this->validateCharacterGetServiceImplementationAndAccount((int)$id, (int)$characterId, $userAuth);
        if ($response instanceof ResponseInterface) {
            return $response;
        }

        // change password
        try {
            $newPassword = $this->serviceImplementation->resetPassword($this->account->getCharacterId());
        } catch (Exception $e) {
            return $this->response->withStatus(500);
        }

        return $this->withJson($newPassword);
    }

    private function getService(int $id): ?ResponseInterface
    {
        // get service
        $service = $this->repositoryFactory->getServiceRepository()->find($id);
        if ($service === null) {
            return $this->response->withStatus(404);
        }
        $this->service = $service;

        // check service permission
        $isAdmin = $this->getUser($this->userAuth)->getPlayer()->hasRole(Role::SERVICE_ADMIN);
        if (!$isAdmin && !$this->serviceRegistration->hasRequiredGroups($this->service)) {
            return $this->response->withStatus(403);
        }

        return null;
    }

    private function getServiceAndServiceImplementation(int $id): ?ResponseInterface
    {
        $response = $this->getService((int) $id);
        if ($response instanceof ResponseInterface) {
            return $response;
        }

        // get service object
        $serviceImplementation = $this->serviceRegistration->getServiceImplementation($this->service);
        if ($serviceImplementation === null) {
            $this->log->error(
                "ServiceController: The configured service class does not exist of " .
                "does not implement Neucore\Plugin\ServiceInterface."
            );
            return $this->response->withStatus(500);
        }
        $this->serviceImplementation = $serviceImplementation;

        return null;
    }

    private function validateCharacterGetServiceImplementationAndAccount(
        int $id,
        int $characterId,
        UserAuth $userAuth
    ): ?ResponseInterface {
        $validCharacter = null;
        foreach ($this->getUser($userAuth)->getPlayer()->getCharacters() as $character) {
            if ($character->getId() === (int)$characterId) {
                $validCharacter = $character;
                break;
            }
        }
        if ($validCharacter === null) {
            return $this->response->withStatus(404);
        }
        $this->validCharacter = $validCharacter;

        $response1 = $this->getServiceAndServiceImplementation((int) $id);
        if ($response1 instanceof ResponseInterface) {
            return $response1;
        }

        // get account
        try {
            $account = $this->serviceRegistration->getAccounts(
                $this->serviceImplementation,
                [$this->validCharacter],
                false
            );
        } catch (Exception $e) {
            return $this->response->withStatus(500);
        }
        if (empty($account)) {
            return $this->response->withStatus(404);
        }
        $this->account = $account[0];

        return null;
    }
}
