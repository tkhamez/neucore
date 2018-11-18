<?php declare(strict_types=1);

namespace Brave\Core\Service;

use Brave\Core\Entity\SystemVariable;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Sso\Basics\EveAuthentication;

class EveMail
{
    /**
     * @var RepositoryFactory
     */
    private $repositoryFactory;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    public function __construct(RepositoryFactory $repositoryFactory, ObjectManager $objectManager) {
        $this->repositoryFactory = $repositoryFactory;
        $this->objectManager = $objectManager;
    }

    public function storeMailCharacter(EveAuthentication $eveAuth)
    {
        $repo = $this->repositoryFactory->getSystemVariableRepository();
        $char = $repo->find(SystemVariable::MAIL_CHARACTER);
        $token = $repo->find(SystemVariable::MAIL_TOKEN);
        if ($char === null || $token === null) {
            return false;
        }

        $char->setValue($eveAuth->getCharacterName());

        $token->setValue(json_encode([
            'id' => (int) $eveAuth->getCharacterId(),
            'access' => $eveAuth->getToken()->getToken(),
            'expires' => $eveAuth->getToken()->getExpires(),
            'refresh' => $eveAuth->getToken()->getRefreshToken(),
        ]));

        return $this->objectManager->flush();
    }
}
