<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Unit\Service;

use Neucore\Entity\Alliance;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\Player;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\EsiApiFactory;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\Config;
use Neucore\Service\EveMail;
use Neucore\Service\OAuthToken;
use Neucore\Service\ObjectManager;
use Brave\Sso\Basics\EveAuthentication;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Token\AccessToken;
use Monolog\Logger;
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
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var RepositoryFactory
     */
    private $repoFactory;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var Logger
     */
    private $logger;

    protected function setUp(): void
    {
        $helper = new Helper();
        $helper->emptyDb();

        $this->em = $helper->getEm();
        $this->repoFactory = new RepositoryFactory($this->em);
        $this->client = new Client();

        $this->logger = new Logger('test');
        $objManager = new ObjectManager($this->em, $this->logger);
        $config = new Config(['eve' => ['datasource' => '', 'esi_host' => '']]);

        $esiFactory = new EsiApiFactory($this->client, $config);

        $oauth = new OAuthProvider($this->client);
        $oauthToken = new OAuthToken($oauth, $objManager, $this->logger, $this->client, $config);

        $this->eveMail = new EveMail(
            $this->repoFactory,
            $objManager,
            $oauthToken,
            $esiFactory,
            $this->logger,
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
        $this->em->persist($char);
        $this->em->persist($token);
        $this->em->flush();

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
        $this->em->persist($player);
        $this->em->persist($char);
        $this->em->flush();
        $playerId = $player->getId();
        $this->em->clear();

        $result = $this->eveMail->invalidTokenFindCharacter($playerId);
        $this->assertNull($result);
    }

    public function testInvalidTokenFindCharacterMain()
    {
        $player = (new Player())->setName('n');
        $char1 = (new Character())->setId(100100)->setName('n')->setPlayer($player);
        $char2 = (new Character())->setId(100101)->setName('n')->setPlayer($player)->setMain(true);
        $this->em->persist($player);
        $this->em->persist($char1);
        $this->em->persist($char2);
        $this->em->flush();
        $playerId = $player->getId();
        $this->em->clear();

        $result = $this->eveMail->invalidTokenFindCharacter($playerId);
        $this->assertSame(100101, $result);
    }

    public function testInvalidTokenFindCharacterNotMain()
    {
        $player = (new Player())->setName('n');
        $char1 = (new Character())->setId(100100)->setName('n')->setPlayer($player);
        $char2 = (new Character())->setId(100101)->setName('n')->setPlayer($player);
        $this->em->persist($player);
        $this->em->persist($char1);
        $this->em->persist($char2);
        $this->em->flush();
        $playerId = $player->getId();
        $this->em->clear();

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
        $this->em->persist($varAlli);
        $this->em->persist($varCorp);
        $this->em->flush();
        $this->em->clear();

        $result = $this->eveMail->invalidTokenMaySend(100100);
        $this->assertSame('Character not found.', $result);
    }

    public function testInvalidTokenMaySendManagedAccount()
    {
        $varAlli = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ALLIANCES))->setValue('123,456');
        $varCorp = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_CORPORATIONS))->setValue('987,654');
        $player = (new Player())->setName('n')->setStatus(Player::STATUS_MANAGED);
        $char = (new Character())->setName('n')->setId(100100)->setPlayer($player);
        $this->em->persist($varAlli);
        $this->em->persist($varCorp);
        $this->em->persist($player);
        $this->em->persist($char);
        $this->em->flush();
        $this->em->clear();

        $result = $this->eveMail->invalidTokenMaySend(100100);
        $this->assertSame('Player account status is managed.', $result);
    }

    public function testInvalidTokenMaySendAllianceAndCorporationDoesNotMatch()
    {
        $varAlli = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ALLIANCES))->setValue('123,456');
        $varCorp = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_CORPORATIONS))->setValue('987,654');
        $player = (new Player())->setName('n');
        $char = (new Character())->setName('n')->setId(100100)->setPlayer($player);
        $this->em->persist($varAlli);
        $this->em->persist($varCorp);
        $this->em->persist($player);
        $this->em->persist($char);
        $this->em->flush();
        $this->em->clear();

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
        $this->em->persist($varAlli);
        $this->em->persist($varCorp);
        $this->em->persist($player);
        $this->em->persist($alli);
        $this->em->persist($corp);
        $this->em->persist($char);
        $this->em->flush();
        $this->em->clear();

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
        $this->em->persist($varAlli);
        $this->em->persist($varCorp);
        $this->em->persist($player);
        $this->em->persist($corp);
        $this->em->persist($char);
        $this->em->flush();
        $this->em->clear();

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
        $this->em->persist($varAlli);
        $this->em->persist($varCorp);
        $this->em->persist($player);
        $this->em->persist($alli);
        $this->em->persist($corp);
        $this->em->persist($char);
        $this->em->flush();
        $this->em->clear();

        $result = $this->eveMail->invalidTokenMaySend(100100);
        $this->assertSame('', $result);
    }

    public function testInvalidTokenIsActiveDeactivated()
    {
        $varActive = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ACTIVE))->setValue('0');
        $this->em->persist($varActive);
        $this->em->flush();

        $result = $this->eveMail->invalidTokenIsActive();
        $this->assertSame('Mail is deactivated.', $result);
    }

    public function testInvalidTokenIsActive()
    {
        $varActive = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ACTIVE))->setValue('1');
        $this->em->persist($varActive);
        $this->em->flush();

        $result = $this->eveMail->invalidTokenIsActive();
        $this->assertSame('', $result);
    }

    public function testInvalidTokenSendMissingCharacter()
    {
        $varActive = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ACTIVE))->setValue('1');
        $this->em->persist($varActive);
        $this->em->flush();

        $result = $this->eveMail->invalidTokenSend(123);
        $this->assertSame('Missing character that can send mails.', $result);
    }

    public function testInvalidTokenSendMissingSubject()
    {
        $varActive = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ACTIVE))->setValue('1');
        $varToken = (new SystemVariable(SystemVariable::MAIL_TOKEN))->setValue('{"id": "123"}');
        $this->em->persist($varActive);
        $this->em->persist($varToken);
        $this->em->flush();

        $result = $this->eveMail->invalidTokenSend(123);
        $this->assertSame('Missing subject.', $result);
    }

    public function testInvalidTokenSendMissingBody()
    {
        $varActive = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ACTIVE))->setValue('1');
        $varToken = (new SystemVariable(SystemVariable::MAIL_TOKEN))->setValue('{"id": "123"}');
        $varSubject = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_SUBJECT))->setValue('s');
        $this->em->persist($varActive);
        $this->em->persist($varToken);
        $this->em->persist($varSubject);
        $this->em->flush();

        $result = $this->eveMail->invalidTokenSend(123);
        $this->assertSame('Missing body text.', $result);
    }

    public function testInvalidTokenSendMissingTokenData()
    {
        $varActive = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ACTIVE))->setValue('1');
        $varSubject = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_SUBJECT))->setValue('s');
        $varBody = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_BODY))->setValue('b');
        $varToken = (new SystemVariable(SystemVariable::MAIL_TOKEN))->setValue('{"id": "123"}');
        $this->em->persist($varActive);
        $this->em->persist($varSubject);
        $this->em->persist($varBody);
        $this->em->persist($varToken);
        $this->em->flush();

        $result = $this->eveMail->invalidTokenSend(123);
        $this->assertSame('Missing token data.', $result);
    }

    public function testInvalidTokenSendInvalidToken()
    {
        $varActive = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ACTIVE))->setValue('1');
        $varSubject = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_SUBJECT))->setValue('s');
        $varBody = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_BODY))->setValue('b');
        $varToken = new SystemVariable(SystemVariable::MAIL_TOKEN);
        $varToken->setValue((string) \json_encode([
            'id' => 123,
            'access' => 'access-token',
            'refresh' => 'refresh-token',
            'expires' => 1542546430,
        ]));
        $this->em->persist($varActive);
        $this->em->persist($varSubject);
        $this->em->persist($varBody);
        $this->em->persist($varToken);
        $this->em->flush();

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
        $varActive = (new SystemVariable(SystemVariable::MAIL_INVALID_TOKEN_ACTIVE))->setValue('1');
        $this->em->persist($varToken);
        $this->em->persist($varSubject);
        $this->em->persist($varBody);
        $this->em->persist($varActive);
        $this->em->flush();

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

        $this->em->persist($player);
        $this->em->flush();
        $playerId = $player->getId();

        $this->eveMail->invalidTokenMailSent($playerId, true);
        $this->em->clear();
        $player2 = $this->repoFactory->getPlayerRepository()->find($playerId);
        $this->assertTrue($player2->getDeactivationMailSent());

        $this->eveMail->invalidTokenMailSent($playerId, false);
        $this->em->clear();
        $player3 = $this->repoFactory->getPlayerRepository()->find($playerId);
        $this->assertFalse($player3->getDeactivationMailSent());
    }


    public function testSendMail()
    {
        $this->client->setResponse(new Response(200, [], '373515628'));

        $result = $this->eveMail->sendMail(123, 'access-token', 'subject', 'body', [456]);

        $this->assertSame('', $result);
    }
}
