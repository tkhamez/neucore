<?php

declare(strict_types=1);

namespace Neucore;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\Persistence\ObjectManager;
use Eve\Sso\AuthenticationProvider;
use Monolog\Formatter\HtmlFormatter;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\LogglyFormatter;
use Monolog\Formatter\LogstashFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Neucore\Exception\RuntimeException;
use Neucore\Factory\HttpClientFactory;
use Neucore\Factory\HttpClientFactoryInterface;
use Neucore\Factory\RepositoryFactory;
use Neucore\Log\FluentdFormatter;
use Neucore\Log\GelfMessageFormatter;
use Neucore\Service\Config;
use Neucore\Storage\ApcuStorage;
use Neucore\Storage\StorageInterface;
use Neucore\Storage\SystemVariableStorage;
use Psr\Container\ContainerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;

class Container
{
    public static function getDefinitions(): array
    {
        return [
            // Doctrine
            EntityManagerInterface::class => function (
                ?ContainerInterface $c = null, // this is also used in unit tests where the container does not exist
                ?Config $config = null
            ) {
                if ($c) {
                    $conf = $c->get(Config::class)['doctrine'];
                } else {
                    $conf = $config['doctrine'] ?? []; // it should always be set
                }
                $options = $conf['driver_options'];
                $caFile = (string)$options['mysql_ssl_ca'];
                $verify = (bool)$options['mysql_verify_server_cert'];
                if ($caFile !== '' && (!$verify || is_file($caFile))) {
                    $conf['connection']['driverOptions'] = [
                        \PDO::MYSQL_ATTR_SSL_CA => $caFile,
                        \PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => $verify,
                    ];
                }
                $metaConfig = ORMSetup::createAttributeMetadataConfiguration(
                    $conf['meta']['entity_paths'],
                    $conf['meta']['dev_mode'],
                    $conf['meta']['proxy_dir']
                );
                #$metaConfig->setMiddlewares([new Middleware($c->get(LoggerInterface::class))]);
                $connection = DriverManager::getConnection($conf['connection'], $metaConfig);
                return new EntityManager($connection, $metaConfig);
            },
            ObjectManager::class => function (ContainerInterface $c) {
                return $c->get(EntityManagerInterface::class);
            },

            // EVE OAuth
            AuthenticationProvider::class => function (ContainerInterface $c) {
                $conf = $c->get(Config::class)['eve'];
                $provider = new AuthenticationProvider(
                    [
                        'clientId'     => $conf['client_id'],
                        'clientSecret' => $conf['secret_key'],
                        'redirectUri'  => $conf['callback_url'],

                        // These are only set for tests
                        'urlMetadata'    => $conf['oauth_urls']['metadata'] ?? null,
                        'urlAuthorize'   => $conf['oauth_urls']['authorize'] ?? null,
                        'urlAccessToken' => $conf['oauth_urls']['token'] ?? null,
                        'urlKeySet'      => $conf['oauth_urls']['jwks'] ?? null,
                        'urlRevoke'      => $conf['oauth_urls']['revoke'] ?? null,
                        'issuer'         => $conf['oauth_urls']['issuer'] ?? null,
                    ],
                    httpClient: $c->get(\GuzzleHttp\ClientInterface::class),
                    logger: $c->get(LoggerInterface::class),
                );
                if (!$conf['oauth_verify_signature']) {
                    $provider->setSignatureVerification(false);
                }
                return $provider;
            },

            // Monolog
            LoggerInterface::class => function (ContainerInterface $c) {
                $config = $c->get(Config::class)['monolog'];
                $path = $config['path'];
                $rotation = $config['rotation'];
                if (!str_contains($path, 'php://')) {
                    if (! is_writable($path)) {
                        throw new RuntimeException("The log directory '$path' must be writable by the web server.");
                    }
                    $date = date('o\wW'); // weekly rotation
                    if ($rotation === 'daily') {
                        $date = date('Ymd');
                    } elseif ($rotation === 'monthly') {
                        $date = date('Ym');
                    }
                    $path .= '/app-' . (PHP_SAPI === 'cli' ? 'cli-' : '') . $date . '.log';
                }
                $format = $config['format'];
                if ($format === 'fluentd') {
                    $formatter = new FluentdFormatter();
                } elseif ($format === 'gelf') {
                    $formatter = new GelfMessageFormatter();
                } elseif ($format === 'html') {
                    $formatter = new HtmlFormatter();
                } elseif ($format === 'json') {
                    $formatter = new JsonFormatter();
                    $formatter->includeStacktraces();
                } elseif ($format === 'loggly') {
                    $formatter = new LogglyFormatter(JsonFormatter::BATCH_MODE_JSON, true);
                    $formatter->includeStacktraces();
                } elseif ($format === 'logstash') {
                    $formatter = new LogstashFormatter('Neucore');
                } else { // multiline or line
                    $formatter = new LineFormatter();
                    $formatter->ignoreEmptyContextAndExtra();
                    if ($format === 'multiline') {
                        $formatter->includeStacktraces();
                    }
                }
                $handler = (new StreamHandler($path, Level::Debug))->setFormatter($formatter);
                return (new Logger('app'))->pushHandler($handler);
            },

            // Guzzle
            HttpClientFactoryInterface::class => function (ContainerInterface $c) {
                return $c->get(HttpClientFactory::class);
            },
            ClientInterface::class => function (ContainerInterface $c) {
                $factory = $c->get(HttpClientFactoryInterface::class); /* @var HttpClientFactoryInterface $factory */
                return $factory->get();
            },
            \GuzzleHttp\ClientInterface::class => function (ContainerInterface $c) {
                $factory = $c->get(HttpClientFactoryInterface::class); /* @var HttpClientFactoryInterface $factory */
                return $factory->getGuzzleClient();
            },

            // Response
            ResponseInterface::class => function (ContainerInterface $c) {
                return $c->get(ResponseFactoryInterface::class)->createResponse();
            },
            ResponseFactoryInterface::class => function () {
                return new ResponseFactory();
            },

            // Storage
            StorageInterface::class => function (ContainerInterface $c) {
                if (
                    function_exists('apcu_store') &&
                    (
                        (php_sapi_name() === 'cli' && ini_get('apc.enable_cli') === '1') ||
                        (php_sapi_name() !== 'cli' && ini_get('apc.enabled') === '1')
                    )
                ) {
                    $storage = new ApcuStorage();
                } else {
                    $storage = new SystemVariableStorage(
                        $c->get(RepositoryFactory::class),
                        $c->get(Service\ObjectManager::class)
                    );
                }
                return $storage;
            },
        ];
    }
}
