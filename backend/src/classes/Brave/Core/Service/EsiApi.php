<?php declare(strict_types=1);

namespace Brave\Core\Service;

use Brave\Core\Entity\Character;
use Psr\Log\LoggerInterface;
use Swagger\Client\Eve\Api\AllianceApi;
use Swagger\Client\Eve\Api\CharacterApi;
use Swagger\Client\Eve\Api\CorporationApi;
use Swagger\Client\Eve\Configuration;

class EsiApi
{

    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var OAuthToken
     */
    private $tokenService;

    /**
     * @var AllianceApi
     */
    private $alliApi;

    /**
     * @var CorporationApi
     */
    private $corpApi;

    /**
     * @var CharacterApi
     */
    private $charApi;

    /**
     * @var int
     */
    private $lastErrorCode;

    /**
     * @var string
     */
    private $lastErrorMessage;

    public function __construct(LoggerInterface $log, OAuthToken $ts,
        AllianceApi $alliApi, CorporationApi $corpApi, CharacterApi $charApi)
    {
        $this->log = $log;
        $this->tokenService = $ts;
        $this->alliApi = $alliApi;
        $this->corpApi = $corpApi;
        $this->charApi = $charApi;
    }

    public function getLastErrorCode()
    {
        return $this->lastErrorCode;
    }

    public function getLastErrorMessage()
    {
        return $this->lastErrorMessage;
    }

    /**
     * Returns the configuration for the Swagger client.
     *
     * For requests that need an access token.
     *
     * @param Character $character
     * @return Configuration
     */
    public function getConfiguration(Character $character): Configuration
    {
        $this->tokenService->setCharacter($character);

        $conf = Configuration::getDefaultConfiguration();
        $conf->setAccessToken($this->tokenService->getToken());

        return $conf;
    }

    /**
     *
     * @param int $id
     * @return \Swagger\Client\Eve\Model\GetAlliancesAllianceIdOk|null
     */
    public function getAlliance(int $id)
    {
        $this->lastErrorCode = null;
        $this->lastErrorMessage = null;

        $alli = null;
        try {
            $alli = $this->alliApi->getAlliancesAllianceId($id);
        } catch (\Exception $e) {
            $this->lastErrorCode = $e->getCode();
            $this->lastErrorMessage = $e->getMessage();
            $this->log->error($e->getMessage());
        }

        return $alli;
    }

    /**
     *
     * @param int $id
     * @return \Swagger\Client\Eve\Model\GetCorporationsCorporationIdOk|null
     */
    public function getCorporation(int $id)
    {
        $this->lastErrorCode = null;
        $this->lastErrorMessage = null;

        $corp = null;
        try {
            $corp = $this->corpApi->getCorporationsCorporationId($id);
        } catch (\Exception $e) {
            $this->lastErrorCode = $e->getCode();
            $this->lastErrorMessage = $e->getMessage();
            $this->log->error($e->getMessage());
        }

        return $corp;
    }

    /**
     *
     * @param int $id
     * @return \Swagger\Client\Eve\Model\GetCharactersCharacterIdOk|null
     */
    public function getCharacter(int $id)
    {
        $this->lastErrorCode = null;
        $this->lastErrorMessage = null;

        $char = null;
        try {
            $char = $this->charApi->getCharactersCharacterId($id);
        } catch (\Exception $e) {
            $this->lastErrorCode = $e->getCode();
            $this->lastErrorMessage = $e->getMessage();
            $this->log->error($e->getMessage());
        }

        return $char;
    }
}
