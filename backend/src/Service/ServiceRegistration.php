<?php

declare(strict_types=1);

namespace Neucore\Service;

use Neucore\Application;
use Neucore\Entity\Service;
use Neucore\Plugin\AccountData;
use Neucore\Plugin\ServiceInterface;
use Psr\Log\LoggerInterface;

class ServiceRegistration
{
    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var UserAuth
     */
    private $userAuth;

    public function __construct(LoggerInterface $log, UserAuth $userAuth)
    {
        $this->log = $log;
        $this->userAuth = $userAuth;
    }

    public function hasRequiredGroups(Service $service): bool
    {
        $character = $this->userAuth->getUser();
        if ($character === null) {
            return false;
        }

        $serviceConfig = json_decode((string)$service->getConfiguration(), true);
        $groups = array_map('intval', explode(',', (string)($serviceConfig['groups'] ?? '')));

        foreach ($groups as $group) {
            if ($group > 0 && !$character->getPlayer()->hasGroup($group)) {
                return false;
            }
        }
        return true;
    }

    public function getServiceObject(Service $service): ?ServiceInterface
    {
        $serviceConfig = json_decode((string)$service->getConfiguration(), true);

        // configure autoloader
        $psr4Prefix = $serviceConfig['psr4Prefix'] ?? '';
        $psr4Paths = (array) ($serviceConfig['psr4Path'] ?? []);
        if ($psr4Prefix !== '' && $psr4Paths !== []) {
            if (substr($psr4Prefix, -1) !== '\\') {
                $psr4Prefix .= '\\';
            }
            /** @noinspection PhpFullyQualifiedNameUsageInspection */
            /* @var \Composer\Autoload\ClassLoader $loader */
            /** @noinspection PhpIncludeInspection */
            $loader = require Application::ROOT_DIR . '/vendor/autoload.php';
            foreach ($loader->getPrefixesPsr4() as $existingPrefix => $paths) {
                if ($existingPrefix === $psr4Prefix) {
                    $psr4Paths = array_merge($paths, $psr4Paths);
                }
            }
            $loader->setPsr4($psr4Prefix, $psr4Paths);
        }

        $serviceClass = $serviceConfig['phpClass'] ?? '';
        if (!class_exists($serviceClass)) {
            return null;
        }

        $implements = class_implements($serviceClass);
        if (!is_array($implements) || !in_array(ServiceInterface::class, $implements)) {
            return null;
        }

        return new $serviceClass;
    }

    /**
     * @return AccountData[]
     */
    public function getAccounts(ServiceInterface $service, array $characterIds): array
    {
        $accountData = [];
        foreach ($service->getAccounts(...$characterIds) as $account) {
            if (!$account instanceof AccountData) {
                $this->log->error(
                    "ServiceController: ServiceInterface::getAccounts must return an array of AccountData objects."
                );
                continue;
            }
            if (! in_array($account->getCharacterId(), $characterIds)) {
                $this->log->error("ServiceController: Character ID does not match.");
                continue;
            }
            $accountData[] = $account;
        }
        return $accountData;
    }
}
