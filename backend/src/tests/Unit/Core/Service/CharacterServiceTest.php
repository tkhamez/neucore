<?php declare(strict_types=1);

namespace Tests\Unit\Core\Service;

use Brave\Core\Entity\Character;
use Brave\Core\Entity\Corporation;
use Brave\Core\Repository\CharacterRepository;
use Brave\Core\Entity\Player;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\CharacterService;
use Brave\Core\Service\OAuthToken;
use Brave\Core\Service\ObjectManager;
use Brave\Sso\Basics\EveAuthentication;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Token\AccessToken;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tests\Helper;
use Tests\OAuthProvider;
use Tests\Client;

class CharacterServiceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var OAuthToken
     */
    private $token;

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

        $this->client = new Client();
        $this->token = new OAuthToken(new OAuthProvider($this->client), new ObjectManager($em, $log), $log);
        $this->service = new CharacterService($log, new ObjectManager($em, $log));
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

    public function testMoveCharacterToNewPlayer()
    {
        $char = new Character();
        $char->setId(100);
        $char->setName('char name');
        $player = new Player();
        $player->setName($char->getName());
        $player->addCharacter($char);
        $char->setPlayer($player);

        $character = $this->service->moveCharacterToNewAccount($char);

        $this->assertSame($char, $character);
        $this->assertNotSame($player, $character->getPlayer());
        $this->assertSame('char name', $character->getPlayer()->getName());
    }

    public function testUpdateAndStoreCharacterWithPlayer()
    {
        $player = (new Player())->setName('name');
        $char = (new Character())->setId(12)->setPlayer($player);

        $expires = time() + (60 * 20);
        $result = $this->service->updateAndStoreCharacterWithPlayer(
            $char,
            new EveAuthentication(
                null,
                'name',
                'character-owner-hash',
                new AccessToken(['access_token' => 'a-t', 'refresh_token' => 'r-t', 'expires' => $expires]),
                ['scope1', 'scope2']
            )
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

    public function testCheckTokenUpdateCharacterDeletesBiomassedChar()
    {
        $em = $this->helper->getEm();
        $corp = (new Corporation())->setId(1000001); // Doomheim
        $char = (new Character())->setId(31)->setName('n31')->setCorporation($corp);
        $em->persist($corp);
        $em->persist($char);
        $em->flush();

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(CharacterService::CHECK_CHAR_DELETED, $result);

        $em->clear();
        $character = $this->charRepo->find(31);
        $this->assertNull($character);
    }

    public function testCheckCharacterNoToken()
    {
        $result = $this->service->checkCharacter(new Character(), $this->token);
        $this->assertSame(CharacterService::CHECK_TOKEN_NOK, $result);
    }

    public function testCheckCharacterInvalidToken()
    {
        $em = $this->helper->getEm();
        $char = (new Character())
            ->setId(31)->setName('n31')
            ->setValidToken(false) // it's also the default
            ->setCharacterOwnerHash('hash')
            ->setAccessToken('at')->setRefreshToken('rt')->setExpires(time() - 1000);
        $em->persist($char);
        $em->flush();

        $this->client->setResponse(
            // for refreshAccessToken()
            new Response(400, [], '{"error": "invalid_token"}')
        );

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(CharacterService::CHECK_TOKEN_NOK, $result);
    }

    public function testCheckCharacterRequestError()
    {
        $this->client->setResponse(
            // for refreshAccessToken()
            new Response(200, [], '{"access_token": "new-at"}'),

            // for getResourceOwner()
            new Response(500)
        );

        $em = $this->helper->getEm();
        $expires = time() - 1000;
        $char = (new Character())
            ->setId(31)->setName('n31')
            ->setValidToken(false) // it's also the default
            ->setCharacterOwnerHash('hash')
            ->setAccessToken('at')->setRefreshToken('rt')->setExpires($expires);
        $em->persist($char);
        $em->flush();

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(CharacterService::CHECK_REQUEST_ERROR, $result);
    }

    public function testCheckCharacterValid()
    {
        $this->client->setResponse(
            // for refreshAccessToken()
            new Response(200, [], '{
                "access_token": "new-at"
            }'),

            // for getResourceOwner()
            new Response(200, [], '{
                "CharacterOwnerHash": "hash"
            }')
        );

        $em = $this->helper->getEm();
        $expires = time() - 1000;
        $char = (new Character())
            ->setId(31)->setName('n31')
            ->setValidToken(false) // it's also the default
            ->setCharacterOwnerHash('hash')
            ->setAccessToken('at')->setRefreshToken('rt')->setExpires($expires);
        $em->persist($char);
        $em->flush();

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(CharacterService::CHECK_TOKEN_OK, $result);

        $em->clear();
        $character = $this->charRepo->find(31);
        $this->assertTrue($character->getValidToken());
        $this->assertSame('at', $character->getAccessToken()); // not updated
        $this->assertSame('rt', $character->getRefreshToken()); // not updated
        $this->assertSame($expires, $character->getExpires()); // not updated
    }

    public function testCheckTokenUpdateCharacterDeletesMovedChar()
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
        $char = (new Character())
            ->setId(31)->setName('n31')
            ->setCharacterOwnerHash('old-hash')
            ->setAccessToken('at')->setRefreshToken('rt')->setExpires($expires);
        $em->persist($char);
        $em->flush();

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(CharacterService::CHECK_CHAR_DELETED, $result);

        $em->clear();
        $character = $this->charRepo->find(31);
        $this->assertNull($character);
    }
}
