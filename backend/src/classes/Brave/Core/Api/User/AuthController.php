<?php
namespace Brave\Core\Api\User;

use Brave\Core\Service\UserAuthService;
use Brave\Slim\Session\SessionData;
use League\OAuth2\Client\Provider\GenericProvider;
use Psr\Log\LoggerInterface;
use Slim\Http\Response;
use Slim\Http\Request;

class AuthController
{

    private $session;

    private $sso;

    private $log;

    public function __construct(SessionData $session, GenericProvider $sso, LoggerInterface $log)
    {
        $this->session = $session;
        $this->sso = $sso;
        $this->log = $log;
    }

    /**
     * @SWG\Get(
     *     path="/user/auth/login",
     *     summary="EVE SSO login URL",
     *     tags={"User"},
     *     @SWG\Parameter(
     *         name="redirect_url",
     *         in="query",
     *         description="Optional URL for redirect after EVE login.",
     *         type="string"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="The EVE SSO login URL",
     *         @SWG\Schema(
     *             title="SSOLogin",
     *             required={"oauth_url"},
     *             @SWG\Property(
     *                 property="oauth_url",
     *                 type="string"
     *             )
     *         )
     *     )
     * )
     */
    public function login(Request $request, Response $response)
    {
        $scopes = ['publicData'];
        $options = [
            'scope' => implode(' ', $scopes)
        ];

        $url = $this->sso->getAuthorizationUrl($options);

        $this->session->set('auth_state', $this->sso->getState());
        $this->session->set('auth_redirect_url', $request->getQueryParam('redirect_url', '/'));

        return $response->withJson(['oauth_url' => $url]);
    }

    public function callback(Request $request, Response $response, UserAuthService $auth)
    {
        $redirectUrl = $this->session->get('auth_redirect_url', '/');
        $this->session->delete('auth_redirect_url');

        $state = $this->session->get('auth_state');
        $this->session->delete('auth_state');

        if ($request->getQueryParam('state') !== $state) {
            $this->session->set('auth_result', [
                'success' => false,
                'message' => 'OAuth state mismatch',
            ]);
            return $response->withRedirect($redirectUrl);
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
            return $response->withRedirect($redirectUrl);
        }

        $resourceOwner = null;
        try {
            $resourceOwner = $this->sso->getResourceOwner($token);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), ['exception' => $e]);
        }

        $verify = $resourceOwner !== null ? $resourceOwner->toArray() : null;
        if (! is_array($verify) || ! isset($verify['CharacterID'])) {
            $this->session->set('auth_result', [
                'success' => false,
                'message' => 'request verify error',
            ]);
            return $response->withRedirect($redirectUrl);
        }

        $success = $auth->authenticate(
            $verify['CharacterID'],
            $verify['CharacterName'],
            $verify['CharacterOwnerHash'],
            $token->getToken(),
            $token->getExpires(),
            $token->getRefreshToken()
        );

        if ($success) {
            $this->session->set('auth_result', [
                'success' => true,
                'message' => ''
            ]);
        } else {
            $this->session->set('auth_result', [
                'success' => false,
                'message' => 'Could not authenticate user.'
            ]);
        }

        return $response->withRedirect($redirectUrl);
    }

    /**
     *
     * @SWG\Get(
     *     path="/user/auth/result",
     *     summary="SSO result",
     *     tags={"User"},
     *     @SWG\Response(
     *         response="200",
     *         description="Result of last SSO attempt",
     *         @SWG\Schema(
     *             title="SSOLoginResult",
     *             required={"success", "message"},
     *             @SWG\Property(
     *                 property="success",
     *                 type="boolean"
     *             ),
     *             @SWG\Property(
     *                 property="message",
     *                 type="string"
     *             )
     *         )
     *     )
     * )
     */
    public function result(Response $response)
    {
        $result = $this->session->get('auth_result');

        $default = [
            'success' => false,
            'message' => 'No login attempt recorded.',
        ];

        return $response->withJson($result ?: $default);
    }

    /**
     * @SWG\Get(
     *     path="/user/auth/logout",
     *     summary="User logout. Needs role: user",
     *     tags={"User"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="Nothing is returned"
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="If not authorized"
     *     )
     * )
     */
    public function logout(Response $response)
    {
        $this->session->clear();

        if (session_id() !== '') { // there's no session for unit tests
            session_regenerate_id(true);
        }

        return $response;
    }
}
