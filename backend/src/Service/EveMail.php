<?php

declare(strict_types=1);

namespace Neucore\Service;

use Eve\Sso\EveAuthentication;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Player;
use Neucore\Entity\SystemVariable;
use Neucore\Exception\Exception;
use Neucore\Factory\EveApiFactory;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\SystemVariableRepository;
use Tkhamez\Eve\API\Model\PostCharactersCharacterIdMailRequest;
use Tkhamez\Eve\API\Model\PostCharactersCharacterIdMailRequestRecipientsInner;

class EveMail
{
    private SystemVariableRepository $sysVarRepo;

    public function __construct(
        private readonly RepositoryFactory $repositoryFactory,
        private readonly ObjectManager $objectManager,
        private readonly EveApiFactory $esiApiFactory,
        private readonly EveMailToken $eveMailToken,
    ) {
        $this->sysVarRepo = $this->repositoryFactory->getSystemVariableRepository();
    }

    /**
     * @see EveMail::updateToken();
     */
    public function storeMailCharacter(EveAuthentication $eveAuth): bool
    {
        $char = $this->sysVarRepo->find(SystemVariable::MAIL_CHARACTER);
        $token = $this->sysVarRepo->find(SystemVariable::MAIL_TOKEN);
        if ($char === null || $token === null) {
            return false;
        }

        $char->setValue($eveAuth->getCharacterName());

        $token->setValue((string) json_encode([
            SystemVariable::TOKEN_ID => $eveAuth->getCharacterId(),
            SystemVariable::TOKEN_ACCESS => $eveAuth->getToken()->getToken(),
            SystemVariable::TOKEN_REFRESH => $eveAuth->getToken()->getRefreshToken(),
            SystemVariable::TOKEN_EXPIRES => $eveAuth->getToken()->getExpires(),
        ]));

        return $this->objectManager->flush2();
    }

    /**
     * Checks if the account has a character with an invalid token
     * and finds the best character to send the mail to.
     */
    public function invalidTokenFindCharacter(int $playerId): ?int
    {
        // find player
        $player = $this->repositoryFactory->getPlayerRepository()->find($playerId);
        if ($player === null) {
            return null;
        }

        // Check if a token is invalid
        $invalidChars = [];
        foreach ($player->getCharacters() as $character) {
            $token = $character->getEsiToken(EveLogin::NAME_DEFAULT);
            if (!$token || !$token->getValidToken()) {
                $invalidChars[] = $character->getId();
            }
        }
        if (empty($invalidChars)) {
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
     * @param int|null $characterId
     * @param bool $ignoreAlreadySentAndStatus If set to true, allow the mail even if it has already been sent
     *             or if the account status is managed.
     * @return string The reason why the mail may not be sent, or empty.
     */
    public function invalidTokenMaySend(?int $characterId, bool $ignoreAlreadySentAndStatus = false): string
    {
        // get configured alliances and corporations
        $allianceVar = $this->sysVarRepo->find(SystemVariable::MAIL_INVALID_TOKEN_ALLIANCES);
        $corporationVar = $this->sysVarRepo->find(SystemVariable::MAIL_INVALID_TOKEN_CORPORATIONS);
        if ($allianceVar === null || $corporationVar === null) {
            return 'Alliance and/or Corporation settings variable not found.';
        }
        $alliances = array_map('intval', explode(',', $allianceVar->getValue()));
        $corporations = array_map('intval', explode(',', $corporationVar->getValue()));

        // get player
        $charRepo = $this->repositoryFactory->getCharacterRepository();
        $char = $charRepo->find($characterId);
        if ($char === null) {
            return 'Character not found.';
        }
        $player = $char->getPlayer();

        if (!$ignoreAlreadySentAndStatus && $player->getStatus() === Player::STATUS_MANAGED) {
            return 'Player account status is manually managed.';
        }

        // check if player account has at least one character in one of the configured alliances or corporations
        $valid = $player->hasCharacterInAllianceOrCorporation($alliances, $corporations);
        if (!$valid) {
            return 'No character found on account that belongs to one of the configured alliances or corporations.';
        }

        // check if mail was sent before
        if (!$ignoreAlreadySentAndStatus && $player->getDeactivationMailSent()) {
            return 'Mail already sent.';
        }

        return '';
    }

    /**
     * @return string The reason why the mail is not active or an empty string
     */
    public function invalidTokenIsActive(): string
    {
        // check whether "invalid ESI token" mail is activated
        $active = $this->sysVarRepo->find(SystemVariable::MAIL_INVALID_TOKEN_ACTIVE);
        if ($active === null || $active->getValue() !== '1') {
            return 'Mail is deactivated.';
        }

        return '';
    }

    /**
     * Send the "invalid token" mail.
     *
     * @param int $recipient EVE character ID
     * @return string Error message or empty string on success
     */
    public function invalidTokenSend(int $recipient): string
    {
        $subject = $this->sysVarRepo->find(SystemVariable::MAIL_INVALID_TOKEN_SUBJECT);
        $body = $this->sysVarRepo->find(SystemVariable::MAIL_INVALID_TOKEN_BODY);

        return $this->prepareAndSendMail($recipient, $subject, $body);
    }

    /**
     * Mark account so that this mail will not be resent, or reset the flag
     */
    public function invalidTokenMailSent(int $playerId, bool $sent): void
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

    public function missingCharacterIsActive(): string
    {
        // check whether "missing character" mail is activated
        $active = $this->sysVarRepo->find(SystemVariable::MAIL_MISSING_CHARACTER_ACTIVE);
        if ($active === null || $active->getValue() !== '1') {
            return 'Mail is deactivated.';
        }

        return '';
    }

    /**
     * Returns corporations from the configuration those member tracking data were updated within one day.
     *
     * @return int[]
     */
    public function missingCharacterGetCorporations(): array
    {
        $corporations = $this->sysVarRepo->find(SystemVariable::MAIL_MISSING_CHARACTER_CORPORATIONS);

        if ($corporations === null || $corporations->getValue() === '') {
            return [];
        }

        $yesterday = date_create('now - 1 days');
        if (!($yesterday instanceof \DateTime)) {
            return [];
        }

        $result = [];
        $corpRepo = $this->repositoryFactory->getCorporationRepository();
        foreach (explode(',', $corporations->getValue()) as $corporationId) {
            $corporation = $corpRepo->find((int) $corporationId);
            if (
                $corporation &&
                $corporation->getTrackingLastUpdate() > $yesterday &&
                !in_array($corporation->getId(), $result)
            ) {
                $result[] = $corporation->getId();
            }
        }

        return $result;
    }

    /**
     * Checks if this mail was sent before within the configured days.
     *
     * All other checks must already be completed (corporation from configuration,
     * last update of member tracking, character without account)
     *
     * @param int $corporationMemberId EVE character ID
     * @param bool $ignoreAlreadySent
     * @return string
     */
    public function missingCharacterMaySend(int $corporationMemberId, bool $ignoreAlreadySent = false): string
    {
        $daysVar = $this->sysVarRepo->find(SystemVariable::MAIL_MISSING_CHARACTER_RESEND);

        if (!$daysVar || (int) $daysVar->getValue() <= 0) {
            return 'Invalid config.';
        }

        if ($ignoreAlreadySent) {
            return '';
        }

        $memberRepo = $this->repositoryFactory->getCorporationMemberRepository();
        $member = $memberRepo->find($corporationMemberId);
        if (!$member) {
            return 'Member not found.';
        }

        $minDateSent = date_create('now -' . (int) $daysVar->getValue() . ' days');
        if ($member->getMissingCharacterMailSentDate() && $member->getMissingCharacterMailSentDate() > $minDateSent) {
            return 'Already sent.';
        }

        return '';
    }

    public function missingCharacterSend(int $recipient): string
    {
        $subject = $this->sysVarRepo->find(SystemVariable::MAIL_MISSING_CHARACTER_SUBJECT);
        $body = $this->sysVarRepo->find(SystemVariable::MAIL_MISSING_CHARACTER_BODY);

        return $this->prepareAndSendMail($recipient, $subject, $body);
    }

    /**
     * Set date to NOW.
     */
    public function missingCharacterMailSent(int $corporationMemberId, string $result): bool
    {
        $member = $this->repositoryFactory->getCorporationMemberRepository()->find($corporationMemberId);
        if ($member === null) {
            return false;
        }

        $member->setMissingCharacterMailSentDate(new \DateTime());
        $member->setMissingCharacterMailSentResult($result);
        $member->setMissingCharacterMailSentNumber($member->getMissingCharacterMailSentNumber() + 1);
        $this->objectManager->flush();

        return true;
    }

    private function prepareAndSendMail(int $recipient, ?SystemVariable $subject, ?SystemVariable $body): string
    {
        try {
            $storedToken = $this->eveMailToken->getStoredToken();
        } catch (Exception $e) {
            return $e->getMessage();
        }

        if (
            $subject === null ||
            trim($subject->getValue()) === '' ||
            $body === null ||
            trim($body->getValue()) === ''
        ) {
            return 'Missing subject or body text.';
        }

        try {
            $validToken = $this->eveMailToken->getValidToken($storedToken);
        } catch (Exception $e) {
            return $e->getMessage();
        }

        return $this->sendMail(
            $storedToken[SystemVariable::TOKEN_ID],
            $validToken->getToken(),
            $subject->getValue(),
            $body->getValue(),
            [$recipient],
        );
    }

    /**
     * @param int $senderId EVE character ID
     * @param string $token A valid access token
     * @param string $subject max length 1000
     * @param string $body max length 10000
     * @param int[] $characterRecipients EVE character IDs
     * @return string Error message or empty string on success
     */
    private function sendMail(
        int $senderId,
        string $token,
        string $subject,
        string $body,
        array $characterRecipients,
    ): string {
        $recipients = [];
        foreach ($characterRecipients as $characterRecipient) {
            $recipients[] = new PostCharactersCharacterIdMailRequestRecipientsInner([
                'recipient_id' => $characterRecipient,
                'recipient_type' => PostCharactersCharacterIdMailRequestRecipientsInner::RECIPIENT_TYPE_CHARACTER,
            ]);
        }
        $mail = new PostCharactersCharacterIdMailRequest([
            'recipients'    => $recipients,
            'subject'       => substr($subject, 0, 1000),
            'body'          => substr($body, 0, 10000),
            'approved_cost' => 0,
        ]);

        $mailApi = $this->esiApiFactory->getMailApi($token);
        try {
            $mailApi->postCharactersCharacterIdMail($senderId, $mail);
        } catch (\Exception $e) {
            return $e->getMessage(); // the message includes the status code
        }

        return '';
    }
}
