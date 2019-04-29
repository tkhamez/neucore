<?php declare(strict_types=1);

namespace Neucore\Service;

use Neucore\Entity\Player;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\EsiApiFactory;
use Neucore\Factory\RepositoryFactory;
use Brave\Sso\Basics\EveAuthentication;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Log\LoggerInterface;
use Swagger\Client\Eve\Model\PostCharactersCharacterIdMailMail;
use Swagger\Client\Eve\Model\PostCharactersCharacterIdMailRecipient;

class EveMail
{
    /**
     * @var RepositoryFactory
     */
    private $repositoryFactory;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var OAuthToken
     */
    private $oauthToken;

    /**
     * @var EsiApiFactory
     */
    private $esiApiFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $datasource;

    public function __construct(
        RepositoryFactory $repositoryFactory,
        ObjectManager $objectManager,
        OAuthToken $oauthToken,
        EsiApiFactory $esiApiFactory,
        LoggerInterface $logger,
        Config $config
    ) {
        $this->repositoryFactory = $repositoryFactory;
        $this->objectManager = $objectManager;
        $this->oauthToken = $oauthToken;
        $this->esiApiFactory = $esiApiFactory;
        $this->logger = $logger;

        $this->datasource = $config->get('eve', 'datasource');
    }

    public function storeMailCharacter(EveAuthentication $eveAuth)
    {
        $repo = $this->repositoryFactory->getSystemVariableRepository();
        $char = $repo->find(SystemVariable::MAIL_CHARACTER);
        $token = $repo->find(SystemVariable::MAIL_TOKEN);
        if ($char === null || $token === null) {
            return false;
        }

        $char->setValue($eveAuth->getCharacterName());

        $token->setValue((string) json_encode([
            'id' => (int) $eveAuth->getCharacterId(),
            'access' => $eveAuth->getToken()->getToken(),
            'refresh' => $eveAuth->getToken()->getRefreshToken(),
            'expires' => $eveAuth->getToken()->getExpires(),
        ]));

        return $this->objectManager->flush();
    }

    /**
     * Checks if the account has a character with an invalid token
     * and finds the best character to send the mail to.
     */
    public function accountDeactivatedFindCharacter(int $playerId): ?int
    {
        // find player
        $player = $this->repositoryFactory->getPlayerRepository()->find($playerId);
        if ($player === null) {
            return null;
        }

        // Check if a token is invalid
        $invalidChars = [];
        foreach ($player->getCharacters() as $character) {
            if (! $character->getValidToken()) {
                $invalidChars[] = $character->getId();
            }
        }
        if (count($invalidChars) === 0) {
            return null;
        }

        // find the character to send the mail to, prefer main
        $main = $player->getMain();
        if ($main !== null) {
            return $main->getId();
        }
        return $invalidChars[0];
    }

    /**
     * @param int $characterId
     * @param bool $ignoreAlreadySentAndStatus If set to true, allow the mail even if it has already been sent
     *             or if the account status is managed.
     * @return string The reason why the mail may not be send or empty
     */
    public function accountDeactivatedMaySend(?int $characterId, bool $ignoreAlreadySentAndStatus = false): string
    {
        $sysVarRepo = $this->repositoryFactory->getSystemVariableRepository();

        // get configured alliances
        $allianceVar = $sysVarRepo->find(SystemVariable::MAIL_ACCOUNT_DISABLED_ALLIANCES);
        if ($allianceVar === null) {
            return 'Alliance settings variable not found.';
        }
        $alliances = explode(',', $allianceVar->getValue());

        // get player
        $charRepo = $this->repositoryFactory->getCharacterRepository();
        $char = $charRepo->find($characterId);
        if ($char === null) {
            return 'Character not found.';
        }
        $player = $char->getPlayer();

        if (! $ignoreAlreadySentAndStatus && $player->getStatus() === Player::STATUS_MANAGED) {
            return 'Player account status is managed.';
        }

        // check if player account has at least one character in one of the configured alliances
        $valid = false;
        foreach ($player->getCharacters() as $character) {
            if ($character->getCorporation() !== null &&
                $character->getCorporation()->getAlliance() !== null &&
                in_array($character->getCorporation()->getAlliance()->getId(), $alliances)
            ) {
                $valid = true;
                break;
            }
        }
        if (! $valid) {
            return 'No character found on account that belongs to one of the configured alliances.';
        }

        // check if mail was sent before
        if (! $ignoreAlreadySentAndStatus && $player->getDeactivationMailSent()) {
            return 'Mail already sent.';
        }

        return '';
    }

    /**
     * @return string The reason why the mail is not active or empty
     */
    public function accountDeactivatedIsActive(): string
    {
        $sysVarRepo = $this->repositoryFactory->getSystemVariableRepository();

        // check if accounts will be disabled if a token is invalid
        $validTokenRequired = $sysVarRepo->find(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN);
        if ($validTokenRequired === null || $validTokenRequired->getValue() !== '1') {
            return '"Deactivate Accounts" settings is not enabled.';
        }

        // check whether "account deactivated" mail is activated
        $active = $sysVarRepo->find(SystemVariable::MAIL_ACCOUNT_DISABLED_ACTIVE);
        if ($active === null || $active->getValue() !== '1') {
            return 'Mail is deactivated.';
        }

        return '';
    }

    /**
     * @param int $recipient EVE character ID
     * @return string Error message or empty string on success
     */
    public function accountDeactivatedSend(int $recipient): string
    {
        $repo = $this->repositoryFactory->getSystemVariableRepository();

        $token = $repo->find(SystemVariable::MAIL_TOKEN);
        if ($token === null || $token->getValue() === '') {
            return 'Missing character that can send mails.';
        }

        $subject = $repo->find(SystemVariable::MAIL_ACCOUNT_DISABLED_SUBJECT);
        if ($subject === null || trim($subject->getValue()) === '') {
            return 'Missing subject.';
        }

        $body = $repo->find(SystemVariable::MAIL_ACCOUNT_DISABLED_BODY);
        if ($body === null || trim($body->getValue()) === '') {
            return 'Missing body text.';
        }

        $tokenValues = json_decode($token->getValue(), true);
        if (! is_array($tokenValues) ||
            ! isset($tokenValues['id']) ||
            ! isset($tokenValues['access']) ||
            ! isset($tokenValues['refresh']) ||
            ! isset($tokenValues['expires'])
        ) {
            return 'Missing token data.';
        }

        try {
            $accessToken = $this->oauthToken->refreshAccessToken(new AccessToken([
                'access_token' => $tokenValues['access'],
                'refresh_token' => $tokenValues['refresh'],
                'expires' => (int)$tokenValues['expires']
            ]));
        } catch (IdentityProviderException $e) {
            return 'Invalid token.';
        }

        return $this->sendMail(
            $tokenValues['id'],
            $accessToken->getToken(),
            $subject->getValue(),
            $body->getValue(),
            [$recipient]
        );
    }

    /**
     * Mark account so that this mail will not be resent, or reset the flag
     */
    public function accountDeactivatedMailSent(int $playerId, bool $sent): void
    {
        // find player
        $player = $this->repositoryFactory->getPlayerRepository()->find($playerId);
        if ($player === null) {
            return;
        }

        if ($player->getDeactivationMailSent() !== $sent) {
            $player->setDeactivationMailSent($sent);
            $this->objectManager->flush();
        }
    }

    /**
     * @param int $senderId EVE character ID
     * @param string $token A valid access token
     * @param string $subject max length 1000
     * @param string $body max length 10000
     * @param int[] $characterRecipients EVE character IDs
     * @param int $approvedCost
     * @return string Error message or empty string on success
     * @see OAuthToken::getToken()
     */
    public function sendMail(
        int $senderId,
        string $token,
        string $subject,
        string $body,
        array $characterRecipients,
        int $approvedCost = 0
    ): string {
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

        try {
            $this->esiApiFactory
                ->getMailApi($token)
                ->postCharactersCharacterIdMail($senderId, $this->datasource, null, $mail);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]); // message includes the status code
            return $e->getMessage();
        }

        return '';
    }
}
