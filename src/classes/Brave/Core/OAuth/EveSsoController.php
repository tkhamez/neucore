<?php
namespace Brave\Core\OAuth;

use Brave\Core\Service\UserAuthService;
use Brave\Slim\Session\SessionData;
use Slim\Http\Response;
use Slim\Http\Request;

class EveSsoController
{

    private $session;

    private $sso;

    public function __construct(SessionData $session, EveSsoService $sso)
    {
        $this->session = $session;
        $this->sso = $sso;
    }

    public function login(Request $request, Response $response)
    {
        $oauthState = uniqid();

        $url = $this->sso->getLoginUrl($oauthState);

        $this->session->set('auth_state', $oauthState);
        $this->session->set('auth_redirect_url', $request->getQueryParam('redirect_url'));

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

        $token = $this->sso->requestToken($request->getQueryParam('code', ''));
        if ($token === null || ! isset($token['access_token'])) {
            $this->session->set('auth_result', [
                'success' => false,
                'message' => 'request token error',
            ]);
            return $response->withRedirect($redirectUrl);
        }

        $verify = $this->sso->requestVerify($token['access_token']);
        if ($verify === null|| ! isset($verify['CharacterID'])) {
            $this->session->set('auth_result', [
                'success' => false,
                'message' => 'request verify error',
            ]);
            return $response->withRedirect($redirectUrl);
        }

        // If any scopes were requested, this must also be stored in the database:
        // $token['access_token'], $token['expires_in'], $token['refresh_token']
        // and we would need a method in the EveSsoService class to resfresh the access token,
        // and a method (in UserAuthService?) to update the user with the new token.

        $success = $auth->authenticate($verify['CharacterID'], $verify['CharacterName']);

        if ($success) {
            $this->session->set('auth_result', [
                'success' => true,
                'message' => null
            ]);
        } else {
            $this->session->set('auth_result', [
                'success' => false,
                'message' => 'Could not authenticate user.'
            ]);
        }

        return $response->withRedirect($redirectUrl);
    }

    public function result(Response $response)
    {
        $result = $this->session->get('auth_result');
        $this->session->delete('auth_result');

        return $response->withJson($result);
    }

    public function logout(Response $response)
    {
        $this->session->clear();

        return $response;
    }
}
