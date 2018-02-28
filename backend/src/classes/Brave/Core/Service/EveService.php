<?php
namespace Brave\Core\Service;

use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Log\LoggerInterface;

/**
 * ESI related functionality.
 */
class EveService
{

    private $oauth;

    private $uas;

    private $log;

    public function __construct(GenericProvider $oauth, UserAuthService $uas, LoggerInterface $log)
    {
        $this->oauth = $oauth;
        $this->uas = $uas;
        $this->log = $log;
    }

    /**
     *
     * @return string
     */
    public function getToken()
    {
        $token = "";

        $char = $this->uas->getUser();

        if ($char === null) {
            return $token;
        }

        try {
            $existingToken = new AccessToken([
                'access_token' => $char->getAccessToken(),
                'refresh_token' => $char->getRefreshToken(),
                'expires' => $char->getExpires()
            ]);
            $newAccessToken = null;
            if ($existingToken->hasExpired()) {
                $newAccessToken = $this->oauth->getAccessToken('refresh_token', [
                    'refresh_token' => $existingToken->getRefreshToken()
                ]);
            }
            $token = $newAccessToken ? $newAccessToken->getToken() : $existingToken->getToken();
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), ['exception' => $e]);
        }

        return $token;
    }
}
