<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Controller\User;

use Eve\Sso\AuthenticationProvider;
use Neucore\Controller\BaseController;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Role;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\RepositoryFactory;
use Neucore\Middleware\Psr15\CSRFToken;
use Neucore\Service\Config;
use Neucore\Service\EsiData;
use Neucore\Service\EveMail;
use Neucore\Service\MemberTracking;
use Neucore\Service\ObjectManager;
use Neucore\Util\Random;
use Neucore\Service\SessionData;
use Neucore\Service\UserAuth;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @OA\SecurityScheme(
 *     securityScheme="Session",
 *     type="apiKey",
 *     name="neucore",
 *     in="cookie"
 * )
 * @OA\SecurityScheme(
 *     securityScheme="CSRF",
 *     type="apiKey",
 *     name="X-CSRF-Token",
 *     in="header",
 *     description="The CSRF token for POST, PUT and DELETE requests."
 * )
 *
 * @OA\Tag(
 *     name="Auth",
 *     description="User authentication."
 * )
 * @OA\Schema(
 *     schema="LoginResult",
 *     required={"success", "message"},
 *     @OA\Property(
 *         property="success",
 *         type="boolean"
 *     ),
 *     @OA\Property(
 *         property="message",
 *         type="string"
 *     )
 * )
 */
class AuthController extends BaseController
{
    private const SESS_AUTH_STATE = 'auth_state';

    private const SESS_AUTH_RESULT = 'auth_result';

    private const KEY_RESULT_SUCCESS = 'success';

    private const KEY_RESULT_MESSAGE = 'message';

    private const STATE_PREFIX_SEPARATOR = '*';

    /**
     * @var SessionData
     */
    private $session;

    /**
     * @var AuthenticationProvider
     */
    private $authProvider;

    /**
     * @var Config
     */
    private $config;

    /**
     * Returns the OAuth state prefix for an EVE login.
     *
     * (Only public for unit tests.)
     *
     * @param string $eveLoginName The name of an EveLogin record.
     */
    public static function getStatePrefix(string $eveLoginName): string
    {
        return $eveLoginName . self::STATE_PREFIX_SEPARATOR;
    }

    public function __construct(
        ResponseInterface $response,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        SessionData $session,
        AuthenticationProvider $authProvider,
        Config $config
    ) {
        parent::__construct($response, $objectManager, $repositoryFactory);

        $this->session = $session;
        $this->authProvider = $authProvider;
        $this->config = $config;
    }

    /**
     * Eve logins, redirects to EVE SSO login page or fails with a 404 or 403 status code.
     */
    public function login(string $name): ResponseInterface
    {
        // validate login ID
        $loginName = null;
        if (in_array($name, EveLogin::INTERNAL_LOGIN_NAMES)) {
            $loginName = $name;
        } else {
            $eveLogin = $this->repositoryFactory->getEveLoginRepository()->findOneBy(['name' => $name]);
            if ($eveLogin) {
                $loginName = $eveLogin->getName();
            }
        }
        if (empty($loginName)) {
            $this->response->getBody()->write($this->getBodyWithHomeLink('Login not found.'));
            return $this->response->withStatus(404);
        }

        // check "allow managed login" settings
        if (in_array($loginName, [EveLogin::NAME_MANAGED, EveLogin::NAME_MANAGED_ALT])) {
            $allowLoginManaged = $this->repositoryFactory->getSystemVariableRepository()
                ->findOneBy(['name' => SystemVariable::ALLOW_LOGIN_MANAGED]);
            if (!$allowLoginManaged || $allowLoginManaged->getValue() !== '1') {
                $this->response->getBody()->write('Forbidden.');
                return $this->response->withStatus(403);
            }
        }

        return $this->redirectToLoginUrl($loginName);
    }

    /**
     * EVE SSO callback URL.
     */
    public function callback(
        ServerRequestInterface $request,
        UserAuth $userAuth,
        EveMail $mailService,
        MemberTracking $memberTrackingService,
        EsiData $esiData
    ): ResponseInterface {
        $state = (string) $this->session->get(self::SESS_AUTH_STATE);
        $loginName = $this->getLoginNameFromState($state);
        $redirectUrl = $this->getRedirectUrl($loginName);

        $this->session->delete(self::SESS_AUTH_STATE);
        $this->authProvider->setScopes($this->getLoginScopes($state));

        try {
            $eveAuth = $this->authProvider->validateAuthenticationV2(
                $this->getQueryParam($request, 'state'),
                $state,
                $this->getQueryParam($request, 'code', '')
            );
        } catch (\Exception $e) {
            $this->session->set(self::SESS_AUTH_RESULT, [
                self::KEY_RESULT_SUCCESS => false,
                self::KEY_RESULT_MESSAGE => $e->getMessage(),
            ]);
            return $this->redirect($redirectUrl);
        }

        // handle login
        $success = false;
        switch ($loginName) {
            case EveLogin::NAME_DEFAULT:
            case EveLogin::NAME_MANAGED:
                $success = $userAuth->authenticate($eveAuth);
                $successMessage = 'Login successful.';
                $errorMessage = 'Failed to authenticate user.';
                break;
            case EveLogin::NAME_ALT:
            case EveLogin::NAME_MANAGED_ALT:
                $success = $userAuth->addAlt($eveAuth);
                $successMessage = 'Character added to player account.';
                $errorMessage = 'Failed to add alt to account.';
                break;
            case EveLogin::NAME_MAIL:
                if (in_array(Role::SETTINGS, $userAuth->getRoles())) {
                    $success = $mailService->storeMailCharacter($eveAuth);
                }
                $successMessage = 'Mail character authenticated.';
                $errorMessage = 'Failed to store character.';
                break;
            case EveLogin::NAME_DIRECTOR:
                $successMessage = 'ESI token for character with director role added.';
                $errorMessage = 'Error adding ESI token for character with director role.';
                if ($esiData->verifyRoles(
                    [EveLogin::ROLE_DIRECTOR],
                    $eveAuth->getCharacterId(),
                    $eveAuth->getToken()->getToken()
                )) {
                    $success = $memberTrackingService->fetchCharacterAndStoreDirector($eveAuth);
                }
                break;
            default:
                $successMessage = 'ESI token added.';
                $errorMessage = '';
                $eveLogin = $this->repositoryFactory->getEveLoginRepository()->findOneBy(['name' => $loginName]);
                if (!$eveLogin) {
                    $errorMessage = 'Error, ESI token not added: Invalid login link.';
                } elseif (!$userAuth->getUser()) {
                    $errorMessage = 'Error, ESI token not added: Not logged in, login first.';
                } elseif (!($character = $userAuth->findCharacterOnAccount($eveAuth))) {
                    $errorMessage =
                        'Error, ESI token not added: Character not found on this account, please add it first.';
                } elseif (!$esiData->verifyRoles(
                    $eveLogin->getEveRoles(),
                    $eveAuth->getCharacterId(),
                    $eveAuth->getToken()->getToken()
                )) {
                    $errorMessage = 'Error, ESI token not added: Character does not have required role(s).';
                } else {
                    if ($userAuth->addToken($eveLogin, $eveAuth, $character)) {
                        $success = true;
                    } else {
                        $errorMessage = 'Failed to add the ESI token, please try again.';
                    }
                }
        }

        $this->session->set(self::SESS_AUTH_RESULT, [
            self::KEY_RESULT_SUCCESS => $success,
            self::KEY_RESULT_MESSAGE => $success ? $successMessage : $errorMessage
        ]);

        return $this->redirect($redirectUrl);
    }

    /**
     * @OA\Get(
     *     path="/user/auth/result",
     *     operationId="result",
     *     summary="Result of last SSO attempt.",
     *     tags={"Auth"},
     *     @OA\Response(
     *         response="200",
     *         description="The result.",
     *         @OA\JsonContent(ref="#/components/schemas/LoginResult")
     *     )
     * )
     */
    public function result(): ResponseInterface
    {
        $result = $this->session->get(self::SESS_AUTH_RESULT);

        $default = [
            self::KEY_RESULT_SUCCESS => false,
            self::KEY_RESULT_MESSAGE => 'No login attempt recorded.',
        ];

        return $this->withJson($result ?: $default);
    }

    /**
     * @OA\Post(
     *     path="/user/auth/logout",
     *     operationId="logout",
     *     summary="User logout.",
     *     description="Needs role: user",
     *     tags={"Auth"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Response(
     *         response="204",
     *         description="User was logged out."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function logout(): ResponseInterface
    {
        $this->session->destroy();

        return $this->response->withStatus(204);
    }

    /**
     * @OA\Get(
     *     path="/user/auth/csrf-token",
     *     operationId="authCsrfToken",
     *     summary="The CSRF token to use in POST, PUT and DELETE requests.",
     *     description="Needs role: user",
     *     tags={"Auth"},
     *     @OA\Response(
     *         response="200",
     *         description="The CSRF token.",
     *         @OA\JsonContent(type="string")
     *     )
     * )
     */
    public function csrfToken(SessionData $sessionData): ResponseInterface
    {
        $token = $sessionData->get(CSRFToken::CSRF_SESSION_NAME);
        if (empty($token)) {
            try {
                $token = Random::chars(39);
            } catch (\Exception $e) {
                $this->response->getBody()->write('Error.');
                return $this->response->withStatus(500);
            }
            $sessionData->set(CSRFToken::CSRF_SESSION_NAME, $token);
        }

        return $this->withJson($token);
    }

    private function getRedirectUrl(string $loginId): string
    {
        if (empty($loginId)) {
            return '/';
        } elseif (in_array($loginId, [EveLogin::NAME_DEFAULT, EveLogin::NAME_MANAGED])) {
            return '/#login';
        } elseif (in_array($loginId, [EveLogin::NAME_ALT, EveLogin::NAME_MANAGED_ALT])) {
            return '/#login-alt';
        } elseif ($loginId === EveLogin::NAME_MAIL) {
            return '/#login-mail';
        } elseif ($loginId === EveLogin::NAME_DIRECTOR) {
            return '/#login-director';
        }
        return '/#login-custom';
    }

    private function redirectToLoginUrl(string $loginName): ResponseInterface
    {
        try {
            $randomString = Random::chars(12);
        } catch (\Exception $e) {
            $this->response->getBody()->write('Error.');
            return $this->response->withStatus(500);
        }
        $state = self::getStatePrefix($loginName) . $randomString;

        $this->session->set(self::SESS_AUTH_STATE, $state);

        $this->authProvider->setScopes($this->getLoginScopes($state));

        return $this->redirect($this->authProvider->buildLoginUrl($state));
    }

    private function getLoginScopes(string $state): array
    {
        $loginName = $this->getLoginNameFromState($state);
        if (in_array($loginName, [EveLogin::NAME_MANAGED, EveLogin::NAME_MANAGED_ALT])) {
            return [];
        } elseif ($loginName === EveLogin::NAME_MAIL) {
            return [EveLogin::SCOPE_MAIL];
        } elseif ($loginName === EveLogin::NAME_DIRECTOR) {
            return [EveLogin::SCOPE_ROLES, EveLogin::SCOPE_TRACKING, EveLogin::SCOPE_STRUCTURES];
        }

        $scopes = '';
        if (in_array($loginName, [EveLogin::NAME_DEFAULT, EveLogin::NAME_ALT])) {
            $scopes = $this->config['eve']['scopes'];
        } else {
            $eveLogin = $this->repositoryFactory->getEveLoginRepository()->findOneBy(['name' => $loginName]);
            if ($eveLogin) {
                $scopes = $eveLogin->getEsiScopes();
            }
        }
        if (trim($scopes) !== '') {
            $scopes = explode(' ', $scopes);
        } else {
            $scopes = [];
        }

        return $scopes;
    }

    private function getLoginNameFromState(string $state): string
    {
        if (strpos($state, self::STATE_PREFIX_SEPARATOR) === false) {
            return '';
        }
        return substr($state, 0, strpos($state, self::STATE_PREFIX_SEPARATOR));
    }

    private function redirect(string $path): ResponseInterface
    {
        return $this->response->withHeader('Location', $path)->withStatus(302);
    }
}
