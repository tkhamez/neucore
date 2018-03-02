<?php
namespace Brave\Core\Service;

use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Log\LoggerInterface;
use Swagger\Client\Configuration;

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

    public function getToken(): string
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
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), ['exception' => $e]);
            return $token;
        }

        $newAccessToken = null;
        if ($existingToken->getExpires() && $existingToken->hasExpired()) {
            try {
                $newAccessToken = $this->oauth->getAccessToken('refresh_token', [
                    'refresh_token' => $existingToken->getRefreshToken()
                ]);
            } catch (\Exception $e) {
                $this->log->error($e->getMessage(), ['exception' => $e]);
            }
        }

        if ($newAccessToken) {
            $this->uas->updateAccessToken($newAccessToken->getToken(), $newAccessToken->getExpires());
        }

        return $newAccessToken ? $newAccessToken->getToken() : $existingToken->getToken();
    }

    public function getConfiguration(): Configuration
    {
        $conf = Configuration::getDefaultConfiguration();
        $conf->setAccessToken($this->getToken());

        return $conf;
    }
}
