<?php declare(strict_types=1);

namespace Brave\Core\Api\User;

use Brave\Core\Roles;
use Brave\Core\Service\UserAuthService;
use Brave\Slim\Session\SessionData;
use League\OAuth2\Client\Provider\GenericProvider;
use Psr\Log\LoggerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 *
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
     * @var GenericProvider
     */
    private $sso;

    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var UserAuthService
     */
    private $auth;

    /**
     * Scopes for EVE SSO login.
     *
     * @var array
     */
    private $scopes = ['publicData'];

    public function __construct(Response $res, SessionData $session, GenericProvider $sso,
        LoggerInterface $log, UserAuthService $auth)
    {
        $this->res = $res;
        $this->session = $session;
        $this->sso = $sso;
        $this->log = $log;
        $this->auth = $auth;
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

        return $this->res->withJson($this->buildLoginUrl($request, false));
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
        return $this->res->withJson($this->buildLoginUrl($request, true));
    }

    public function callback(Request $request): Response
    {
        $redirectUrl = $this->session->get('auth_redirect', '/');
        $this->session->delete('auth_redirect');

        $state = $this->session->get('auth_state');
        $this->session->delete('auth_state');

        if ($request->getQueryParam('state') !== $state) {
            $this->session->set('auth_result', [
                'success' => false,
                'message' => 'OAuth state mismatch',
            ]);
            return $this->res->withRedirect($redirectUrl);
        }

        try {
            $token = $this->sso->getAccessToken('authorization_code', [
                'code' => $request->getQueryParam('code', '')
            ]);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), ['exception' => $e]);
            $this->session->set('auth_result', [
                'success' => false,
                'message' => 'request token error',
            ]);
            return $this->res->withRedirect($redirectUrl);
        }

        $resourceOwner = null;
        try {
            $resourceOwner = $this->sso->getResourceOwner($token);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), ['exception' => $e]);
        }

        $verify = $resourceOwner !== null ? $resourceOwner->toArray() : null;
        if (! is_array($verify) ||
            ! isset($verify['CharacterID']) ||
            ! isset($verify['CharacterName']) ||
            ! isset($verify['CharacterOwnerHash'])
        ) {
            $this->session->set('auth_result', [
                'success' => false,
                'message' => 'request verify error',
            ]);
            return $this->res->withRedirect($redirectUrl);
        }

        // normal or alt login?
        $alt = $state{0} === 't'; // see buildLoginUrl()

        if ($alt) {
            $success = $this->auth->addAlt(
                $verify['CharacterID'],
                $verify['CharacterName'],
                $verify['CharacterOwnerHash'],
                $token->getToken(),
                $token->getExpires(),
                $token->getRefreshToken()
            );
        } else {
            $success = $this->auth->authenticate(
                $verify['CharacterID'],
                $verify['CharacterName'],
                $verify['CharacterOwnerHash'],
                $token->getToken(),
                $token->getExpires(),
                $token->getRefreshToken()
            );
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
     * @SWG\Get(
     *     path="/user/auth/character",
     *     operationId="character",
     *     summary="Returns the logged in EVE character.",
     *     description="Needs role: user",
     *     tags={"Auth"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="The logged in EVE character.",
     *         @SWG\Schema(ref="#/definitions/Character")
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized"
     *     )
     * )
     */
    public function character(): Response
    {
        return $this->res->withJson($this->auth->getUser());
    }

    /**
     * @SWG\Get(
     *     path="/user/auth/player",
     *     operationId="player",
     *     summary="Returns the logged in player with all properties.",
     *     description="Needs role: user",
     *     tags={"Auth"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="The player information.",
     *         @SWG\Schema(ref="#/definitions/Player")
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function player(): Response
    {
        return $this->res->withJson($this->auth->getUser()->getPlayer());
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

        if (session_id() !== '') { // there's no session for unit tests
            session_regenerate_id(true);
        }

        return $this->res->withStatus(204);
    }

    private function buildLoginUrl(Request $request, bool $altLogin): string
    {
        // "t" is used in the callback to identify an alt login request
        // (t is not a valid HEX value, the rest of the state is HEX)
        $statePrefix = $altLogin ? 't' : '';

        $options = [
            'scope' => implode(' ', $this->scopes),
            'state' => $statePrefix . bin2hex(random_bytes(16)),
        ];

        $url = $this->sso->getAuthorizationUrl($options);

        $this->session->set('auth_state', $this->sso->getState());
        $this->session->set('auth_redirect', $request->getQueryParam('redirect', '/'));

        return $url;
    }
}
