<?php declare(strict_types=1);

namespace Brave\Core\Api\User;

use Brave\Core\Config;
use Brave\Core\Roles;
use Brave\Core\Service\UserAuth;
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
     * @var UserAuth
     */
    private $auth;

    /**
     * Scopes for EVE SSO login.
     *
     * @var array
     */
    private $scopes;

    public function __construct(
        Response $res,
        SessionData $session,
        GenericProvider $sso,
        LoggerInterface $log,
        UserAuth $auth,
        Config $config
    ) {
        $this->res = $res;
        $this->session = $session;
        $this->sso = $sso;
        $this->log = $log;
        $this->auth = $auth;
        $this->scopes = isset($config->get('eve')['scopes']) ?
            explode(' ', (string) $config->get('eve')['scopes']) :
            [];
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

        // check OAuth state parameter
        if ($request->getQueryParam('state') !== $state) {
            $this->session->set('auth_result', [
                'success' => false,
                'message' => 'OAuth state mismatch.',
            ]);
            return $this->res->withRedirect($redirectUrl);
        }

        // get token(s)
        try {
            $token = $this->sso->getAccessToken('authorization_code', [
                'code' => $request->getQueryParam('code', '')
            ]);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), ['exception' => $e]);
            $this->session->set('auth_result', [
                'success' => false,
                'message' => 'Error when requesting the token.',
            ]);
            return $this->res->withRedirect($redirectUrl);
        }

        // get resource owner (character ID etc.)
        $resourceOwner = null;
        try {
            $resourceOwner = $this->sso->getResourceOwner($token);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), ['exception' => $e]);
        }

        // verify result
        $verify = $resourceOwner !== null ? $resourceOwner->toArray() : null;
        if (! is_array($verify) ||
            ! isset($verify['CharacterID']) ||
            ! isset($verify['CharacterName']) ||
            ! isset($verify['CharacterOwnerHash'])
        ) {
            $this->session->set('auth_result', [
                'success' => false,
                'message' => 'Error obtaining Character ID.',
            ]);
            return $this->res->withRedirect($redirectUrl);
        }

        // verify scopes (user can manipulate the SSO login URL)
        $scopes = isset($verify['Scopes']) ? $verify['Scopes'] : '';
        if (! $this->verifyScopes($scopes)) {
            $this->session->set('auth_result', [
                'success' => false,
                'message' => 'Required scopes do not match.',
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
                $scopes,
                $token
            );
        } else {
            $success = $this->auth->authenticate(
                $verify['CharacterID'],
                $verify['CharacterName'],
                $verify['CharacterOwnerHash'],
                $scopes,
                $token
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

    private function verifyScopes(string $scopes): bool
    {
        $scopeArr = explode(' ', $scopes);
        $diff1 = array_diff($this->scopes, $scopeArr);
        $diff2 = array_diff($scopeArr, $this->scopes);

        if (count($diff1) !== 0 || count($diff2) !== 0) {
            return false;
        }
        return true;
    }
}
