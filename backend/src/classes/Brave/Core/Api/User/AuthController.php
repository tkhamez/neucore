<?php declare(strict_types=1);

namespace Brave\Core\Api\User;

use Brave\Core\Service\Config;
use Brave\Core\Entity\Role;
use Brave\Core\Service\EveMail;
use Brave\Core\Service\Random;
use Brave\Core\Service\UserAuth;
use Brave\Slim\Session\SessionData;
use Brave\Sso\Basics\AuthenticationProvider;
use Psr\Log\LoggerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * @SWG\Tag(
 *     name="Auth",
 *     description="User authentication."
 * )
 * @SWG\Definition(
 *     definition="LoginResult",
 *     required={"success", "message"},
 *     @SWG\Property(
 *         property="success",
 *         type="boolean"
 *     ),
 *     @SWG\Property(
 *         property="message",
 *         type="string"
 *     )
 * )
 */
class AuthController
{
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
     * @var Response
     */
    private $response;

    /**
     * @var SessionData
     */
    private $session;

    /**
     * @var AuthenticationProvider
     */
    private $authProvider;

    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var UserAuth
     */
    private $userAuth;

    /**
     * @var Config
     */
    private $config;

    public function __construct(
        Response $response,
        SessionData $session,
        AuthenticationProvider $authProvider,
        LoggerInterface $log,
        UserAuth $userAuth,
        Config $config
    ) {
        $this->response = $response;
        $this->session = $session;
        $this->authProvider = $authProvider;
        $this->log = $log;
        $this->userAuth = $userAuth;
        $this->config = $config;
    }

    /**
     * @SWG\Get(
     *     path="/user/auth/login-url",
     *     operationId="loginUrl",
     *     summary="EVE SSO login URL.",
     *     tags={"Auth"},
     *     @SWG\Parameter(
     *         name="redirect",
     *         in="query",
     *         description="Optional URL for redirect after EVE login.",
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="type",
     *         in="query",
     *         type="string",
     *         description="Optional type of the login",
     *         enum={"alt", "mail"}
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="The EVE SSO login URL.",
     *         @SWG\Schema(type="string")
     *     )
     * )
     */
    public function loginUrl(Request $request): Response
    {
        switch ($request->getQueryParam('type', '')) {
            case 'alt':
                $prefix = self::STATE_PREFIX_ALT;
                break;
            case 'mail':
                $prefix = self::STATE_PREFIX_MAIL;
                break;
            default:
                $prefix = '';
        }
        $state = $prefix . Random::chars(12);

        $this->session->set('auth_state', $state);
        $this->session->set('auth_redirect', $request->getQueryParam('redirect', '/'));

        $this->authProvider->setScopes($this->getLoginScopes($state));

        return $this->response->withJson($this->authProvider->buildLoginUrl($state));
    }

    /**
     * EVE SSO callback URL.
     *
     * @param Request $request
     * @return Response
     */
    public function callback(Request $request, EveMail $mailService): Response
    {
        $redirectUrl = $this->session->get('auth_redirect', '/');
        $this->session->delete('auth_redirect');

        $state = (string) $this->session->get('auth_state');
        $this->session->delete('auth_state');

        $this->authProvider->setScopes($this->getLoginScopes($state));

        try {
            $eveAuth = $this->authProvider->validateAuthentication(
                $request->getQueryParam('state'),
                $state,
                $request->getQueryParam('code', '')
            );
        } catch (\UnexpectedValueException $uve) {
            $this->session->set('auth_result', [
                'success' => false,
                'message' => $uve->getMessage(),
            ]);
            return $this->response->withRedirect($redirectUrl);
        }

        // handle login
        switch (substr($state, 0, 2)) {
            case self::STATE_PREFIX_ALT:
                $success = $this->userAuth->addAlt($eveAuth);
                $successMessage = 'Character added to player account.';
                $errorMessage = 'Failed to add alt to account.';
                break;
            case self::STATE_PREFIX_MAIL:
                if (in_array(Role::SETTINGS, $this->userAuth->getRoles())) {
                    $success = $mailService->storeMailCharacter($eveAuth);
                } else {
                    $success = false;
                }
                $successMessage = 'Mail character authenticated.';
                $errorMessage = 'Failed to store character.';
                break;
            default:
                $success = $this->userAuth->authenticate($eveAuth);
                $successMessage = 'Login successful.';
                $errorMessage = 'Failed to authenticate user.';
        }

        $this->session->set('auth_result', [
            'success' => $success,
            'message' => $success ? $successMessage : $errorMessage
        ]);

        return $this->response->withRedirect($redirectUrl);
    }

    /**
     * @SWG\Get(
     *     path="/user/auth/result",
     *     operationId="result",
     *     summary="Result of last SSO attempt.",
     *     tags={"Auth"},
     *     @SWG\Response(
     *         response="200",
     *         description="The result.",
     *         @SWG\Schema(ref="#/definitions/LoginResult")
     *     )
     * )
     */
    public function result(): Response
    {
        $result = $this->session->get('auth_result');

        $default = [
            'success' => false,
            'message' => 'No login attempt recorded.',
        ];

        return $this->response->withJson($result ?: $default);
    }

    /**
     * @SWG\Post(
     *     path="/user/auth/logout",
     *     operationId="logout",
     *     summary="User logout.",
     *     description="Needs role: user",
     *     tags={"Auth"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="204",
     *         description="User was logged out."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function logout(): Response
    {
        $this->session->clear();

        return $this->response->withStatus(204);
    }

    private function getLoginScopes($state)
    {
        if (substr($state, 0, 2) === self::STATE_PREFIX_MAIL) {
            return ['esi-mail.send_mail.v1'];
        }

        $scopes = (string) $this->config->get('eve')['scopes'];
        if (trim($scopes) !== '') {
            $scopes = explode(' ', $scopes);
        } else {
            $scopes = ['publicData'];
        }

        return $scopes;
    }
}
