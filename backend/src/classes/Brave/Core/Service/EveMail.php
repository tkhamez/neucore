<?php declare(strict_types=1);

namespace Brave\Core\Service;

use Brave\Core\Entity\SystemVariable;
use Brave\Core\Factory\EsiApiFactory;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Sso\Basics\EveAuthentication;
use League\OAuth2\Client\Token\AccessToken;

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

    /**
     * @var OAuthToken
     */
    private $oauthToken;

    /**
     * @var EsiApiFactory
     */
    private $esiApi;

    public function __construct(
        RepositoryFactory $repositoryFactory,
        ObjectManager $objectManager,
        OAuthToken $oauthToken,
        EsiApi $esiApi
    ) {
        $this->repositoryFactory = $repositoryFactory;
        $this->objectManager = $objectManager;
        $this->oauthToken = $oauthToken;
        $this->esiApi = $esiApi;
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
            'refresh' => $eveAuth->getToken()->getRefreshToken(),
            'expires' => $eveAuth->getToken()->getExpires(),
        ]));

        return $this->objectManager->flush();
    }

    /**
     * @param int $recipient EVE character ID
     * @return string Error message or empty string on success
     */
    public function sendAccountDeactivatedMail(int $recipient): string
    {
        $repo = $this->repositoryFactory->getSystemVariableRepository();

        $active = $repo->find(SystemVariable::MAIL_ACCOUNT_DISABLED_ACTIVE);
        if ($active === null || $active->getValue() !== '1') {
            return 'This mail is deactivated.';
        }

        $token = $repo->find(SystemVariable::MAIL_TOKEN);
        $subject = $repo->find(SystemVariable::MAIL_ACCOUNT_DISABLED_SUBJECT);
        $body = $repo->find(SystemVariable::MAIL_ACCOUNT_DISABLED_BODY);

        if ($token === null || $token->getValue() === '' ||
            $subject === null || trim($subject->getValue()) === '' ||
            $body === null || trim($body->getValue()) === ''
        ) {
            return 'Missing data.';
        }

        $tokenValues = json_decode($token->getValue(), true);
        if (! is_array($tokenValues) ||
            ! isset($tokenValues['id']) ||
            ! isset($tokenValues['access']) ||
            ! isset($tokenValues['refresh']) ||
            ! isset($tokenValues['expires'])
        ) {
            return 'Missing token data.';
        }

        $accessToken = $this->oauthToken->refreshAccessToken(new AccessToken([
            'access_token' => $tokenValues['access'],
            'refresh_token' => $tokenValues['refresh'],
            'expires' => (int) $tokenValues['expires']
        ]));

        $result = $this->esiApi->sendMail(
            $tokenValues['id'],
            $accessToken->getToken(),
            $subject->getValue(),
            $body->getValue(),
            [$recipient]
        );

        if ($result > 0) {
            return '';
        } else {
            return $this->esiApi->getLastErrorMessage();
        }
    }
}
