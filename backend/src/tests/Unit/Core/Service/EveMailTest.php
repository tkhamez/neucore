<?php declare(strict_types=1);

namespace Tests\Unit\Core\Service;

use Brave\Core\Entity\SystemVariable;
use Brave\Core\Factory\EsiApiFactory;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\EsiApi;
use Brave\Core\Service\EveMail;
use Brave\Core\Service\OAuthToken;
use Brave\Core\Service\ObjectManager;
use Brave\Sso\Basics\EveAuthentication;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Token\AccessToken;
use Monolog\Logger;
use Tests\Client;
use Tests\Helper;
use Tests\OAuthProvider;

class EveMailTest extends \PHPUnit\Framework\TestCase
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

    public function setUp()
    {
        $helper = new Helper();
        $helper->emptyDb();

        $this->em = $helper->getEm();
        $this->repoFactory = new RepositoryFactory($this->em);
        $this->client = new Client();

        $logger = new Logger('test');
        $objManager = new ObjectManager($this->em, $logger);

        $esiFactory = (new EsiApiFactory())->setClient($this->client);
        $esiApi = new EsiApi($logger, $esiFactory);

        $oauth = new OAuthProvider($this->client);
        $oauthToken = new OAuthToken($oauth, $objManager, $logger);

        $this->eveMail = new EveMail($this->repoFactory, $objManager, $oauthToken, $esiApi);
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

    public function testSendMailDeactivated()
    {
        $varActive = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_ACTIVE))->setValue('0');
        $this->em->persist($varActive);
        $this->em->flush();

        $result = $this->eveMail->sendAccountDeactivatedMail(123);
        $this->assertSame('This mail is deactivated.', $result);
    }

    public function testSendMailMissingData()
    {
        $varActive = (new SystemVariable(SystemVariable::MAIL_ACCOUNT_DISABLED_ACTIVE))->setValue('1');
        $this->em->persist($varActive);
        $this->em->flush();

        $result = $this->eveMail->sendAccountDeactivatedMail(123);
        $this->assertSame('Missing data.', $result);
    }

    public function testSendMailMissingTokenData()
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

        $result = $this->eveMail->sendAccountDeactivatedMail(123);
        $this->assertSame('Missing token data.', $result);
    }

    public function testSendMail()
    {
        $varToken = new SystemVariable(SystemVariable::MAIL_TOKEN);
        $varToken->setValue(\json_encode([
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
            new Response(200, [], '{
                "access_token": "new-token",
                "refresh_token": "",
                "expires": 1519933900}' // 03/01/2018 @ 7:51pm (UTC)
            ),

            // for postCharactersCharacterIdMail()
            new Response(200, [], 373515628)
        );

        $result = $this->eveMail->sendAccountDeactivatedMail(456);
        $this->assertSame('', $result);
    }
}
