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
    private const SESS_AUTH_REDIRECT = 'auth_redirect';

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
     * @param string $eveLoginId The ID of an EveLogin record.
     */
    public static function getStatePrefix(string $eveLoginId): string
    {
        return $eveLoginId . self::STATE_PREFIX_SEPARATOR;
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
    public function login(string $id): ResponseInterface
    {
        // validate login ID
        $loginId = null;
        if (in_array($id, EveLogin::INTERNAL_LOGINS)) {
            $loginId = $id;
        } else {
            $eveLogin = $this->repositoryFactory->getEveLoginRepository()->find($id);
            if ($eveLogin) {
                $loginId = $eveLogin->getId();
            }
        }
        if (empty($loginId)) {
            $this->response->getBody()->write(
                'Login not found.<br><br>' .
                '<a href="/">Home</a>'
            );
            return $this->response->withStatus(404, 'Login not found.');
        }

        // check "allow managed login" settings
        if (in_array($loginId, [EveLogin::ID_MANAGED, EveLogin::ID_MANAGED_ALT])) {
            $allowLoginManaged = $this->repositoryFactory->getSystemVariableRepository()
                ->findOneBy(['name' => SystemVariable::ALLOW_LOGIN_MANAGED]);
            if (!$allowLoginManaged || $allowLoginManaged->getValue() !== '1') {
                $this->response->getBody()->write('Forbidden');
                return $this->response->withStatus(403, 'Forbidden');
            }
        }

        return $this->redirectToLoginUrl($loginId);
    }

    /**
     * EVE SSO callback URL.
     */
    public function callback(
        ServerRequestInterface $request,
        UserAuth $userAuth,
        EveMail $mailService,
        MemberTracking $memberTrackingService
    ): ResponseInterface {
        $redirectUrl = $this->session->get(self::SESS_AUTH_REDIRECT, '/');
        $this->session->delete(self::SESS_AUTH_REDIRECT);

        $state = (string) $this->session->get(self::SESS_AUTH_STATE);
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
            return $this->redirect((string) $redirectUrl);
        }

        // handle login
        $loginId = $this->getLoginIdFromState($state);
        $success = false;
        switch ($loginId) {
            case EveLogin::ID_DEFAULT:
            case EveLogin::ID_MANAGED:
                $success = $userAuth->authenticate($eveAuth);
                $successMessage = 'Login successful.';
                $errorMessage = 'Failed to authenticate user.';
                break;
            case EveLogin::ID_ALT:
            case EveLogin::ID_MANAGED_ALT:
                $success = $userAuth->addAlt($eveAuth);
                $successMessage = 'Character added to player account.';
                $errorMessage = 'Failed to add alt to account.';
                break;
            case EveLogin::ID_MAIL:
                if (in_array(Role::SETTINGS, $userAuth->getRoles())) {
                    $success = $mailService->storeMailCharacter($eveAuth);
                }
                $successMessage = 'Mail character authenticated.';
                $errorMessage = 'Failed to store character.';
                break;
            case EveLogin::ID_DIRECTOR:
                $successMessage = 'Character with director roles added.';
                $errorMessage = 'Error adding character with director roles.';
                $success = $memberTrackingService->verifyAndStoreDirector($eveAuth);
                break;
            default:
                $successMessage = 'ESI token added.';
                $errorMessage = '';
                $eveLogin = $this->repositoryFactory->getEveLoginRepository()->find($loginId);
                if (!$eveLogin) {
                    $errorMessage = 'Invalid login link.';
                } else {
                    # TODO check in-game roles (scopes were already checked above)
                    if ($userAuth->addToken($eveLogin, $eveAuth)) {
                        $success = true;
                    } else {
                        // Not logged in or
                        // character not found on this account or
                        // failed to save ESI token
                        $errorMessage = 'Error adding the ESI token to a character on the logged in account.';
                    }
                }
        }

        $this->session->set(self::SESS_AUTH_RESULT, [
            self::KEY_RESULT_SUCCESS => $success,
            self::KEY_RESULT_MESSAGE => $success ? $successMessage : $errorMessage
        ]);

        return $this->redirect((string) $redirectUrl);
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
        if (in_array($loginId, [EveLogin::ID_DEFAULT, EveLogin::ID_MANAGED, EveLogin::ID_MANAGED_ALT])) {
            return '/#login';
        } elseif ($loginId === EveLogin::ID_ALT) {
            return '/#login-alt';
        } elseif ($loginId === EveLogin::ID_MAIL) {
            return '/#login-mail';
        } elseif ($loginId === EveLogin::ID_DIRECTOR) {
            return '/#login-director';
        }
        return '/#login-custom';
    }

    private function redirectToLoginUrl(string $loginId): ResponseInterface
    {
        try {
            $randomString = Random::chars(12);
        } catch (\Exception $e) {
            $this->response->getBody()->write('Error.');
            return $this->response->withStatus(500);
        }
        $state = self::getStatePrefix($loginId) . $randomString;

        $this->session->set(self::SESS_AUTH_STATE, $state);
        $this->session->set(self::SESS_AUTH_REDIRECT, $this->getRedirectUrl($loginId));

        $this->authProvider->setScopes($this->getLoginScopes($state));

        return $this->redirect($this->authProvider->buildLoginUrl($state));
    }

    private function getLoginScopes(string $state): array
    {
        $loginId = $this->getLoginIdFromState($state);
        if (in_array($loginId, [EveLogin::ID_MANAGED, EveLogin::ID_MANAGED_ALT])) {
            return [];
        } elseif ($loginId === EveLogin::ID_MAIL) {
            return [EveLogin::SCOPE_MAIL];
        } elseif ($loginId === EveLogin::ID_DIRECTOR) {
            return [EveLogin::SCOPE_ROLES, EveLogin::SCOPE_TRACKING, EveLogin::SCOPE_STRUCTURES];
        }

        $scopes = '';
        if (in_array($loginId, [EveLogin::ID_DEFAULT, EveLogin::ID_ALT])) {
            $scopes = $this->config['eve']['scopes'];
        } else {
            $eveLogin = $this->repositoryFactory->getEveLoginRepository()->find($loginId);
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

    private function getLoginIdFromState(string $state): string
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
