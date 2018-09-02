<?php declare(strict_types=1);

namespace Brave\Core\Service;

use Brave\Core\Factory\EsiApiFactory;
use Psr\Log\LoggerInterface;
use Swagger\Client\Eve\Model\GetAlliancesAllianceIdOk;
use Swagger\Client\Eve\Model\GetCharactersCharacterIdOk;
use Swagger\Client\Eve\Model\GetCorporationsCorporationIdOk;

/**
 * Swagger API wrapper
 */
class EsiApi
{
    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var int
     */
    private $lastErrorCode;

    /**
     * @var EsiApiFactory
     */
    private $esiApiFactory;

    /**
     * @var string
     */
    private $lastErrorMessage;

    public function __construct(LoggerInterface $log, EsiApiFactory $esiApiFactory)
    {
        $this->log = $log;
        $this->esiApiFactory = $esiApiFactory;
    }

    public function getLastErrorCode()
    {
        return $this->lastErrorCode;
    }

    public function getLastErrorMessage()
    {
        return $this->lastErrorMessage;
    }

    public function getAlliance(int $id): ?GetAlliancesAllianceIdOk
    {
        $this->lastErrorCode = null;
        $this->lastErrorMessage = null;

        $alli = null;
        try {
            $alli = $this->esiApiFactory->getAllianceApi()->getAlliancesAllianceId($id);
        } catch (\Exception $e) {
            $this->lastErrorCode = $e->getCode();
            $this->lastErrorMessage = $e->getMessage();
            $this->log->error($e->getMessage());
        }

        return $alli;
    }

    public function getCorporation(int $id): ?GetCorporationsCorporationIdOk
    {
        $this->lastErrorCode = null;
        $this->lastErrorMessage = null;

        $corp = null;
        try {
            $corp = $this->esiApiFactory->getCorporationApi()->getCorporationsCorporationId($id);
        } catch (\Exception $e) {
            $this->lastErrorCode = $e->getCode();
            $this->lastErrorMessage = $e->getMessage();
            $this->log->error($e->getMessage());
        }

        return $corp;
    }

    public function getCharacter(int $id): ?GetCharactersCharacterIdOk
    {
        $this->lastErrorCode = null;
        $this->lastErrorMessage = null;

        $char = null;
        try {
            $char = $this->esiApiFactory->getCharacterApi()->getCharactersCharacterId($id);
        } catch (\Exception $e) {
            $this->lastErrorCode = $e->getCode();
            $this->lastErrorMessage = $e->getMessage();
            $this->log->error($e->getMessage());
        }

        return $char;
    }
}
