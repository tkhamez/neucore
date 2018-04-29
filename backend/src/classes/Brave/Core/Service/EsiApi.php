<?php declare(strict_types=1);

namespace Brave\Core\Service;

use Psr\Log\LoggerInterface;
use Swagger\Client\Eve\Api\AllianceApi;
use Swagger\Client\Eve\Api\CharacterApi;
use Swagger\Client\Eve\Api\CorporationApi;

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
        $this->tokenService = $ts; // not yet used (only needed for protected endpoints)
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

            // don't log these client errors: 404 not found, 400 invalid param
            if ($e->getCode() !== 404 && $e->getCode() !== 400) {
                $this->log->error($e->getMessage());
            }
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
            if ($e->getCode() !== 404) {
                $this->log->error($e->getMessage());
            }
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
            if ($e->getCode() !== 404) {
                $this->log->error($e->getMessage());
            }
        }

        return $char;
    }
}
