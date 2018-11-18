<?php declare(strict_types=1);

namespace Brave\Core\Service;

use Brave\Core\Factory\EsiApiFactory;
use Psr\Log\LoggerInterface;
use Swagger\Client\Eve\Model\GetAlliancesAllianceIdOk;
use Swagger\Client\Eve\Model\GetCharactersCharacterIdOk;
use Swagger\Client\Eve\Model\GetCorporationsCorporationIdOk;
use Swagger\Client\Eve\Model\PostCharactersCharacterIdMailMail;
use Swagger\Client\Eve\Model\PostCharactersCharacterIdMailRecipient;

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
        $this->initRequest();

        $alli = null;
        try {
            $alli = $this->esiApiFactory->getAllianceApi()->getAlliancesAllianceId($id);
        } catch (\Exception $e) {
            $this->handleException($e);
        }

        return $alli;
    }

    public function getCorporation(int $id): ?GetCorporationsCorporationIdOk
    {
        $this->initRequest();

        $corp = null;
        try {
            $corp = $this->esiApiFactory->getCorporationApi()->getCorporationsCorporationId($id);
        } catch (\Exception $e) {
            $this->handleException($e);
        }

        return $corp;
    }

    public function getCharacter(int $id): ?GetCharactersCharacterIdOk
    {
        $this->initRequest();

        $char = null;
        try {
            $char = $this->esiApiFactory->getCharacterApi()->getCharactersCharacterId($id);
        } catch (\Exception $e) {
            $this->handleException($e);
        }

        return $char;
    }

    /**
     * @param int $senderId EVE character ID
     * @param string $token A valid access token
     * @param string $subject max length 1000
     * @param string $body max length 10000
     * @param int[] $characterRecipients EVE character IDs
     * @param int $approvedCost
     * @return int|null
     * @see OAuthToken::getToken()
     */
    public function sendMail(
        int $senderId,
        string $token,
        string $subject,
        string $body,
        array $characterRecipients,
        int $approvedCost = 0
    ): ?int {
        $recipients = [];
        foreach ($characterRecipients as $characterRecipient) {
            $recipients[] = new PostCharactersCharacterIdMailRecipient([
                'recipient_id' => (int) $characterRecipient,
                'recipient_type' => PostCharactersCharacterIdMailRecipient::RECIPIENT_TYPE_CHARACTER,
            ]);
        }
        $mail = new PostCharactersCharacterIdMailMail([
            'recipients'    => $recipients,
            'subject'       => substr($subject, 0, 1000),
            'body'          => substr($body, 0, 10000),
            'approved_cost' => $approvedCost,
        ]);

        $id = null;
        try {
            $id = $this->esiApiFactory->getMailApi($token)->postCharactersCharacterIdMail($senderId, $mail);
        } catch (\Exception $e) {
            $this->handleException($e);
        }

        return $id;
    }

    private function initRequest()
    {
        $this->lastErrorCode = null;
        $this->lastErrorMessage = null;
    }

    private function handleException(\Exception $e): void
    {
        $this->lastErrorCode = $e->getCode();
        $this->lastErrorMessage = $e->getMessage();
        $this->log->error($e->getMessage()); // message includes the status code
    }
}
