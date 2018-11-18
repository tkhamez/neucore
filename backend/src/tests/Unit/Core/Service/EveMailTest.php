<?php declare(strict_types=1);

namespace Tests\Unit\Core\Service;

use Brave\Core\Entity\SystemVariable;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\EveMail;
use Brave\Core\Service\ObjectManager;
use Brave\Sso\Basics\EveAuthentication;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Client\Token\AccessToken;
use Monolog\Logger;
use Tests\Helper;

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

    public function setUp()
    {
        $helper = new Helper();
        $helper->emptyDb();

        $this->em = $helper->getEm();
        $this->repoFactory = new RepositoryFactory($this->em);
        $logger = new Logger('test');
        $objManager = new ObjectManager($this->em, $logger);
        $this->eveMail = new EveMail($this->repoFactory, $objManager);
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

        #$this->em->flush();

        $charActual = $this->repoFactory->getSystemVariableRepository()->find(SystemVariable::MAIL_CHARACTER);
        $tokenActual = $this->repoFactory->getSystemVariableRepository()->find(SystemVariable::MAIL_TOKEN);

        $this->assertSame('Name', $charActual->getValue());
        $this->assertSame([
            'id' => 123456,
            'access' => 'access',
            'expires' => 1543480210,
            'refresh' => 'refresh',
        ], json_decode($tokenActual->getValue(), true));
    }
}
