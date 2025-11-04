<?php

namespace Tests\Unit\Service;

use Eve\Sso\EveAuthentication;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Token\AccessToken;
use Monolog\Logger;
use Neucore\Entity\Alliance;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\CorporationMember;
use Neucore\Entity\EsiToken;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Player;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\EsiApiFactory;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\EveMail;
use Neucore\Service\EveMailToken;
use Neucore\Service\ObjectManager;
use PHPUnit\Framework\TestCase;
use Tests\Client;
use Tests\Helper;
use Tests\HttpClientFactory;

class EveMailTest extends TestCase
{
    private EveMail $eveMail;

    private \Doctrine\Persistence\ObjectManager $om;

    private RepositoryFactory $repoFactory;

    private Client $client;

    protected function setUp(): void
    {
        $helper = new Helper();
        $helper->emptyDb();

        $this->om = $helper->getObjectManager();
        $this->repoFactory = new RepositoryFactory($this->om);
        $this->client = new Client();

        $logger = new Logger('test');
        $objManager = new ObjectManager($this->om, $logger);
        $config = Helper::getConfig();

        $eveMailToken = new EveMailToken(
            $this->repoFactory,
            $objManager,
            Helper::getAuthenticationProvider($this->client),
            $logger,
        );
        $esiFactory = new EsiApiFactory(new HttpClientFactory($this->client), $config, $eveMailToken);
        $this->eveMail = new EveMail($this->repoFactory, $objManager, $esiFactory, $eveMailToken);
    }

    public function testStoreMailCharacterFail(): void
    {
        $auth = new EveAuthentication(
            123456,
            'Name',
            'hash',
            new AccessToken(['access_token' => 'access', 'expires' => 1525456785, 'refresh_token' => 'refresh']),
        );

        // fails because variables are missing
        $result = $this->eveMail->storeMailCharacter($auth);
        self::assertFalse($result);
    }

    public function testStoreMailCharacter(): void
    {
        $char = new SystemVariable(SystemVariable::MAIL_CHARACTER);
        $token = new SystemVariable(SystemVariable::MAIL_TOKEN);
        $this->om->persist($char);
        $this->om->persist($token);
        $this->om->flush();

        $auth = new EveAuthentication(
            123456,
            'Name',
            'hash',
            new AccessToken(['access_token' => 'access', 'expires' => 1543480210, 'refresh_token' => 'refresh']),
        );
        $result = $this->eveMail->storeMailCharacter($auth);
        self::assertTrue($result);

        $charActual = $this->repoFactory->getSystemVariableRepository()->find(SystemVariable::MAIL_CHARACTER);
        $tokenActual = $this->repoFactory->getSystemVariableRepository()->find(SystemVariable::MAIL_TOKEN);

        self::assertSame('Name', $charActual?->getValue());
        self::assertSame([
            'id' => 123456,
            'access' => 'access',
            'refresh' => 'refresh',
            'expires' => 1543480210,
        ], json_decode((string) $tokenActual?->getValue(), true));
    }

    public function testInvalidTokenFindCharacterPlayerNotFound(): void
    {
        $result = $this->eveMail->invalidTokenFindCharacter(100100);
        self::assertNull($result);
    }

    public function testInvalidTokenFindCharacterNoInvalidToken(): void
    {
        $player = (new Player())->setName('n');
        $char = (new Character())->setId(100100)->setName('n')->setPlayer($player);
        $eveLogin = (new EveLogin())->setName(EveLogin::NAME_DEFAULT);
        $esiToken = (new EsiToken())->setEveLogin($eveLogin)->setCharacter($char)->setValidToken(true)
            ->setAccessToken('')->setRefreshToken('')->setExpires(0);
        $this->om->persist($player);
        $this->om->persist($char);
        $this->om->persist($eveLogin);
        $this->om->persist($esiToken);
        $this->om->flush();
        $playerId = $player->getId();
        $this->om->clear();

        $result = $this->eveMail->invalidTokenFindCharacter($playerId);
        self::assertNull($result);
    }

    public function testInvalidTokenFindCharacterMain(): void
    {
        $player = (new Player())->setName('n');
        $char1 = (new Character())->setId(100100)->setName('n')->setPlayer($player);
        $char2 = (new Character())->setId(100101)->setName('n')->setPlayer($player)->setMain(true);
        $this->om->persist($player);
        $this->om->persist($char1);
        $this->om->persist($char2);
        $this->om->flush();
        $playerId = $player->getId();
        $this->om->clear();

        $result = $this->eveMail->invalidTokenFindCharacter($playerId);
        self::assertSame(100101, $result);
    }

    public function testInvalidTokenFindCharacterNotMain(): void
    {
        $player = (new Player())->setName('n');
        $char1 = (new Character())->setId(100100)->setName('n')->setPlayer($player);
        $char2 = (new Character())->setId(100101)->setName('n')->setPlayer($player);
        $this->om->persist($player);
        $this->om->persist($char1);
        $this->om->persist($char2);
        $this->om->flush();
        $playerId = $player->getId();
        $this->om->clear();

        $result = $this->eveMail->invalidTokenFindCharacter($playerId);
        self::assertSame(100100, $result);
    }

    public function testInvalidTokenMaySendAllianceSettingsNotFound(): void
    {
        $result = $this->eveMail->invalidTokenMaySend(100100);
        self::assertSame('Alliance and/or Corporation settings variable not found.', $result);
    }

    public function testInvalidTokenMaySendCharacterNotFound(): void
    {
        $varAlli = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ALLIANCES))->setValue('123,456');
        $varCorp = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_CORPORATIONS))->setValue('987,654');
        $this->om->persist($varAlli);
        $this->om->persist($varCorp);
        $this->om->flush();
        $this->om->clear();

        $result = $this->eveMail->invalidTokenMaySend(100100);
        self::assertSame('Character not found.', $result);
    }

    public function testInvalidTokenMaySendManagedAccount(): void
    {
        $varAlli = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ALLIANCES))->setValue('123,456');
        $varCorp = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_CORPORATIONS))->setValue('987,654');
        $player = (new Player())->setName('n')->setStatus(Player::STATUS_MANAGED);
        $char = (new Character())->setName('n')->setId(100100)->setPlayer($player);
        $this->om->persist($varAlli);
        $this->om->persist($varCorp);
        $this->om->persist($player);
        $this->om->persist($char);
        $this->om->flush();
        $this->om->clear();

        $result = $this->eveMail->invalidTokenMaySend(100100);
        self::assertSame('Player account status is manually managed.', $result);
    }

    public function testInvalidTokenMaySendAllianceAndCorporationDoesNotMatch(): void
    {
        $varAlli = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ALLIANCES))->setValue('123,456');
        $varCorp = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_CORPORATIONS))->setValue('987,654');
        $player = (new Player())->setName('n');
        $char = (new Character())->setName('n')->setId(100100)->setPlayer($player);
        $this->om->persist($varAlli);
        $this->om->persist($varCorp);
        $this->om->persist($player);
        $this->om->persist($char);
        $this->om->flush();
        $this->om->clear();

        $result = $this->eveMail->invalidTokenMaySend(100100);
        self::assertSame(
            'No character found on account that belongs to one of the configured alliances or corporations.',
            $result,
        );
    }

    public function testInvalidTokenMaySendAlreadySent(): void
    {
        $varAlli = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ALLIANCES))->setValue('123,456');
        $varCorp = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_CORPORATIONS))->setValue('987,654');
        $player = (new Player())->setName('n')->setDeactivationMailSent(true);
        $alli = (new Alliance())->setId(456);
        $corp = (new Corporation())->setId(2020)->setAlliance($alli);
        $char = (new Character())->setName('n')->setId(100100)->setPlayer($player)->setCorporation($corp);
        $this->om->persist($varAlli);
        $this->om->persist($varCorp);
        $this->om->persist($player);
        $this->om->persist($alli);
        $this->om->persist($corp);
        $this->om->persist($char);
        $this->om->flush();
        $this->om->clear();

        $result = $this->eveMail->invalidTokenMaySend(100100);
        self::assertSame('Mail already sent.', $result);
    }

    public function testInvalidTokenMaySendCorporationIgnoreAlreadySentAndAccountStatus(): void
    {
        $varAlli = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ALLIANCES))->setValue('123,456');
        $varCorp = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_CORPORATIONS))->setValue('987,654');
        $player = (new Player())->setName('n')->setDeactivationMailSent(true)->setStatus(Player::STATUS_MANAGED);
        $corp = (new Corporation())->setId(987);
        $char = (new Character())->setName('n')->setId(100100)->setPlayer($player)->setCorporation($corp);
        $this->om->persist($varAlli);
        $this->om->persist($varCorp);
        $this->om->persist($player);
        $this->om->persist($corp);
        $this->om->persist($char);
        $this->om->flush();
        $this->om->clear();

        $result = $this->eveMail->invalidTokenMaySend(100100, true);
        self::assertSame('', $result);
    }

    public function testInvalidTokenMayAllianceSendTrue(): void
    {
        $varAlli = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ALLIANCES))->setValue('123,456');
        $varCorp = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_CORPORATIONS))->setValue('987,654');
        $player = (new Player())->setName('n');
        $alli = (new Alliance())->setId(456);
        $corp = (new Corporation())->setId(2020)->setAlliance($alli);
        $char = (new Character())->setName('n')->setId(100100)->setPlayer($player)->setCorporation($corp);
        $this->om->persist($varAlli);
        $this->om->persist($varCorp);
        $this->om->persist($player);
        $this->om->persist($alli);
        $this->om->persist($corp);
        $this->om->persist($char);
        $this->om->flush();
        $this->om->clear();

        $result = $this->eveMail->invalidTokenMaySend(100100);
        self::assertSame('', $result);
    }

    public function testInvalidTokenIsActiveDeactivated(): void
    {
        $varActive = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ACTIVE))->setValue('0');
        $this->om->persist($varActive);
        $this->om->flush();

        $result = $this->eveMail->invalidTokenIsActive();
        self::assertSame('Mail is deactivated.', $result);
    }

    public function testInvalidTokenIsActive(): void
    {
        $varActive = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ACTIVE))->setValue('1');
        $this->om->persist($varActive);
        $this->om->flush();

        $result = $this->eveMail->invalidTokenIsActive();
        self::assertSame('', $result);
    }

    public function testInvalidTokenSend_MissingCharacter(): void
    {
        $result = $this->eveMail->invalidTokenSend(123);
        self::assertSame('Missing character that can send mails.', $result);
    }

    public function testInvalidTokenSend_MissingTokenData(): void
    {
        $varToken = (new SystemVariable(SystemVariable::MAIL_TOKEN))->setValue('{"id": "123"}');
        $this->om->persist($varToken);
        $this->om->flush();

        $result = $this->eveMail->invalidTokenSend(123);
        self::assertSame('Missing token data.', $result);
    }

    public function testInvalidTokenSend_InvalidToken(): void
    {
        $varSubject = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_SUBJECT))->setValue('s');
        $varBody = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_BODY))->setValue('b');
        $varToken = new SystemVariable(SystemVariable::MAIL_TOKEN);
        $varToken->setValue((string) \json_encode([
            'id' => 123,
            'access' => 'access-token',
            'refresh' => 'refresh-token',
            'expires' => 1542546430,
        ]));
        $this->om->persist($varSubject);
        $this->om->persist($varBody);
        $this->om->persist($varToken);
        $this->om->flush();

        $this->client->setResponse(
            // for getAccessToken() (refresh)
            new Response(400, [], '{ "error": "invalid_grant" }'),
        );

        $result = $this->eveMail->invalidTokenSend(123);
        self::assertSame('Invalid token.', $result);

        $this->om->clear();
        $token = $this->repoFactory->getSystemVariableRepository()->find(SystemVariable::MAIL_TOKEN);
        self::assertSame('', $token?->getValue());
    }

    public function testInvalidTokenSend_Ok(): void
    {
        $varToken = new SystemVariable(SystemVariable::MAIL_TOKEN);
        $varToken->setValue((string) \json_encode([
            'id' => 123,
            'access' => 'access-token',
            'refresh' => 'refresh-token',
            'expires' => 1542546430,
        ]));
        $varSubject = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_SUBJECT))->setValue('subject 3');
        $varBody = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_BODY))->setValue("body\n\ntext");
        $this->om->persist($varToken);
        $this->om->persist($varSubject);
        $this->om->persist($varBody);
        $this->om->flush();

        $this->client->setResponse(
            // for getAccessToken() (refresh)
            new Response(
                200,
                [],
                '{"access_token": "new-token",
                "refresh_token": "new-rf",
                "expires": 1519933900}', // 03/01/2018 @ 7:51pm (UTC)
            ),

            // for postCharactersCharacterIdMail()
            new Response(200, [], '373515628'),
        );

        $result = $this->eveMail->invalidTokenSend(456);
        self::assertSame('', $result);

        $this->om->clear();
        $token = $this->repoFactory->getSystemVariableRepository()->find(SystemVariable::MAIL_TOKEN);
        self::assertSame([
            SystemVariable::TOKEN_ID => 123,
            SystemVariable::TOKEN_ACCESS => 'new-token',
            SystemVariable::TOKEN_REFRESH => 'new-rf',
            SystemVariable::TOKEN_EXPIRES => 1519933900,
        ], json_decode((string) $token?->getValue(), true));
    }

    public function testInvalidTokenMailSent(): void
    {
        $player = (new Player())->setName('n');
        self::assertFalse($player->getDeactivationMailSent());

        $this->om->persist($player);
        $this->om->flush();
        $playerId = $player->getId();

        $this->eveMail->invalidTokenMailSent($playerId, true);
        $this->om->clear();
        $player2 = $this->repoFactory->getPlayerRepository()->find($playerId);
        self::assertTrue($player2?->getDeactivationMailSent());

        $this->eveMail->invalidTokenMailSent($playerId, false);
        $this->om->clear();
        $player3 = $this->repoFactory->getPlayerRepository()->find($playerId);
        self::assertFalse($player3?->getDeactivationMailSent());
    }

    public function testMissingCharacterGetCorporations(): void
    {
        $varCorps = (new SystemVariable(SystemVariable::MAIL_MISSING_CHARACTER_CORPORATIONS))->setValue('');
        $corp1 = (new Corporation())->setId(1)->setTrackingLastUpdate(new \DateTime('now - 1 days - 1 hours'));
        $corp2 = (new Corporation())->setId(2)->setTrackingLastUpdate(new \DateTime('now - 1 hours'));
        $this->om->persist($varCorps);
        $this->om->persist($corp1);
        $this->om->persist($corp2);
        $this->om->flush();

        self::assertSame([], $this->eveMail->missingCharacterGetCorporations());

        $varCorps->setValue('1,2,2');
        $this->om->flush();

        self::assertSame([2], $this->eveMail->missingCharacterGetCorporations());
    }

    public function testMissingCharacterMaySend(): void
    {
        self::assertSame('Invalid config.', $this->eveMail->missingCharacterMaySend(101));

        $daysVar = (new SystemVariable(SystemVariable::MAIL_MISSING_CHARACTER_RESEND))->setValue('0');
        $this->om->persist($daysVar);
        $this->om->flush();

        self::assertSame('Invalid config.', $this->eveMail->missingCharacterMaySend(101));

        $daysVar->setValue('20');
        $this->om->flush();

        self::assertSame('', $this->eveMail->missingCharacterMaySend(101, true));

        self::assertSame('Member not found.', $this->eveMail->missingCharacterMaySend(101));

        $corp = (new Corporation())->setId(11);
        $member = (new CorporationMember())->setId(101)->setCorporation($corp)
            ->setMissingCharacterMailSentDate(new \DateTime('now -20 days +1 hour'));
        $this->om->persist($corp);
        $this->om->persist($member);
        $this->om->flush();

        self::assertSame('Already sent.', $this->eveMail->missingCharacterMaySend(101));

        $member->setMissingCharacterMailSentDate(new \DateTime('now -20 days -1 hour'));
        $this->om->flush();

        self::assertSame('', $this->eveMail->missingCharacterMaySend(101));
    }

    public function testMissingCharacterSend_MissingMailChar(): void
    {
        self::assertSame(
            'Missing character that can send mails.',
            $this->eveMail->missingCharacterSend(101),
        );
    }

    public function testMissingCharacterSend_MissingSubjectOrBody(): void
    {
        $varToken = new SystemVariable(SystemVariable::MAIL_TOKEN);
        $varToken->setValue((string) \json_encode([
            'id' => 123,
            'access' => 'access-token',
            'refresh' => 'refresh-token',
            'expires' => 1542546430,
        ]));
        $this->om->persist($varToken);
        $this->om->flush();

        self::assertSame('Missing subject or body text.', $this->eveMail->missingCharacterSend(101));
    }

    public function testMissingCharacterSend_InvalidGrant(): void
    {
        $varToken = new SystemVariable(SystemVariable::MAIL_TOKEN);
        $varToken->setValue((string) \json_encode([
            'id' => 123,
            'access' => 'access-token',
            'refresh' => 'refresh-token',
            'expires' => 1542546430,
        ]));
        $varSubject = (new SystemVariable(SystemVariable::MAIL_MISSING_CHARACTER_SUBJECT))->setValue('s');
        $varBody = (new SystemVariable(SystemVariable::MAIL_MISSING_CHARACTER_BODY))->setValue('b');
        $this->om->persist($varToken);
        $this->om->persist($varSubject);
        $this->om->persist($varBody);
        $this->om->flush();

        $this->client->setResponse(
            // for getAccessToken() (refresh)
            new Response(400, [], '{ "error": "invalid_grant" }'),
        );

        self::assertSame('Invalid token.', $this->eveMail->missingCharacterSend(101));
    }

    public function testMissingCharacterSend_OK(): void
    {
        $varToken = new SystemVariable(SystemVariable::MAIL_TOKEN);
        $varToken->setValue((string) \json_encode([
            'id' => 123,
            'access' => 'access-token',
            'refresh' => 'refresh-token',
            'expires' => 1542546430,
        ]));
        $varSubject = (new SystemVariable(SystemVariable::MAIL_MISSING_CHARACTER_SUBJECT))->setValue('s');
        $varBody = (new SystemVariable(SystemVariable::MAIL_MISSING_CHARACTER_BODY))->setValue('b');
        $this->om->persist($varToken);
        $this->om->persist($varSubject);
        $this->om->persist($varBody);
        $this->om->flush();

        $this->client->setResponse(
            new Response( // for getAccessToken() (refresh)
                200,
                [],
                '{"access_token": "new-token",
                "refresh_token": "",
                "expires": 1519933900}', // 03/01/2018 @ 7:51pm (UTC)
            ),
            new Response(200, [], '373515628'), // for postCharactersCharacterIdMail()
        );

        self::assertSame('', $this->eveMail->missingCharacterSend(101));
    }

    public function testMissingCharacterMailSent(): void
    {
        self::assertFalse($this->eveMail->missingCharacterMailSent(101, 'result'));

        $corp = (new Corporation())->setId(11);
        $member = (new CorporationMember())->setId(101)->setCorporation($corp);
        $this->om->persist($corp);
        $this->om->persist($member);
        $this->om->flush();

        self::assertNull($member->getMissingCharacterMailSentDate());

        self::assertTrue($this->eveMail->missingCharacterMailSent(101, 'result'));

        $this->om->clear();
        $memberDb = $this->repoFactory->getCorporationMemberRepository()->find(101);
        self::assertLessThanOrEqual(new \DateTime(), $memberDb?->getMissingCharacterMailSentDate());
        self::assertSame('result', $memberDb?->getMissingCharacterMailSentResult());
        self::assertSame(1, $memberDb->getMissingCharacterMailSentNumber());
    }
}
