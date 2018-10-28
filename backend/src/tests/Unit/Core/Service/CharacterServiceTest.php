<?php declare(strict_types=1);

namespace Tests\Unit\Core\Service;

use Brave\Core\Entity\Character;
use Brave\Core\Repository\CharacterRepository;
use Brave\Core\Entity\Player;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\CharacterService;
use Brave\Core\Service\OAuthToken;
use Brave\Core\Service\ObjectManager;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Token\AccessToken;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tests\Helper;
use Tests\OAuthTestProvider;
use Tests\TestClient;

class CharacterServiceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var TestClient
     */
    private $client;

    /**
     * @var CharacterService
     */
    private $service;

    /**
     * @var CharacterRepository
     */
    private $charRepo;

    public function setUp()
    {
        $this->helper = new Helper();
        $this->helper->emptyDb();
        $em = $this->helper->getEm();

        $log = new Logger('Test');
        $log->pushHandler(new TestHandler());

        $this->client = new TestClient();
        $token = new OAuthToken(new OAuthTestProvider($this->client), new ObjectManager($em, $log), $log);

        $this->service = new CharacterService($log, new ObjectManager($em, $log), $token);
        $this->charRepo = (new RepositoryFactory($em))->getCharacterRepository();
    }

    public function testCreateNewPlayerWithMain()
    {
        $character = $this->service->createNewPlayerWithMain(234, 'bcd');

        $this->assertTrue($character->getMain());
        $this->assertSame(234, $character->getId());
        $this->assertSame('bcd', $character->getName());
        $this->assertSame('bcd', $character->getPlayer()->getName());
        $this->assertSame([], $character->getPlayer()->getRoles());
    }

    public function testUpdateAndStoreCharacterWithPlayer()
    {
        $player = (new Player())->setName('name');
        $char = (new Character())->setId(12)->setPlayer($player);

        $expires = time() + (60 * 20);
        $result = $this->service->updateAndStoreCharacterWithPlayer(
            $char,
            'name',
            'character-owner-hash',
            'scope1 scope2',
            new AccessToken(['access_token' => 'a-t', 'refresh_token' => 'r-t', 'expires' => $expires])
        );
        $this->assertTrue($result);

        $this->helper->getEm()->clear();

        $character = $this->charRepo->find(12);

        $this->assertSame('name', $character->getName());
        $this->assertFalse($character->getMain());
        $this->assertSame('name', $character->getPlayer()->getName());

        $this->assertSame('character-owner-hash', $character->getCharacterOwnerHash());
        $this->assertSame('a-t', $character->getAccessToken());
        $this->assertSame('r-t', $character->getRefreshToken());
        $this->assertSame($expires, $character->getExpires());
        $this->assertSame('scope1 scope2', $character->getScopes());
    }

    public function testCheckTokenUpdateCharacterInvalid()
    {
        // can't really test the difference between no token and revoked token
        // here, but that is done in OAuthTokenTest

        $this->client->setResponse(new Response());

        $result = $this->service->checkTokenUpdateCharacter(new Character());
        $this->assertFalse($result);
    }

    public function testCheckTokenUpdateCharacterValid()
    {
        $this->client->setResponse(
            // for refreshAccessToken()
            new Response(200, [], '{
                "access_token": "new-at"
            }'),

            // for getResourceOwner()
            new Response(200, [], '{
                "CharacterOwnerHash": "new-hash"
            }')
        );

        $em = $this->helper->getEm();
        $expires = time() - 1000;
        $char = (new Character())->setId(31)->setName('n31')
            ->setCharacterOwnerHash('old-hash')
            ->setAccessToken('at')->setRefreshToken('rt')->setExpires($expires);
        $em->persist($char);
        $em->flush();

        $result = $this->service->checkTokenUpdateCharacter($char);
        $this->assertTrue($result);

        $em->clear();

        $character = $this->charRepo->find(31);

        $this->assertSame('new-hash', $character->getCharacterOwnerHash()); // updated
        $this->assertSame('at', $character->getAccessToken()); // not updated
        $this->assertSame('rt', $character->getRefreshToken()); // not updated
        $this->assertSame($expires, $character->getExpires()); // not updated
    }
}
