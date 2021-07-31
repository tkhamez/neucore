<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Unit\Service;

use Eve\Sso\EveAuthentication;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Token\AccessToken;
use Monolog\Logger;
use Neucore\Entity\Alliance;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\CorporationMember;
use Neucore\Entity\Player;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\EsiApiFactory;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\Config;
use Neucore\Service\EveMail;
use Neucore\Service\OAuthToken;
use Neucore\Service\ObjectManager;
use PHPUnit\Framework\TestCase;
use Tests\Client;
use Tests\Helper;
use Tests\OAuthProvider;

class EveMailTest extends TestCase
{
    /**
     * @var EveMail
     */
    private $eveMail;

    /**
     * @var \Doctrine\Persistence\ObjectManager
     */
    private $om;

    /**
     * @var RepositoryFactory
     */
    private $repoFactory;

    /**
     * @var Client
     */
    private $client;

    protected function setUp(): void
    {
        $helper = new Helper();
        $helper->emptyDb();

        $this->om = $helper->getObjectManager();
        $this->repoFactory = new RepositoryFactory($this->om);
        $this->client = new Client();

        $logger = new Logger('test');
        $objManager = new ObjectManager($this->om, $logger);
        $config = new Config(['eve' => ['datasource' => '', 'esi_host' => '']]);

        $esiFactory = new EsiApiFactory($this->client, $config);

        $oauth = new OAuthProvider($this->client);
        $oauthToken = new OAuthToken($oauth, $objManager, $logger, $this->client, $config);

        $this->eveMail = new EveMail(
            $this->repoFactory,
            $objManager,
            $oauthToken,
            $esiFactory,
            $config
        );
    }

    public function testStoreMailCharacterFail()
    {
        $auth = new EveAuthentication(
            123456,
            'Name',
            'hash',
            new AccessToken(['access_token' => 'access', 'expires' => 1525456785, 'refresh_token' => 'refresh'])
        );

        // fails because variables are missing
        $result = $this->eveMail->storeMailCharacter($auth);
        $this->assertFalse($result);
    }

    public function testStoreMailCharacter()
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
            new AccessToken(['access_token' => 'access', 'expires' => 1543480210, 'refresh_token' => 'refresh'])
        );
        $result = $this->eveMail->storeMailCharacter($auth);
        $this->assertTrue($result);

        $charActual = $this->repoFactory->getSystemVariableRepository()->find(SystemVariable::MAIL_CHARACTER);
        $tokenActual = $this->repoFactory->getSystemVariableRepository()->find(SystemVariable::MAIL_TOKEN);

        $this->assertSame('Name', $charActual->getValue());
        $this->assertSame([
            'id' => 123456,
            'access' => 'access',
            'refresh' => 'refresh',
            'expires' => 1543480210,
        ], json_decode($tokenActual->getValue(), true));
    }

    public function testInvalidTokenFindCharacterPlayerNotFound()
    {
        $result = $this->eveMail->invalidTokenFindCharacter(100100);
        $this->assertNull($result);
    }

    public function testInvalidTokenFindCharacterNoInvalidToken()
    {
        $player = (new Player())->setName('n');
        $char = (new Character())->setId(100100)->setName('n')->setPlayer($player);
        $char->setValidToken(true);
        $this->om->persist($player);
        $this->om->persist($char);
        $this->om->flush();
        $playerId = $player->getId();
        $this->om->clear();

        $result = $this->eveMail->invalidTokenFindCharacter($playerId);
        $this->assertNull($result);
    }

    public function testInvalidTokenFindCharacterMain()
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
        $this->assertSame(100101, $result);
    }

    public function testInvalidTokenFindCharacterNotMain()
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
        $this->assertSame(100100, $result);
    }

    public function testInvalidTokenMaySendAllianceSettingsNotFound()
    {
        $result = $this->eveMail->invalidTokenMaySend(100100);
        $this->assertSame('Alliance and/or Corporation settings variable not found.', $result);
    }

    public function testInvalidTokenMaySendCharacterNotFound()
    {
        $varAlli = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ALLIANCES))->setValue('123,456');
        $varCorp = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_CORPORATIONS))->setValue('987,654');
        $this->om->persist($varAlli);
        $this->om->persist($varCorp);
        $this->om->flush();
        $this->om->clear();

        $result = $this->eveMail->invalidTokenMaySend(100100);
        $this->assertSame('Character not found.', $result);
    }

    public function testInvalidTokenMaySendManagedAccount()
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
        $this->assertSame('Player account status is managed.', $result);
    }

    public function testInvalidTokenMaySendAllianceAndCorporationDoesNotMatch()
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
        $this->assertSame(
            'No character found on account that belongs to one of the configured alliances or corporations.',
            $result
        );
    }

    public function testInvalidTokenMaySendAlreadySent()
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
        $this->assertSame('Mail already sent.', $result);
    }

    public function testInvalidTokenMaySendCorporationIgnoreAlreadySentAndAccountStatus()
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
        $this->assertSame('', $result);
    }

    public function testInvalidTokenMayAllianceSendTrue()
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
        $this->assertSame('', $result);
    }

    public function testInvalidTokenIsActiveDeactivated()
    {
        $varActive = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ACTIVE))->setValue('0');
        $this->om->persist($varActive);
        $this->om->flush();

        $result = $this->eveMail->invalidTokenIsActive();
        $this->assertSame('Mail is deactivated.', $result);
    }

    public function testInvalidTokenIsActive()
    {
        $varActive = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ACTIVE))->setValue('1');
        $this->om->persist($varActive);
        $this->om->flush();

        $result = $this->eveMail->invalidTokenIsActive();
        $this->assertSame('', $result);
    }

    public function testInvalidTokenSendMissingCharacterOrTokenData()
    {
        $result = $this->eveMail->invalidTokenSend(123);
        $this->assertSame('Missing character that can send mails or missing token data.', $result);

        $varToken = (new SystemVariable(SystemVariable::MAIL_TOKEN))->setValue('{"id": "123"}');
        $this->om->persist($varToken);
        $this->om->flush();

        $result = $this->eveMail->invalidTokenSend(123);
        $this->assertSame('Missing character that can send mails or missing token data.', $result);
    }

    public function testInvalidTokenSendInvalidToken()
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
            new Response(400, [], '{ "error": "invalid_grant" }')
        );

        $result = $this->eveMail->invalidTokenSend(123);
        $this->assertSame('Invalid token.', $result);
    }

    public function testInvalidTokenSend()
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
                "refresh_token": "",
                "expires": 1519933900}' // 03/01/2018 @ 7:51pm (UTC)
            ),

            // for postCharactersCharacterIdMail()
            new Response(200, [], '373515628')
        );

        $result = $this->eveMail->invalidTokenSend(456);
        $this->assertSame('', $result);
    }

    public function testInvalidTokenMailSent()
    {
        $player = (new Player())->setName('n');
        $this->assertFalse($player->getDeactivationMailSent());

        $this->om->persist($player);
        $this->om->flush();
        $playerId = $player->getId();

        $this->eveMail->invalidTokenMailSent($playerId, true);
        $this->om->clear();
        $player2 = $this->repoFactory->getPlayerRepository()->find($playerId);
        $this->assertTrue($player2->getDeactivationMailSent());

        $this->eveMail->invalidTokenMailSent($playerId, false);
        $this->om->clear();
        $player3 = $this->repoFactory->getPlayerRepository()->find($playerId);
        $this->assertFalse($player3->getDeactivationMailSent());
    }

    public function testMissingCharacterGetCorporations()
    {
        $varCorps = (new SystemVariable(SystemVariable::MAIL_MISSING_CHARACTER_CORPORATIONS))->setValue('');
        $corp1 = (new Corporation())->setId(1)->setTrackingLastUpdate(new \DateTime('now - 1 days - 1 hours'));
        $corp2 = (new Corporation())->setId(2)->setTrackingLastUpdate(new \DateTime('now - 1 hours'));
        $this->om->persist($varCorps);
        $this->om->persist($corp1);
        $this->om->persist($corp2);
        $this->om->flush();

        $this->assertSame([], $this->eveMail->missingCharacterGetCorporations());

        $varCorps->setValue('1,2,2');
        $this->om->flush();

        $this->assertSame([2], $this->eveMail->missingCharacterGetCorporations());
    }

    public function testMissingCharacterMaySend()
    {
        $this->assertSame('Invalid config.', $this->eveMail->missingCharacterMaySend(101));

        $daysVar = (new SystemVariable(SystemVariable::MAIL_MISSING_CHARACTER_RESEND))->setValue('0');
        $this->om->persist($daysVar);
        $this->om->flush();

        $this->assertSame('Invalid config.', $this->eveMail->missingCharacterMaySend(101));

        $daysVar->setValue('20');
        $this->om->flush();

        $this->assertSame('', $this->eveMail->missingCharacterMaySend(101, true));

        $this->assertSame('Member not found.', $this->eveMail->missingCharacterMaySend(101));

        $corp = (new Corporation())->setId(11);
        $member = (new CorporationMember())->setId(101)->setCorporation($corp)
            ->setMissingCharacterMailSentDate(new \DateTime('now -20 days +1 hour'));
        $this->om->persist($corp);
        $this->om->persist($member);
        $this->om->flush();

        $this->assertSame('Already sent.', $this->eveMail->missingCharacterMaySend(101));

        $member->setMissingCharacterMailSentDate(new \DateTime('now -20 days -1 hour'));
        $this->om->flush();

        $this->assertSame('', $this->eveMail->missingCharacterMaySend(101));
    }

    public function testMissingCharacterSend()
    {
        $this->assertSame(
            'Missing character that can send mails or missing token data.',
            $this->eveMail->missingCharacterSend(101)
        );

        $varToken = new SystemVariable(SystemVariable::MAIL_TOKEN);
        $varToken->setValue((string) \json_encode([
            'id' => 123,
            'access' => 'access-token',
            'refresh' => 'refresh-token',
            'expires' => 1542546430,
        ]));
        $this->om->persist($varToken);
        $this->om->flush();

        $this->assertSame('Missing subject or body text.', $this->eveMail->missingCharacterSend(101));

        $varSubject = (new SystemVariable(SystemVariable::MAIL_MISSING_CHARACTER_SUBJECT))->setValue('s');
        $varBody = (new SystemVariable(SystemVariable::MAIL_MISSING_CHARACTER_BODY))->setValue('b');
        $this->om->persist($varSubject);
        $this->om->persist($varBody);
        $this->om->flush();

        $this->client->setResponse(
            // for getAccessToken() (refresh)
            new Response(400, [], '{ "error": "invalid_grant" }')
        );

        $this->assertSame('Invalid token.', $this->eveMail->missingCharacterSend(101));

        $this->client->setResponse(
            new Response( // for getAccessToken() (refresh)
                200,
                [],
                '{"access_token": "new-token",
                "refresh_token": "",
                "expires": 1519933900}' // 03/01/2018 @ 7:51pm (UTC)
            ),
            new Response(200, [], '373515628') // for postCharactersCharacterIdMail()
        );

        $this->assertSame('', $this->eveMail->missingCharacterSend(101));
    }

    public function testMissingCharacterMailSent()
    {
        $this->assertFalse($this->eveMail->missingCharacterMailSent(101, 'result'));

        $corp = (new Corporation())->setId(11);
        $member = (new CorporationMember())->setId(101)->setCorporation($corp);
        $this->om->persist($corp);
        $this->om->persist($member);
        $this->om->flush();

        $this->assertNull($member->getMissingCharacterMailSentDate());

        $this->assertTrue($this->eveMail->missingCharacterMailSent(101, 'result'));

        $this->om->clear();
        $memberDb = $this->repoFactory->getCorporationMemberRepository()->find(101);
        $this->assertLessThanOrEqual(new \DateTime(), $memberDb->getMissingCharacterMailSentDate());
        $this->assertSame('result', $memberDb->getMissingCharacterMailSentResult());
        $this->assertSame(1, $memberDb->getMissingCharacterMailSentNumber());
    }
}
