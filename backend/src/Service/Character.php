<?php

declare(strict_types=1);

namespace Neucore\Service;

use Neucore\Entity\CharacterNameChange;
use Neucore\Factory\RepositoryFactory;

class Character
{
    private ObjectManager $objectManager;

    private RepositoryFactory $repositoryFactory;

    public function __construct(ObjectManager $objectManager, RepositoryFactory $repositoryFactory)
    {
        $this->objectManager = $objectManager;
        $this->repositoryFactory = $repositoryFactory;
    }

    /**
     * Set character name if it is not empty.
     *
     * Creates a new CharacterNameChange if the name changed, persists the new object, but does not flush
     * the entity manager.
     */
    public function setCharacterName(\Neucore\Entity\Character $character, string $name): void
    {
        if ($name === '') { // don't update name if it is empty
            return;
        }

        $oldName = $character->getName();
        if ($oldName !== '' && $oldName !== $name) {
            $this->createNewRecord($character, $oldName);
        }

        $character->setName($name);
    }

    /**
     * Checks if the name changed and adds a record for it if it is missing.
     *
     * Persists the new object, but does not flush the entity manager.
     *
     * Returns true if a new record was created, otherwise false.
     */
    public function addCharacterNameChange(\Neucore\Entity\Character $character, string $oldName): bool
    {
        if ($oldName === '' || $character->getName() === $oldName) {
            return false;
        }

        $record = $this->repositoryFactory
            ->getCharacterNameChangeRepository()
            ->findOneBy(['character' => $character, 'oldName' => $oldName]);

        if ($record !== null) {
            return false;
        }

        $this->createNewRecord($character, $oldName);

        return true;
    }

    private function createNewRecord(\Neucore\Entity\Character $character, string $oldName): void
    {
        $characterNameChange = new CharacterNameChange();
        $characterNameChange->setCharacter($character);
        $characterNameChange->setOldName($oldName);
        $characterNameChange->setChangeDate(new \DateTime());
        $character->addCharacterNameChange($characterNameChange);
        $this->objectManager->persist($characterNameChange);
    }
}
