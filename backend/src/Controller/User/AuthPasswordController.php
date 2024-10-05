<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Service\SessionData;
use Neucore\Service\UserAuth;
use Neucore\Util\Random;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AuthPasswordController extends BaseController
{
    #[OA\Post(
        path: '/user/auth/password-generate',
        operationId: 'userAuthPasswordGenerate',
        summary: 'Generates the password for a user.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: '200',
                description: 'The new password.',
                content: new OA\JsonContent(type: 'string')
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '500', description: 'Failed to created new password.'),
        ],
    )]
    public function generatePassword(UserAuth $userAuth): ResponseInterface
    {
        $user = $this->getUser($userAuth);

        try {
            $password = Random::chars(20, Random::CHARS_PASSWORD);
        } catch (\Exception) {
            return $this->response->withStatus(500);
        }
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $user->getPlayer()->setPassword($hash);

        return $this->flushAndReturn(200, $password);
    }

    #[OA\Post(
        path: '/user/auth/password-login',
        operationId: 'userAuthPasswordLogin',
        summary: 'Password login.',
        security: [['CSRF' => []]],
        requestBody: new OA\RequestBody(
            description: 'User ID and password.',
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['playerId', 'password'],
                    properties: [
                        new OA\Property(property: 'playerId', type: 'string'),
                        new OA\Property(property: 'password', type: 'string'),
                    ],
                )
            )
        ),
        tags: ['Auth'],
        responses: [
            new OA\Response(response: '204', description: 'Login success.'),
            new OA\Response(response: '400', description: 'Invalid request.'),
            new OA\Response(response: '401', description: 'Login failed.'),
        ],
    )]
    public function login(
        ServerRequestInterface $request,
        UserAuth $userAuth,
        SessionData $session,
    ): ResponseInterface
    {
        $playerId = $this->getBodyParam($request, 'playerId');
        $password = $this->getBodyParam($request, 'password');

        $player = $this->repositoryFactory->getPlayerRepository()->find((int)$playerId);
        if (!$player) {
            return $this->response->withStatus(400);
        }

        $mainCharacter = $player->getMain();
        if (!$mainCharacter) {
            return $this->response->withStatus(400);
        }

        if (!password_verify($password, $player->getPassword())) {
            return $this->response->withStatus(401);
        }

        if (password_needs_rehash($player->getPassword(), PASSWORD_BCRYPT)) {
            $player->setPassword(password_hash($password, PASSWORD_BCRYPT));
            $this->objectManager->flush();
        }

        $userAuth->loginCharacter($mainCharacter);

        $this->response = $this->response->withHeader(AuthController::HEADER_LOGIN, '1');

        return $this->response->withStatus(204);
    }
}
