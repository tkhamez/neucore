<?php declare(strict_types=1);

namespace Tests\Unit\Core\Service;

use Brave\Core\Entity\Character;
use Brave\Core\Entity\Corporation;
use Brave\Core\Entity\CorporationMember;
use Brave\Core\Entity\SystemVariable;
use Brave\Core\Repository\CharacterRepository;
use Brave\Core\Entity\Player;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Repository\RemovedCharacterRepository;
use Brave\Core\Service\Account;
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

class AccountTest extends \PHPUnit\Framework\TestCase
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
     * @var Account
     */
    private $service;

    /**
     * @var CharacterRepository
     */
    private $charRepo;

    /**
     * @var RemovedCharacterRepository
     */
    private $removedCharRepo;

    public function setUp()
    {
        $this->helper = new Helper();
        $this->helper->emptyDb();
        $em = $this->helper->getEm();

        $log = new Logger('Test');
        $log->pushHandler(new TestHandler());

        $this->client = new Client();
        $this->token = new OAuthToken(new OAuthProvider($this->client), new ObjectManager($em, $log), $log);
        $this->service = new Account($log, new ObjectManager($em, $log), new RepositoryFactory($em));
        $this->charRepo = (new RepositoryFactory($em))->getCharacterRepository();
        $this->removedCharRepo = (new RepositoryFactory($em))->getRemovedCharacterRepository();
    }

    public function testCreateNewPlayerWithMain()
    {
        $character = $this->service->createNewPlayerWithMain(234, 'bcd');

        $this->assertTrue($character->getMain());
        $this->assertSame(234, $character->getId());
        $this->assertSame('bcd', $character->getName());
        $this->assertSame('bcd#0', $character->getPlayer()->getName());
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
        $this->assertSame('char name#0', $character->getPlayer()->getName());

        $this->assertSame(100, $player->getRemovedCharacters()[0]->getCharacterId());
        $this->assertSame($character->getPlayer(), $player->getRemovedCharacters()[0]->getNewPlayer());
        $this->assertSame('moved', $player->getRemovedCharacters()[0]->getAction());
        $this->assertSame($character->getPlayer(), $player->getRemovedCharacters()[0]->getNewPlayer());
    }

    public function testUpdateAndStoreCharacterWithPlayer()
    {
        $player = (new Player())->setName('name');
        $char = (new Character())->setName('char name')->setId(12)->setMain(true);
        $char->setPlayer($player);
        $player->addCharacter($char);

        $expires = time() + (60 * 20);
        $result = $this->service->updateAndStoreCharacterWithPlayer(
            $char,
            new EveAuthentication(
                null,
                'char name changed',
                'character-owner-hash',
                new AccessToken(['access_token' => 'a-t', 'refresh_token' => 'r-t', 'expires' => $expires]),
                ['scope1', 'scope2']
            )
        );
        $this->assertTrue($result);

        $this->helper->getEm()->clear();

        $character = $this->charRepo->find(12);

        $this->assertSame('char name changed', $character->getName());
        $this->assertTrue($character->getMain());
        $this->assertSame('char name changed#' . $player->getId(), $player->getName());

        $this->assertSame('character-owner-hash', $character->getCharacterOwnerHash());
        $this->assertSame('a-t', $character->getAccessToken());
        $this->assertSame('r-t', $character->getRefreshToken());
        $this->assertSame($expires, $character->getExpires());
        $this->assertSame('scope1 scope2', $character->getScopes());
        $this->assertTrue($character->getValidToken());
    }

    public function testUpdateAndStoreCharacterWithPlayerNoToken()
    {
        $player = (new Player())->setName('p-name');
        $char = (new Character())->setName('c-name')->setId(12)->setPlayer($player);

        $result = $this->service->updateAndStoreCharacterWithPlayer(
            $char,
            new EveAuthentication(
                null,
                'name',
                'character-owner-hash',
                new AccessToken(['access_token' => 'a-t']),
                []
            )
        );
        $this->assertTrue($result);

        $this->helper->getEm()->clear();

        $character = $this->charRepo->find(12);
        $this->assertNull($character->getRefreshToken());
        $this->assertNull($character->getValidToken());
    }

    public function testCheckTokenUpdateCharacterDeletesBiomassedChar()
    {
        $em = $this->helper->getEm();
        $corp = (new Corporation())->setId(1000001); // Doomheim
        $player = (new Player())->setName('p');
        $char = (new Character())->setId(31)->setName('n31')->setCorporation($corp)->setPlayer($player);
        $em->persist($corp);
        $em->persist($player);
        $em->persist($char);
        $em->flush();

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(Account::CHECK_CHAR_DELETED, $result);

        $em->clear();
        $character = $this->charRepo->find(31);
        $this->assertNull($character);

        $removedChar = $this->removedCharRepo->findOneBy(['characterId' => 31]);
        $this->assertSame(31, $removedChar->getCharacterId());
        $this->assertSame('deleted (biomassed)', $removedChar->getAction());
    }

    public function testCheckCharacterNoToken()
    {
        $char = (new Character())->setId(100)->setName('name')->setValidToken(false);
        $this->helper->getEm()->persist($char);
        $this->helper->getEm()->flush();

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(Account::CHECK_TOKEN_NA, $result);

        $this->assertNull($char->getValidToken()); // no token = NULL
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
        $this->assertSame(Account::CHECK_TOKEN_NOK, $result);
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
        $this->assertSame(Account::CHECK_REQUEST_ERROR, $result);
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
        $this->assertSame(Account::CHECK_TOKEN_OK, $result);

        $em->clear();
        $character = $this->charRepo->find(31);
        $this->assertTrue($character->getValidToken());
        $this->assertSame('at', $character->getAccessToken()); // not updated
        $this->assertSame('rt', $character->getRefreshToken()); // not updated
        $this->assertSame($expires, $character->getExpires()); // not updated
    }

    public function testCheckCharacterDeletesMovedChar()
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
        $player = (new Player())->setName('p');
        $char = (new Character())
            ->setPlayer($player)
            ->setId(31)->setName('n31')
            ->setCharacterOwnerHash('old-hash')
            ->setAccessToken('at')->setRefreshToken('rt')->setExpires($expires);
        $em->persist($player);
        $em->persist($char);
        $em->flush();

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(Account::CHECK_CHAR_DELETED, $result);

        $em->clear();
        $character = $this->charRepo->find(31);
        $this->assertNull($character);

        $removedChar = $this->removedCharRepo->findOneBy(['characterId' => 31]);
        $this->assertSame(31, $removedChar->getCharacterId());
        $this->assertSame('deleted (EVE account changed)', $removedChar->getAction());
    }

    public function testRemoveCharacterFromPlayer()
    {
        $player = (new Player())->setName('player 1');
        $char = (new Character())->setId(10)->setName('char')->setPlayer($player);
        $player->addCharacter($char);
        $this->helper->getEm()->persist($player);
        $this->helper->getEm()->persist($char);
        $this->helper->getEm()->flush();

        $this->service->removeCharacterFromPlayer($char, $player);
        $this->helper->getEm()->flush();

        $this->helper->getEm()->clear();

        $this->assertSame(0, count($player->getCharacters()));
        $this->assertSame(10, $player->getRemovedCharacters()[0]->getCharacterId());
        $this->assertSame('char', $player->getRemovedCharacters()[0]->getCharacterName());
        $this->assertSame('player 1', $player->getRemovedCharacters()[0]->getPlayer()->getName());
        $this->assertEquals(time(), $player->getRemovedCharacters()[0]->getRemovedDate()->getTimestamp(), '', 10);
        $this->assertSame($player, $player->getRemovedCharacters()[0]->getNewPlayer());
        $this->assertSame('moved', $player->getRemovedCharacters()[0]->getAction());

        // tests that the new object was persisted.
        $removedChars = $this->removedCharRepo->findBy([]);
        $this->assertSame(10, $removedChars[0]->getCharacterId());
    }

    public function testDeleteCharacter()
    {
        $player = (new Player())->setName('player 1');
        $char = (new Character())->setId(10)->setName('char')->setPlayer($player);
        $member = (new CorporationMember())->setId(10)->setCharacter($char);
        $player->addCharacter($char);
        $this->helper->getEm()->persist($player);
        $this->helper->getEm()->persist($char);
        $this->helper->getEm()->persist($member);
        $this->helper->getEm()->flush();

        $this->service->deleteCharacter($char, 'manually');
        $this->helper->getEm()->flush();

        $this->assertSame(0, count($player->getCharacters()));

        $chars = $this->charRepo->findBy([]);
        $this->assertSame(0, count($chars));
        $removedChars = $this->removedCharRepo->findBy([]);
        $this->assertSame(1, count($removedChars));
        $this->assertSame(10, $removedChars[0]->getCharacterId());
        $this->assertSame('char', $removedChars[0]->getCharacterName());
        $this->assertSame($player->getId(), $removedChars[0]->getPlayer()->getId());
        $this->assertSame('player 1', $removedChars[0]->getPlayer()->getName());
        $this->assertEquals(time(), $removedChars[0]->getRemovedDate()->getTimestamp(), '', 10);
        $this->assertNull($removedChars[0]->getNewPlayer());
        $this->assertSame('deleted (manually)', $removedChars[0]->getAction());
    }

    public function testDeleteCharacterWithoutPlayer()
    {
        $char = (new Character())->setId(10)->setName('char');
        $this->helper->getEm()->persist($char);
        $this->helper->getEm()->flush();

        $this->service->deleteCharacter($char, 'manually');
        $this->helper->getEm()->flush();

        $chars = $this->charRepo->findBy([]);
        $this->assertSame(0, count($chars));
        $removedChars = $this->removedCharRepo->findBy([]);
        $this->assertSame(1, count($removedChars));
        $this->assertSame(10, $removedChars[0]->getCharacterId());
        $this->assertNull($removedChars[0]->getPlayer());
    }

    public function testGroupsDeactivatedValidToken()
    {
        // activate "deactivated accounts"
        $setting = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $this->helper->getEm()->persist($setting);
        $this->helper->getEm()->flush();

        $player = (new Player())->addCharacter(
            (new Character())->setValidToken(true)
        );

        $this->assertFalse($this->service->groupsDeactivated($player));
    }

    public function testGroupsDeactivatedInvalidToken()
    {
        // activate "deactivated accounts"
        $setting = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $this->helper->getEm()->persist($setting);
        $this->helper->getEm()->flush();

        $player = (new Player())->addCharacter(
            (new Character())->setValidToken(false)
        );

        $this->assertTrue($this->service->groupsDeactivated($player));
    }

    public function testGroupsDeactivatedInvalidTokenManaged()
    {
        // activate "deactivated accounts"
        $setting = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $this->helper->getEm()->persist($setting);
        $this->helper->getEm()->flush();

        $player = (new Player())
            ->setStatus(Player::STATUS_MANAGED)
            ->addCharacter((new Character())->setValidToken(false));

        $this->assertFalse($this->service->groupsDeactivated($player));
    }

    public function testGroupsDeactivatedInvalidTokenWithDelay()
    {
        // feature "deactivated accounts" is active, account has invalid token but only for a short time
        $setting = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $delay = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_DELAY))->setValue('24');
        $this->helper->getEm()->persist($setting);
        $this->helper->getEm()->persist($delay);
        $this->helper->getEm()->flush();

        $player = (new Player())->addCharacter(
            (new Character())->setValidToken(false)->setValidTokenTime(date_create("now -12 hours"))
        );

        $this->assertFalse($this->service->groupsDeactivated($player));
    }

    public function testGroupsDeactivatedInvalidTokenIgnoreDelay()
    {
        // feature "deactivated accounts" is active, account has invalid token but only for a short time
        $setting = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $delay = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_DELAY))->setValue('24');
        $this->helper->getEm()->persist($setting);
        $this->helper->getEm()->persist($delay);
        $this->helper->getEm()->flush();

        $player = (new Player())->addCharacter(
            (new Character())->setValidToken(false)->setValidTokenTime(date_create("now -12 hours"))
        );

        $this->assertTrue($this->service->groupsDeactivated($player, true));
    }

    public function testGroupsDeactivatedInvalidTokenSettingNotActive()
    {
        $player = (new Player())->addCharacter(
            (new Character())->setValidToken(false)
        );

        // test with missing setting
        $this->assertFalse($this->service->groupsDeactivated($player));

        // add "deactivated accounts" setting set to 0
        $setting = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('0');
        $this->helper->getEm()->persist($setting);
        $this->helper->getEm()->flush();

        $this->assertFalse($this->service->groupsDeactivated($player));
    }
}
