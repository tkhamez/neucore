<?php declare(strict_types=1);

namespace Brave\Core\Service;

use Brave\Core\Entity\Corporation;
use Brave\Core\Entity\CorporationMember;
use Brave\Core\Entity\SystemVariable;
use Brave\Core\Factory\EsiApiFactory;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Sso\Basics\EveAuthentication;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Token\ResourceOwnerAccessTokenInterface;
use Psr\Log\LoggerInterface;
use Swagger\Client\Eve\Model\GetCorporationsCorporationIdMembertracking200Ok;

class MemberTracking
{
    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var EsiApiFactory
     */
    private $esiApiFactory;

    /**
     * @var RepositoryFactory
     */
    private $repositoryFactory;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var EsiData
     */
    private $esiData;

    /**
     * @var OAuthToken
     */
    private $oauthToken;

    /**
     * @var string
     */
    private $datasource;

    public function __construct(
        LoggerInterface $log,
        EsiApiFactory $esiApiFactory,
        RepositoryFactory $repositoryFactory,
        ObjectManager $objectManager,
        EsiData $esiData,
        OAuthToken $oauthToken,
        Config $config
    ) {
        $this->log = $log;
        $this->esiApiFactory = $esiApiFactory;
        $this->repositoryFactory = $repositoryFactory;
        $this->objectManager = $objectManager;
        $this->esiData = $esiData;
        $this->oauthToken = $oauthToken;

        $this->datasource = $config->get('eve', 'datasource');
    }

    /**
     * @return bool True if character has director roles and could be stored
     */
    public function verifyAndStoreDirector(EveAuthentication $eveAuth): bool
    {
        // get corporation ID from character
        try {
            $char = $this->esiApiFactory->getCharacterApi()
                ->getCharactersCharacterId((int) $eveAuth->getCharacterId(), $this->datasource);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), ['exception' => $e]);
            return false;
        }

        // check if character has required roles
        if (! $this->verifyDirectorRole((int) $eveAuth->getCharacterId(), $eveAuth->getToken()->getToken())) {
            return false;
        }

        // get corporation - adds it to DB if missing
        $corporation = $this->esiData->fetchCorporation($char->getCorporationId());
        if ($corporation === null) {
            return false;
        }

        // store director
        return $this->storeDirector($eveAuth, $corporation);
    }

    public function removeDirector(SystemVariable $character): bool
    {
        $number = (int) explode('_', $character->getName())[2];
        $token = $this->repositoryFactory->getSystemVariableRepository()
            ->find(SystemVariable::DIRECTOR_TOKEN . $number);

        $this->objectManager->remove($character);
        if ($token) {
            $this->objectManager->remove($token);
        }

        return $this->objectManager->flush();
    }

    /**
     * Updates name and corporation of director char.
     */
    public function updateDirector(string $variableName): bool
    {
        $variable = $this->repositoryFactory->getSystemVariableRepository()->find($variableName);
        if (! $variable) {
            return false;
        }
        $data = \json_decode($variable->getValue(), true);
        if (! isset($data['character_id'])) {
            return false;
        }

        try {
            $char = $this->esiApiFactory->getCharacterApi()
                ->getCharactersCharacterId($data['character_id'], $this->datasource);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), ['exception' => $e]);
            return false;
        }

        $corporation = $this->esiData->fetchCorporation($char->getCorporationId());
        if ($corporation === null) {
            return false;
        }

        $data['character_name'] = $char->getName();
        $data['corporation_id'] = $corporation->getId();
        $data['corporation_name'] = $corporation->getName();
        $data['corporation_ticker'] = $corporation->getTicker();

        $variable->setValue((string) \json_encode($data));

        return $this->objectManager->flush();
    }

    /**
     * @param string $name
     * @return ResourceOwnerAccessTokenInterface|null Token including the resource_owner_id property.
     */
    public function refreshDirectorToken(string $name): ?ResourceOwnerAccessTokenInterface
    {
        $number = (int) explode('_', $name)[2];

        $systemVariableRepository = $this->repositoryFactory->getSystemVariableRepository();
        $characterVar = $systemVariableRepository->find(SystemVariable::DIRECTOR_CHAR . $number);
        $tokenVar = $systemVariableRepository->find(SystemVariable::DIRECTOR_TOKEN . $number);

        $characterData = \json_decode($characterVar ? $characterVar->getValue() : '', true);
        $tokenData = \json_decode($tokenVar ? $tokenVar->getValue() : '', true);
        if (! isset($characterData['character_id']) || ! isset($tokenData['access'])) {
            return null;
        }

        try {
            $token = $this->oauthToken->refreshAccessToken(new AccessToken([
                'access_token' => $tokenData['access'],
                'refresh_token' => $tokenData['refresh'],
                'expires' => (int) $tokenData['expires'],
            ]));
        } catch (IdentityProviderException $e) {
            return null;
        }

        return new AccessToken([
            'access_token' => $token->getToken(),
            'refresh_token' => $token->getRefreshToken(),
            'expires' => $token->getExpires(),
            'resource_owner_id' => $characterData['character_id'],
        ]);
    }

    public function verifyDirectorRole(int $characterId, string $accessToken): bool
    {
        $characterApi = $this->esiApiFactory->getCharacterApi($accessToken);
        try {
            $roles = $characterApi->getCharactersCharacterIdRoles($characterId, $this->datasource);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), ['exception' => $e]);
            return false;
        }

        if (! isset($roles['roles']) || ! in_array('Director', $roles['roles'])) {
            return false;
        }

        return true;
    }

    /**
     * @return GetCorporationsCorporationIdMembertracking200Ok[]|null Null if ESI request failed
     */
    public function fetchData(string $accessToken, int $corporationId): ?array
    {
        $corpApi = $this->esiApiFactory->getCorporationApi($accessToken);
        try {
            $memberTracking = $corpApi->getCorporationsCorporationIdMembertracking($corporationId, $this->datasource);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), ['exception' => $e]);
            return null;
        }

        return $memberTracking;
    }

    /**
     * @param Corporation $corporation An instance that is attached to the Doctrine entity manager.
     * @param GetCorporationsCorporationIdMembertracking200Ok[] $trackingData
     * @return bool
     */
    public function processData(Corporation $corporation, array $trackingData): bool
    {
        // get character names
        $charIds = [];
        foreach ($trackingData as $data) {
            $charIds[] = (int) $data->getCharacterId();
        }
        $names = null;
        if (count($charIds) > 0) {
            try {
                // it's possible that postUniverseNames() returns null
                $names = $this->esiApiFactory
                    ->getUniverseApi()
                    ->postUniverseNames($charIds, $this->datasource);
            } catch (\Exception $e) {
                $this->log->error($e->getMessage(), ['exception' => $e]);
            }
        }
        $charNames = [];
        if (is_array($names)) {
            foreach ($names as $name) {
                $charNames[(int) $name->getId()] = $name->getName();
            }
        }

        // store member data
        foreach ($trackingData as $data) {
            $id = (int) $data->getCharacterId();
            $corpMember = $this->repositoryFactory->getCorporationMemberRepository()->find($id);
            $character = $this->repositoryFactory->getCharacterRepository()->find($id);
            if ($corpMember === null) {
                $corpMember = new CorporationMember();
                $corpMember->setId($id);
            }
            $corpMember->setCharacter($character);
            $corpMember->setName($charNames[$id] ?? null);
            $corpMember->setLocationId((int) $data->getLocationId());
            if ($data->getLogoffDate() instanceof \DateTime) {
                $corpMember->setLogoffDate($data->getLogoffDate());
            }
            if ($data->getLogonDate() instanceof \DateTime) {
                $corpMember->setLogonDate($data->getLogonDate());
            }
            $corpMember->setShipTypeId((int) $data->getShipTypeId());
            if ($data->getStartDate() instanceof \DateTime) {
                $corpMember->setStartDate($data->getStartDate());
            }
            $corpMember->setCorporation($corporation);

            $this->objectManager->persist($corpMember);
        }

        return $this->objectManager->flush();
    }

    private function storeDirector(EveAuthentication $eveAuth, Corporation $corporation): bool
    {
        // determine next available number suffix
        $existingDirectors = $this->repositoryFactory->getSystemVariableRepository()->getDirectors();
        $maxNumber = 0;
        foreach ($existingDirectors as $existingDirector) {
            $number = (int) explode('_', $existingDirector->getName())[2];
            $maxNumber = max($maxNumber, $number);
        }
        $nextNumber = $maxNumber + 1;

        // store new director
        $newDirectorChar = new SystemVariable(SystemVariable::DIRECTOR_CHAR . $nextNumber);
        $newDirectorChar->setScope(SystemVariable::SCOPE_SETTINGS);
        $newDirectorChar->setValue((string) json_encode([
            'character_id' => (int) $eveAuth->getCharacterId(),
            'character_name' => $eveAuth->getCharacterName(),
            'corporation_id' => $corporation->getId(),
            'corporation_name' => $corporation->getName(),
            'corporation_ticker' => $corporation->getTicker(),
        ]));
        $newDirectorToken = new SystemVariable(SystemVariable::DIRECTOR_TOKEN . $nextNumber);
        $newDirectorToken->setScope(SystemVariable::SCOPE_BACKEND);
        $newDirectorToken->setValue((string) json_encode([
            'access' => $eveAuth->getToken()->getToken(),
            'refresh' => $eveAuth->getToken()->getRefreshToken(),
            'expires' => $eveAuth->getToken()->getExpires(),
        ]));

        $this->objectManager->persist($newDirectorChar);
        $this->objectManager->persist($newDirectorToken);
        return $this->objectManager->flush();
    }
}
