<?php declare(strict_types=1);

namespace Neucore\Controller\User;

use Brave\Sso\Basics\AuthenticationProvider;
use Neucore\Controller\BaseController;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\Config;
use Neucore\Entity\Role;
use Neucore\Service\EveMail;
use Neucore\Service\MemberTracking;
use Neucore\Service\ObjectManager;
use Neucore\Service\Random;
use Neucore\Service\UserAuth;
use Neucore\Slim\Session\SessionData;
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @OA\SecurityScheme(
 *     securityScheme="Session",
 *     type="apiKey",
 *     name="BCSESS",
 *     in="cookie"
 * )
 *
 * @OA\Tag(
 *     name="Auth",
 *     description="User authentication."
 * )
 * @OA\Schema(
 *     schema="LoginResult",
 *     required={"success", "message"},
 *     @OA\Property(
 *         property="success",
 *         type="boolean"
 *     ),
 *     @OA\Property(
 *         property="message",
 *         type="string"
 *     )
 * )
 */
class AuthController extends BaseController
{
    /**
     * A prefix for the OAuth state parameter that identifies a login of "managed" accounts.
     *
     * @var string
     */
    const STATE_PREFIX_STATUS_MANAGED = 's.';

    /**
     * A prefix for the OAuth state parameter that identifies an alt login.
     *
     * @var string
     */
    const STATE_PREFIX_ALT = 'a.';

    /**
     * A prefix for the OAuth state parameter that identifies an login
     * of the character that is used to send mails.
     *
     * @var string
     */
    const STATE_PREFIX_MAIL = 'm.';

    /**
     * A prefix for the OAuth state parameter that identifies an login
     * of the character with director roles for the member tracking functionality.
     *
     * @var string
     */
    const STATE_PREFIX_DIRECTOR = 'd.';

    /**
     * @var SessionData
     */
    private $session;

    /**
     * @var AuthenticationProvider
     */
    private $authProvider;

    /**
     * @var Config
     */
    private $config;

    public function __construct(
        ResponseInterface $response,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        SessionData $session,
        AuthenticationProvider $authProvider,
        Config $config
    ) {
        parent::__construct($response, $objectManager, $repositoryFactory);
        
        $this->session = $session;
        $this->authProvider = $authProvider;
        $this->config = $config;
    }

    /**
     * Main login, redirects to EVE SSO login.
     */
    public function login(): ResponseInterface
    {
        return $this->redirectToLoginUrl(Random::chars(12), '/#login');
    }

    /**
     * Login for "managed" accounts, redirects to EVE SSO login.
     * 
     * @noinspection PhpUnused
     */
    public function loginManaged(): ResponseInterface
    {
        // check "allow managed login" settings
        $allowLoginManaged = $this->repositoryFactory->getSystemVariableRepository()->findOneBy(
            ['name' => SystemVariable::ALLOW_LOGIN_MANAGED]
        );
        if (! $allowLoginManaged || $allowLoginManaged->getValue() !== '1') {
            $this->response->getBody()->write('Forbidden');
            return $this->response->withStatus(403, 'Forbidden');
        }

        return $this->redirectToLoginUrl(self::STATE_PREFIX_STATUS_MANAGED . Random::chars(12), '/#login');
    }

    /**
     * Alt login, redirects to EVE SSO login.
     *
     * @noinspection PhpUnused
     */
    public function loginAlt(): ResponseInterface
    {
        return $this->redirectToLoginUrl(self::STATE_PREFIX_ALT . Random::chars(12), '/#login-alt');
    }

    /**
     * Mail char login, redirects to EVE SSO login.
     *
     * @noinspection PhpUnused
     */
    public function loginMail(): ResponseInterface
    {
        return $this->redirectToLoginUrl(self::STATE_PREFIX_MAIL . Random::chars(12), '/#login-mail');
    }

    /**
     * Director char login, redirects to EVE SSO login.
     *
     * @noinspection PhpUnused
     */
    public function loginDirector(): ResponseInterface
    {
        return $this->redirectToLoginUrl(self::STATE_PREFIX_DIRECTOR . Random::chars(12), '/#login-director');
    }

    /**
     * EVE SSO callback URL.
     */
    public function callback(
        ServerRequestInterface $request,
        UserAuth $userAuth,
        EveMail $mailService,
        MemberTracking $memberTrackingService
    ): ResponseInterface {
        $redirectUrl = $this->session->get('auth_redirect', '/');
        $this->session->delete('auth_redirect');

        $state = (string) $this->session->get('auth_state');
        $this->session->delete('auth_state');

        $this->authProvider->setScopes($this->getLoginScopes($state));

        try {
            $eveAuth = $this->authProvider->validateAuthenticationV2(
                $this->getQueryParam($request, 'state'),
                $state,
                $this->getQueryParam($request, 'code', '')
            );
        } catch (\UnexpectedValueException $uve) {
            $this->session->set('auth_result', [
                'success' => false,
                'message' => $uve->getMessage(),
            ]);
            return $this->response->withHeader('Location', (string)$redirectUrl)->withStatus(302);
        }

        // handle login
        switch (substr($state, 0, 2)) {
            case self::STATE_PREFIX_ALT:
                $success = $userAuth->addAlt($eveAuth);
                $successMessage = 'Character added to player account.';
                $errorMessage = 'Failed to add alt to account.';
                break;
            case self::STATE_PREFIX_MAIL:
                if (in_array(Role::SETTINGS, $userAuth->getRoles())) {
                    $success = $mailService->storeMailCharacter($eveAuth);
                } else {
                    $success = false;
                }
                $successMessage = 'Mail character authenticated.';
                $errorMessage = 'Failed to store character.';
                break;
            case self::STATE_PREFIX_DIRECTOR:
                $successMessage = 'Character with director roles added.';
                $errorMessage = 'Error adding character with director roles.';
                $success = $memberTrackingService->verifyAndStoreDirector($eveAuth);
                break;
            case self::STATE_PREFIX_STATUS_MANAGED:
            default:
                $success = $userAuth->authenticate($eveAuth);
                $successMessage = 'Login successful.';
                $errorMessage = 'Failed to authenticate user.';
        }

        $this->session->set('auth_result', [
            'success' => $success,
            'message' => $success ? $successMessage : $errorMessage
        ]);

        return $this->response->withHeader('Location', (string)$redirectUrl)->withStatus(302);
    }

    /**
     * @OA\Get(
     *     path="/user/auth/result",
     *     operationId="result",
     *     summary="Result of last SSO attempt.",
     *     tags={"Auth"},
     *     @OA\Response(
     *         response="200",
     *         description="The result.",
     *         @OA\JsonContent(ref="#/components/schemas/LoginResult")
     *     )
     * )
     */
    public function result(): ResponseInterface
    {
        $result = $this->session->get('auth_result');

        $default = [
            'success' => false,
            'message' => 'No login attempt recorded.',
        ];

        return $this->withJson($result ?: $default);
    }

    /**
     * @OA\Post(
     *     path="/user/auth/logout",
     *     operationId="logout",
     *     summary="User logout.",
     *     description="Needs role: user",
     *     tags={"Auth"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="204",
     *         description="User was logged out."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function logout(): ResponseInterface
    {
        $this->session->clear();

        return $this->response->withStatus(204);
    }

    private function redirectToLoginUrl(string $state, string $redirect): ResponseInterface
    {
        $this->session->set('auth_state', $state);
        $this->session->set('auth_redirect', $redirect);

        $this->authProvider->setScopes($this->getLoginScopes($state));

        return $this->response->withHeader('Location', $this->authProvider->buildLoginUrl($state))->withStatus(302);
    }

    private function getLoginScopes($state): array
    {
        if (substr($state, 0, 2) === self::STATE_PREFIX_STATUS_MANAGED) {
            return [];
        } elseif (substr($state, 0, 2) === self::STATE_PREFIX_MAIL) {
            return ['esi-mail.send_mail.v1'];
        } elseif (substr($state, 0, 2) === self::STATE_PREFIX_DIRECTOR) {
            return [
                'esi-characters.read_corporation_roles.v1',
                'esi-corporations.track_members.v1',
            ];
        }

        $scopes = $this->config['eve']['scopes'];
        if (trim($scopes) !== '') {
            $scopes = explode(' ', $scopes);
        } else {
            $scopes = [];
        }

        return $scopes;
    }
}
