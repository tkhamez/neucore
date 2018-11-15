<?php declare(strict_types=1);

namespace Brave\Core\Api\User;

use Brave\Core\Config;
use Brave\Core\Roles;
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
 *
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
     * @var Response
     */
    private $res;

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
    private $auth;

    /**
     * A prefix for the OAuth state parameter that identifies an alt login.
     *
     * @var string
     */
    private $altLoginPrefix = '*';

    /**
     * Scopes for EVE SSO login.
     *
     * @var array
     */
    private $scopes;

    public function __construct(
        Response $res,
        SessionData $session,
        AuthenticationProvider $authProvider,
        LoggerInterface $log,
        UserAuth $auth,
        Config $config
    ) {
        $this->res = $res;
        $this->session = $session;
        $this->log = $log;
        $this->auth = $auth;

        $scopes = $config->get('eve')['scopes'];
        if (trim((string) $scopes) !== '') {
            $this->scopes = explode(' ', (string) $config->get('eve')['scopes']);
        } else {
            $this->scopes = ['publicData'];
        }

        $authProvider->setScopes($this->scopes);
        $this->authProvider = $authProvider;
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
     *     @SWG\Response(
     *         response="200",
     *         description="The EVE SSO login URL.",
     *         @SWG\Schema(type="string")
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="No URL is returned if the user is already logged in."
     *     )
     * )
     */
    public function loginUrl(Request $request): Response
    {
        // return empty string if already logged in
        if (in_array(Roles::USER, $this->auth->getRoles($request))) {
            return $this->res->withStatus(204);
        }

        return $this->res->withJson($this->getLoginUrl($request, false));
    }

    /**
     * @SWG\Get(
     *     path="/user/auth/login-alt-url",
     *     operationId="loginAltUrl",
     *     summary="EVE SSO login URL to add additional characters to an account.",
     *     description="Needs role: user",
     *     tags={"Auth"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="redirect",
     *         in="query",
     *         description="Optional URL for redirect after EVE login.",
     *         type="string"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="The EVE SSO login URL.",
     *         @SWG\Schema(type="string")
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function loginAltUrl(Request $request): Response
    {
        return $this->res->withJson($this->getLoginUrl($request, true));
    }

    public function callback(Request $request): Response
    {
        $redirectUrl = $this->session->get('auth_redirect', '/');
        $this->session->delete('auth_redirect');

        $state = (string) $this->session->get('auth_state');
        $this->session->delete('auth_state');

        try {
            $eveAuth = $this->authProvider->validateAuthentication(
                $request->getQueryParam('state'),
                $state,
                $request->getQueryParam('code', '')
            );
        } catch(\UnexpectedValueException $uve) {
            $this->session->set('auth_result', [
                'success' => false,
                'message' => $uve->getMessage(),
            ]);
            return $this->res->withRedirect($redirectUrl);
        }

        // normal or alt login?
        $alt = $state[0] === $this->altLoginPrefix;

        if ($alt) {
            $success = $this->auth->addAlt($eveAuth);
        } else {
            $success = $this->auth->authenticate($eveAuth);
        }

        if ($success) {
            $this->session->set('auth_result', [
                'success' => true,
                'message' => $alt ? 'Character added to player account.' : 'Login successful.'
            ]);
        } else {
            $this->session->set('auth_result', [
                'success' => false,
                'message' => 'Could not authenticate user.'
            ]);
        }

        return $this->res->withRedirect($redirectUrl);
    }

    /**
     *
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

        return $this->res->withJson($result ?: $default);
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

        return $this->res->withStatus(204);
    }

    private function getLoginUrl(Request $request, bool $altLogin): string
    {
        $statePrefix = $altLogin ? $this->altLoginPrefix : '';
        $state = $statePrefix . Random::chars(12);

        $url =  $this->authProvider->buildLoginUrl($state);

        $this->session->set('auth_state', $state);
        $this->session->set('auth_redirect', $request->getQueryParam('redirect', '/'));

        return $url;
    }
}
