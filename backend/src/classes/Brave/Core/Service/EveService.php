<?php
namespace Brave\Core\Service;

use Brave\Core\Entity\Character;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Log\LoggerInterface;
use Swagger\Client\Eve\Configuration;

/**
 * ESI related functionality.
 */
class EveService
{

    private $oauth;

    private $em;

    private $log;

    private $character;

    public function __construct(GenericProvider $oauth, EntityManagerInterface $em, LoggerInterface $log)
    {
        $this->oauth = $oauth;
        $this->em = $em;
        $this->log = $log;
    }

    public function setCharacter(Character $character)
    {
        $this->character = $character;
    }

    public function getToken(): string
    {
        $token = "";

        if ($this->character === null) {
            $this->log->error('EveService::getToken: Character not set.');
            return $token;
        }

        try {
            $existingToken = new AccessToken([
                'access_token' => $this->character->getAccessToken(),
                'refresh_token' => $this->character->getRefreshToken(),
                'expires' => $this->character->getExpires()
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
            $this->character->setAccessToken($newAccessToken->getToken());
            $this->character->setExpires($newAccessToken->getExpires());
            try {
                $this->em->persist($this->character);
                $this->em->flush();
            } catch (\Exception $e) {
                $this->log->critical($e->getMessage(), ['exception' => $e]);
            }
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
