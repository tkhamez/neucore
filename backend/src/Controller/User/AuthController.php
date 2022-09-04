<?php

/** @noinspection PhpUnusedAliasInspection */
/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Controller\User;

use Eve\Sso\AuthenticationProvider;
use Exception;
use Neucore\Controller\BaseController;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Role;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\RepositoryFactory;
use Neucore\Middleware\Psr15\CSRFToken;
use Neucore\Service\Config;
use Neucore\Service\EsiData;
use Neucore\Service\EveMail;
use Neucore\Service\ObjectManager;
use Neucore\Util\Random;
use Neucore\Service\SessionData;
use Neucore\Service\UserAuth;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
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

    private const SESS_AUTH_REDIRECT = 'auth_redirect';

    private const KEY_RESULT_SUCCESS = 'success';

    private const KEY_RESULT_MESSAGE = 'message';

    private const STATE_PREFIX_SEPARATOR = '*';

    private SessionData $session;

    private AuthenticationProvider $authProvider;

    private Config $config;

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
    public function login(string $name, ServerRequestInterface $request): ResponseInterface
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
        if ($loginName === EveLogin::NAME_MANAGED) {
            $allowLoginManaged = $this->repositoryFactory->getSystemVariableRepository()
                ->findOneBy(['name' => SystemVariable::ALLOW_LOGIN_MANAGED]);
            if (!$allowLoginManaged || $allowLoginManaged->getValue() !== '1') {
                $this->response->getBody()->write('Forbidden.');
                return $this->response->withStatus(403);
            }
        }

        $this->session->set(self::SESS_AUTH_REDIRECT, $this->getQueryParam($request,'redirect'));

        return $this->redirectToLoginUrl($loginName);
    }

    /**
     * EVE SSO callback URL.
     */
    public function callback(
        ServerRequestInterface $request,
        UserAuth $userAuth,
        EveMail $mailService,
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
        } catch (Exception $e) {
            $this->session->set(self::SESS_AUTH_RESULT, [
                self::KEY_RESULT_SUCCESS => false,
                self::KEY_RESULT_MESSAGE => $e->getMessage(),
            ]);
            return $this->redirect($redirectUrl);
        }

        // handle login
        $success = false;
        $successMessage = '';
        $errorMessage = '';
        switch ($loginName) {
            case EveLogin::NAME_DEFAULT:
            case EveLogin::NAME_MANAGED:
                $result = $userAuth->login($eveAuth);
                if ($result === UserAuth::LOGIN_AUTHENTICATED_SUCCESS) {
                    $success = true;
                    $successMessage = 'Login successful.';
                } elseif ($result === UserAuth::LOGIN_AUTHENTICATED_FAIL) {
                    $errorMessage = 'Failed to authenticate user.';
                } elseif ($result === UserAuth::LOGIN_CHARACTER_ADDED_SUCCESS) {
                    $success = true;
                    $successMessage = 'Character added to player account.';
                    $redirectUrl = $this->getRedirectUrl(EveLogin::NAME_DEFAULT, true);
                } elseif ($result === UserAuth::LOGIN_ACCOUNTS_MERGED) {
                    $success = true;
                    $successMessage = 'Accounts successfully merged.';
                    $redirectUrl = $this->getRedirectUrl(EveLogin::NAME_DEFAULT, true);
                } elseif ($result === UserAuth::LOGIN_CHARACTER_ADDED_FAIL) {
                    $errorMessage = 'Failed to add character to account.';
                    $redirectUrl = $this->getRedirectUrl(EveLogin::NAME_DEFAULT, true);
                }
                break;
            case EveLogin::NAME_MAIL:
                if (in_array(Role::SETTINGS, $userAuth->getRoles())) {
                    $success = $mailService->storeMailCharacter($eveAuth);
                }
                $successMessage = 'Mail character authenticated.';
                $errorMessage = 'Failed to store character.';
                break;
            default:
                $successMessage = 'ESI token added.';
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

        if ($redirectAfterLogin = $this->session->get(self::SESS_AUTH_REDIRECT)) {
            $redirectUrl .= '?redirect=' . $redirectAfterLogin;
        }
        $this->session->delete(self::SESS_AUTH_REDIRECT);

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
            } catch (Exception $e) {
                $this->response->getBody()->write('Error.');
                return $this->response->withStatus(500);
            }
            $sessionData->set(CSRFToken::CSRF_SESSION_NAME, $token);
        }

        return $this->withJson($token);
    }

    private function getRedirectUrl(string $loginId, bool $alt = false): string
    {
        if (empty($loginId)) {
            return '/#login-unknown';
        } elseif (in_array($loginId, [EveLogin::NAME_DEFAULT, EveLogin::NAME_MANAGED])) {
            return $alt ? '/#login-alt' : '/#login';
        } elseif ($loginId === EveLogin::NAME_MAIL) {
            return '/#login-mail';
        }
        return '/#login-custom';
    }

    private function redirectToLoginUrl(string $loginName): ResponseInterface
    {
        try {
            $randomString = Random::chars(12);
        } catch (Exception $e) {
            $this->response->getBody()->write('Error.');
            return $this->response->withStatus(500);
        }
        $state = $loginName . self::STATE_PREFIX_SEPARATOR . $randomString;

        $this->session->set(self::SESS_AUTH_STATE, $state);

        $this->authProvider->setScopes($this->getLoginScopes($state));

        return $this->redirect($this->authProvider->buildLoginUrl($state));
    }

    private function getLoginScopes(string $state): array
    {
        $loginName = $this->getLoginNameFromState($state);
        if ($loginName === EveLogin::NAME_MANAGED) {
            return [];
        } elseif ($loginName === EveLogin::NAME_MAIL) {
            return [EveLogin::SCOPE_MAIL];
        }

        $scopes = '';
        if ($loginName === EveLogin::NAME_DEFAULT) {
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
