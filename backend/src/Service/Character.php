<?php

declare(strict_types=1);

namespace Neucore\Service;

use Neucore\Entity\CharacterNameChange;

class Character
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    public function __construct(ObjectManager $objectManager)
    {
        $this->objectManager = $objectManager;
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
            $characterNameChange = new CharacterNameChange();
            $characterNameChange->setCharacter($character);
            $characterNameChange->setOldName($oldName);
            $characterNameChange->setChangeDate(new \DateTime());
            $character->addCharacterNameChange($characterNameChange);
            $this->objectManager->persist($characterNameChange);
        }

        $character->setName($name);
    }
}
