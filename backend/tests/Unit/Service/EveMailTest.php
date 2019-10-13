<?php declare(strict_types=1);

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

    public function setUp()
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

    public function testAccountDeactivateFindCharacterPlayerNotFound()
    {
        $result = $this->eveMail->accountDeactivatedFindCharacter(100100);
        $this->assertNull($result);
    }

    public function testAccountDeactivateFindCharacterNoInvalidToken()
    {
        $player = (new Player())->setName('n');
        $char = (new Character())->setId(100100)->setName('n')->setPlayer($player);
        $char->setValidToken(true);
        $this->em->persist($player);
        $this->em->persist($char);
        $this->em->flush();
        $playerId = $player->getId();
        $this->em->clear();

        $result = $this->eveMail->accountDeactivatedFindCharacter($playerId);
        $this->assertNull($result);
    }

    public function testAccountDeactivateFindCharacterMain()
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

        $result = $this->eveMail->accountDeactivatedFindCharacter($playerId);
        $this->assertSame(100101, $result);
    }

    public function testAccountDeactivateFindCharacterNotMain()
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

        $result = $this->eveMail->accountDeactivatedFindCharacter($playerId);
        $this->assertSame(100100, $result);
    }

    public function testAccountDeactivateMaySendAllianceSettingsNotFound()
    {
        $result = $this->eveMail->accountDeactivatedMaySend(100100);
        $this->assertSame('Alliance settings variable not found.', $result);
    }

    public function testAccountDeactivateMaySendCharacterNotFound()
    {
        $varAlli = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_ALLIANCES))->setValue('123,456');
        $this->em->persist($varAlli);
        $this->em->flush();
        $this->em->clear();

        $result = $this->eveMail->accountDeactivatedMaySend(100100);
        $this->assertSame('Character not found.', $result);
    }

    public function testAccountDeactivateMaySendManagedAccount()
    {
        $varAlli = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_ALLIANCES))->setValue('123,456');
        $player = (new Player())->setName('n')->setStatus(Player::STATUS_MANAGED);
        $char = (new Character())->setName('n')->setId(100100)->setPlayer($player);
        $this->em->persist($varAlli);
        $this->em->persist($player);
        $this->em->persist($char);
        $this->em->flush();
        $this->em->clear();

        $result = $this->eveMail->accountDeactivatedMaySend(100100);
        $this->assertSame('Player account status is managed.', $result);
    }

    public function testAccountDeactivateMaySendAllianceDoesNotMatch()
    {
        $varAlli = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_ALLIANCES))->setValue('123,456');
        $player = (new Player())->setName('n');
        $char = (new Character())->setName('n')->setId(100100)->setPlayer($player);
        $this->em->persist($varAlli);
        $this->em->persist($player);
        $this->em->persist($char);
        $this->em->flush();
        $this->em->clear();

        $result = $this->eveMail->accountDeactivatedMaySend(100100);
        $this->assertSame('No character found on account that belongs to one of the configured alliances.', $result);
    }

    public function testAccountDeactivateMaySendAlreadySent()
    {
        $varAlli = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_ALLIANCES))->setValue('123,456');
        $player = (new Player())->setName('n')->setDeactivationMailSent(true);
        $alli = (new Alliance())->setId(456);
        $corp = (new Corporation())->setId(2020)->setAlliance($alli);
        $char = (new Character())->setName('n')->setId(100100)->setPlayer($player)->setCorporation($corp);
        $this->em->persist($varAlli);
        $this->em->persist($player);
        $this->em->persist($alli);
        $this->em->persist($corp);
        $this->em->persist($char);
        $this->em->flush();
        $this->em->clear();

        $result = $this->eveMail->accountDeactivatedMaySend(100100);
        $this->assertSame('Mail already sent.', $result);
    }

    public function testAccountDeactivateMaySendIgnoreAlreadySentAndAccountStatus()
    {
        $varAlli = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_ALLIANCES))->setValue('123,456');
        $player = (new Player())->setName('n')->setDeactivationMailSent(true)->setStatus(Player::STATUS_MANAGED);
        $alli = (new Alliance())->setId(456);
        $corp = (new Corporation())->setId(2020)->setAlliance($alli);
        $char = (new Character())->setName('n')->setId(100100)->setPlayer($player)->setCorporation($corp);
        $this->em->persist($varAlli);
        $this->em->persist($player);
        $this->em->persist($alli);
        $this->em->persist($corp);
        $this->em->persist($char);
        $this->em->flush();
        $this->em->clear();

        $result = $this->eveMail->accountDeactivatedMaySend(100100, true);
        $this->assertSame('', $result);
    }

    public function testAccountDeactivateMaySendTrue()
    {
        $varAlli = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_ALLIANCES))->setValue('123,456');
        $player = (new Player())->setName('n');
        $alli = (new Alliance())->setId(456);
        $corp = (new Corporation())->setId(2020)->setAlliance($alli);
        $char = (new Character())->setName('n')->setId(100100)->setPlayer($player)->setCorporation($corp);
        $this->em->persist($varAlli);
        $this->em->persist($player);
        $this->em->persist($alli);
        $this->em->persist($corp);
        $this->em->persist($char);
        $this->em->flush();
        $this->em->clear();

        $result = $this->eveMail->accountDeactivatedMaySend(100100);
        $this->assertSame('', $result);
    }

    public function testAccountDeactivateIsActiveNotRequired()
    {
        $varToken = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('0');
        $this->em->persist($varToken);
        $this->em->flush();

        $result = $this->eveMail->accountDeactivatedIsActive();
        $this->assertSame('"Deactivate Accounts" settings is not enabled.', $result);
    }

    public function testAccountDeactivateIsActiveDeactivated()
    {
        $varToken = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $varActive = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_ACTIVE))->setValue('0');
        $this->em->persist($varToken);
        $this->em->persist($varActive);
        $this->em->flush();

        $result = $this->eveMail->accountDeactivatedIsActive();
        $this->assertSame('Mail is deactivated.', $result);
    }

    public function testAccountDeactivateIsActive()
    {
        $varToken = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $varActive = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_ACTIVE))->setValue('1');
        $this->em->persist($varToken);
        $this->em->persist($varActive);
        $this->em->flush();

        $result = $this->eveMail->accountDeactivatedIsActive();
        $this->assertSame('', $result);
    }

    public function testAccountDeactivatedSendMissingCharacter()
    {
        $varActive = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_ACTIVE))->setValue('1');
        $this->em->persist($varActive);
        $this->em->flush();

        $result = $this->eveMail->accountDeactivatedSend(123);
        $this->assertSame('Missing character that can send mails.', $result);
    }

    public function testAccountDeactivatedSendMissingSubject()
    {
        $varActive = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_ACTIVE))->setValue('1');
        $varToken = (new SystemVariable(SystemVariable::MAIL_TOKEN))->setValue('{"id": "123"}');
        $this->em->persist($varActive);
        $this->em->persist($varToken);
        $this->em->flush();

        $result = $this->eveMail->accountDeactivatedSend(123);
        $this->assertSame('Missing subject.', $result);
    }

    public function testAccountDeactivatedSendMissingBody()
    {
        $varActive = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_ACTIVE))->setValue('1');
        $varToken = (new SystemVariable(SystemVariable::MAIL_TOKEN))->setValue('{"id": "123"}');
        $varSubject = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_SUBJECT))->setValue('s');
        $this->em->persist($varActive);
        $this->em->persist($varToken);
        $this->em->persist($varSubject);
        $this->em->flush();

        $result = $this->eveMail->accountDeactivatedSend(123);
        $this->assertSame('Missing body text.', $result);
    }

    public function testAccountDeactivatedSendMissingTokenData()
    {
        $varActive = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_ACTIVE))->setValue('1');
        $varSubject = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_SUBJECT))->setValue('s');
        $varBody = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_BODY))->setValue('b');
        $varToken = (new SystemVariable(SystemVariable::MAIL_TOKEN))->setValue('{"id": "123"}');
        $this->em->persist($varActive);
        $this->em->persist($varSubject);
        $this->em->persist($varBody);
        $this->em->persist($varToken);
        $this->em->flush();

        $result = $this->eveMail->accountDeactivatedSend(123);
        $this->assertSame('Missing token data.', $result);
    }

    public function testAccountDeactivatedSendInvalidToken()
    {
        $varActive = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_ACTIVE))->setValue('1');
        $varSubject = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_SUBJECT))->setValue('s');
        $varBody = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_BODY))->setValue('b');
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

        $result = $this->eveMail->accountDeactivatedSend(123);
        $this->assertSame('Invalid token.', $result);
    }

    public function testAccountDeactivatedSend()
    {
        $varToken = new SystemVariable(SystemVariable::MAIL_TOKEN);
        $varToken->setValue((string) \json_encode([
            'id' => 123,
            'access' => 'access-token',
            'refresh' => 'refresh-token',
            'expires' => 1542546430,
        ]));
        $varSubject = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_SUBJECT))->setValue('subject 3');
        $varBody = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_BODY))->setValue("body\n\ntext");
        $varActive = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_ACTIVE))->setValue('1');
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

        $result = $this->eveMail->accountDeactivatedSend(456);
        $this->assertSame('', $result);
    }

    public function testAccountDeactivatedMailSent()
    {
        $player = (new Player())->setName('n');
        $this->assertFalse($player->getDeactivationMailSent());

        $this->em->persist($player);
        $this->em->flush();
        $playerId = $player->getId();

        $this->eveMail->accountDeactivatedMailSent($playerId, true);
        $this->em->clear();
        $player2 = $this->repoFactory->getPlayerRepository()->find($playerId);
        $this->assertTrue($player2->getDeactivationMailSent());

        $this->eveMail->accountDeactivatedMailSent($playerId, false);
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
