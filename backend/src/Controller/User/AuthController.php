<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Eve\Sso\AuthenticationProvider;
use Neucore\Api;
use Neucore\Controller\BaseController;
use Neucore\Entity\Role;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\RepositoryFactory;
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
    const SESS_AUTH_REDIRECT = 'auth_redirect';

    const SESS_AUTH_STATE = 'auth_state';

    const SESS_AUTH_RESULT = 'auth_result';

    const KEY_RESULT_SUCCESS = 'success';

    const KEY_RESULT_MESSAGE = 'message';

    /**
     * A prefix for the OAuth state parameter that identifies a login of "managed" accounts.
     *
     * @var string
     */
    const STATE_PREFIX_STATUS_MANAGED = 's.';

    /**
     * A prefix for the OAuth state parameter that identifies a login of "managed" alt.
     *
     * @var string
     */
    const STATE_PREFIX_STATUS_MANAGED_ALT = 'z.';

    /**
     * A prefix for the OAuth state parameter that identifies an alt login.
     *
     * @var string
     */
    const STATE_PREFIX_ALT = 'a.';

    /**
     * A prefix for the OAuth state parameter that identifies an login
     * of the character that is used to send mails.
     *
     * @var string
     */
    const STATE_PREFIX_MAIL = 'm.';

    /**
     * A prefix for the OAuth state parameter that identifies an login
     * of the character with director roles for the member tracking functionality.
     *
     * @var string
     */
    const STATE_PREFIX_DIRECTOR = 'd.';

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
     * Main login, redirects to EVE SSO login.
     */
    public function login(): ResponseInterface
    {
        return $this->redirectToLoginUrl('', '/#login');
    }

    /**
     * Login for "managed" accounts, redirects to EVE SSO login.
     */
    public function loginManaged(string $prefix = self::STATE_PREFIX_STATUS_MANAGED): ResponseInterface
    {
        // check "allow managed login" settings
        $allowLoginManaged = $this->repositoryFactory->getSystemVariableRepository()->findOneBy(
            ['name' => SystemVariable::ALLOW_LOGIN_MANAGED]
        );
        if (! $allowLoginManaged || $allowLoginManaged->getValue() !== '1') {
            $this->response->getBody()->write('Forbidden');
            return $this->response->withStatus(403, 'Forbidden');
        }

        return $this->redirectToLoginUrl($prefix, '/#login');
    }

    /**
     * Login for "managed" alts, redirects to EVE SSO login.
     *
     * @noinspection PhpUnused
     */
    public function loginManagedAlt(): ResponseInterface
    {
        return $this->loginManaged(self::STATE_PREFIX_STATUS_MANAGED_ALT);
    }

    /**
     * Alt login, redirects to EVE SSO login.
     *
     * @noinspection PhpUnused
     */
    public function loginAlt(): ResponseInterface
    {
        return $this->redirectToLoginUrl(self::STATE_PREFIX_ALT, '/#login-alt');
    }

    /**
     * Mail char login, redirects to EVE SSO login.
     *
     * @noinspection PhpUnused
     */
    public function loginMail(): ResponseInterface
    {
        return $this->redirectToLoginUrl(self::STATE_PREFIX_MAIL, '/#login-mail');
    }

    /**
     * Director char login, redirects to EVE SSO login.
     *
     * @noinspection PhpUnused
     */
    public function loginDirector(): ResponseInterface
    {
        return $this->redirectToLoginUrl(self::STATE_PREFIX_DIRECTOR, '/#login-director');
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
        switch (substr($state, 0, 2)) {
            case self::STATE_PREFIX_ALT:
            case self::STATE_PREFIX_STATUS_MANAGED_ALT:
                $success = $userAuth->addAlt($eveAuth);
                $successMessage = 'Character added to player account.';
                $errorMessage = 'Failed to add alt to account.';
                break;
            case self::STATE_PREFIX_MAIL:
                if (in_array(Role::SETTINGS, $userAuth->getRoles())) {
                    $success = $mailService->storeMailCharacter($eveAuth);
                } else {
                    $success = false;
                }
                $successMessage = 'Mail character authenticated.';
                $errorMessage = 'Failed to store character.';
                break;
            case self::STATE_PREFIX_DIRECTOR:
                $successMessage = 'Character with director roles added.';
                $errorMessage = 'Error adding character with director roles.';
                $success = $memberTrackingService->verifyAndStoreDirector($eveAuth);
                break;
            case self::STATE_PREFIX_STATUS_MANAGED:
            default:
                $success = $userAuth->authenticate($eveAuth);
                $successMessage = 'Login successful.';
                $errorMessage = 'Failed to authenticate user.';
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
     *     security={{"Session"={}}},
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

    private function redirectToLoginUrl(string $prefix, string $redirect): ResponseInterface
    {
        try {
            $randomString = Random::chars(12);
        } catch (\Exception $e) {
            $this->response->getBody()->write('Error.');
            return $this->response->withStatus(500);
        }
        $state = $prefix . $randomString;

        $this->session->set(self::SESS_AUTH_STATE, $state);
        $this->session->set(self::SESS_AUTH_REDIRECT, $redirect);

        $this->authProvider->setScopes($this->getLoginScopes($state));

        return $this->redirect($this->authProvider->buildLoginUrl($state));
    }

    private function getLoginScopes(string $state): array
    {
        $prefix = substr($state, 0, 2);
        if (in_array($prefix, [self::STATE_PREFIX_STATUS_MANAGED, self::STATE_PREFIX_STATUS_MANAGED_ALT])) {
            return [];
        } elseif ($prefix === self::STATE_PREFIX_MAIL) {
            return [Api::SCOPE_MAIL];
        } elseif ($prefix === self::STATE_PREFIX_DIRECTOR) {
            return [API::SCOPE_ROLES, API::SCOPE_TRACKING, Api::SCOPE_STRUCTURES];
        }

        $scopes = $this->config['eve']['scopes'];
        if (trim($scopes) !== '') {
            $scopes = explode(' ', $scopes);
        } else {
            $scopes = [];
        }

        return $scopes;
    }

    private function redirect(string $path): ResponseInterface
    {
        return $this->response->withHeader('Location', $path)->withStatus(302);
    }
}
