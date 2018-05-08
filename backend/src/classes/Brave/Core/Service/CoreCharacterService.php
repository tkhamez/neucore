<?php declare(strict_types=1);

namespace Brave\Core\Service;

use Brave\Core\Entity\Character;
use Brave\Core\Entity\Player;
use Brave\Core\Entity\Role;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Log\LoggerInterface;

class CoreCharacterService
{
    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(
        LoggerInterface $log,
        EntityManagerInterface $em
    ) {
        $this->log = $log;
        $this->em = $em;
    }

    /**
     * Creates and stores a new Character and Player.
     *
     * This is for characters who have not signed up with EVE SSO.
     *
     * @param int $characterId
     * @param string $characterName
     * @return boolean
     */
    public function addCharacter(int $characterId, string $characterName)
    {
        return $this->updateAndStoreCharacterWithPlayer(
            $this->createNewPlayerWithMain($characterId, $characterName)
        );
    }

    /**
     * Creates Player and Character objects.
     *
     * Does not persist them in the database.
     *
     * @param int $characterId
     * @param string $characterName
     * @param Role|null $role
     * @return Character
     */
    public function createNewPlayerWithMain(
        int $characterId,
        string $characterName,
        Role $role =  null
    ): Character
    {
        $player = new Player();
        $player->setName($characterName);
        if ($role !== null) {
            $player->addRole($role);
        }

        $char = new Character();
        $char->setId($characterId);
        $char->setName($characterName);
        $char->setMain(true);
        $char->setPlayer($player);
        $player->addCharacter($char);

        return $char;
    }

    public function updateAndStoreCharacterWithPlayer(
        Character $char,
        string $characterOwnerHash = null,
        AccessToken $token = null,
        string $scopes = null
    ): bool
    {
        $char->setCharacterOwnerHash($characterOwnerHash);
        $char->setScopes($scopes);

        if ($token !== null) {
            $char->setAccessToken($token->getToken());
            $char->setExpires($token->getExpires());
            $char->setRefreshToken($token->getRefreshToken());
        }

        try {
            $this->em->persist($char->getPlayer()); // could be a new player
            $this->em->persist($char); // could be a new character
            $this->em->flush();
        } catch (\Exception $e) {
            print_r($e->getMessage());
            $this->log->critical($e->getMessage(), ['exception' => $e]);
            return false;
        }

        return true;
    }
}
