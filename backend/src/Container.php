<?php

declare(strict_types=1);

namespace Neucore;

use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Cache\FilesystemCache;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Setup;
use Doctrine\Persistence\ObjectManager;
use Eve\Sso\AuthenticationProvider;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\DoctrineCacheStorage;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use League\OAuth2\Client\Provider\GenericProvider;
use Monolog\Formatter\HtmlFormatter;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\LogglyFormatter;
use Monolog\Formatter\LogstashFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Neucore\Exception\RuntimeException;
use Neucore\Factory\RepositoryFactory;
use Neucore\Log\FluentdFormatter;
use Neucore\Log\GelfMessageFormatter;
use Neucore\Middleware\Guzzle\EsiHeaders;
use Neucore\Service\Config;
use Neucore\Storage\ApcuStorage;
use Neucore\Storage\StorageInterface;
use Neucore\Storage\SystemVariableStorage;
use Psr\Container\ContainerInterface;
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
            EntityManagerInterface::class => function (ContainerInterface $c) {
                $conf = $c->get(Config::class)['doctrine'];
                $config = Setup::createAnnotationMetadataConfiguration(
                    $conf['meta']['entity_paths'],
                    $conf['meta']['dev_mode'],
                    $conf['meta']['proxy_dir'],
                    null,
                    false
                );
                /** @noinspection PhpDeprecationInspection */
                /* @phan-suppress-next-line PhanDeprecatedFunction */
                AnnotationRegistry::registerLoader('class_exists');
                $options = $conf['driver_options'];
                $caFile = (string) $options['mysql_ssl_ca'];
                $verify = (bool) $options['mysql_verify_server_cert'];
                if ($caFile !== '' && (! $verify || is_file($caFile))) {
                    $conf['connection']['driverOptions'] = [
                        \PDO::MYSQL_ATTR_SSL_CA => $caFile,
                        \PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => $verify,
                    ];
                }
                $em = EntityManager::create($conf['connection'], $config);
                /*$logger = new class() extends \Doctrine\DBAL\Logging\DebugStack {
                    public function startQuery($sql, ?array $params = null, ?array $types = null)
                    {
                        error_log($sql);
                        #error_log(print_r($params, true));
                    }
                };
                $em->getConnection()->getConfiguration()->setSQLLogger($logger);*/
                return $em;
            },
            ObjectManager::class => function (ContainerInterface $c) {
                return $c->get(EntityManagerInterface::class);
            },

            // EVE OAuth
            GenericProvider::class => function (ContainerInterface $c) {
                $conf = $c->get(Config::class)['eve'];
                $urls = $conf['datasource'] === 'singularity' ? $conf['oauth_urls_sisi'] : $conf['oauth_urls_tq'];
                return new GenericProvider([
                    'clientId'                => $conf['client_id'],
                    'clientSecret'            => $conf['secret_key'],
                    'redirectUri'             => $conf['callback_url'],
                    'urlAuthorize'            => $urls['authorize'],
                    'urlAccessToken'          => $urls['token'],
                    'urlResourceOwnerDetails' => $urls['verify'],
                ], [
                    'httpClient' => $c->get(ClientInterface::class)
                ]);
            },
            AuthenticationProvider::class => function (ContainerInterface $c) {
                $conf = $c->get(Config::class)['eve'];
                $urls = $conf['datasource'] === 'singularity' ? $conf['oauth_urls_sisi'] : $conf['oauth_urls_tq'];
                return new AuthenticationProvider($c->get(GenericProvider::class), [], $urls['jwks']);
            },

            // Monolog
            LoggerInterface::class => function (ContainerInterface $c) {
                $config = $c->get(Config::class)['monolog'];
                $path = $config['path'];
                $rotation = $config['rotation'];
                if (strpos($path, 'php://') === false) {
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
                    $formatter->includeStacktraces(true);
                } elseif ($format === 'loggly') {
                    $formatter = new LogglyFormatter(JsonFormatter::BATCH_MODE_JSON, true);
                    $formatter->includeStacktraces(true);
                } elseif ($format === 'logstash') {
                    $formatter = new LogstashFormatter('Neucore');
                } else { // multiline or line
                    $formatter = new LineFormatter();
                    $formatter->ignoreEmptyContextAndExtra(true);
                    if ($format === 'multiline') {
                        $formatter->includeStacktraces(true);
                    }
                }
                $handler = (new StreamHandler($path, Logger::DEBUG))->setFormatter($formatter);
                return (new Logger('app'))->pushHandler($handler);
            },

            // Guzzle
            ClientInterface::class => function (ContainerInterface $c) {
                /*$debugFunc = function (\Psr\Http\Message\MessageInterface $r) use ($c) {
                    if ($r instanceof \Psr\Http\Message\RequestInterface) {
                        $c->get(LoggerInterface::class)->debug($r->getMethod() . ' ' . $r->getUri());
                    } elseif ($r instanceof \Psr\Http\Message\ResponseInterface) {
                        $c->get(LoggerInterface::class)->debug('Status Code: ' . $r->getStatusCode());
                    }
                    $headers = [];
                    foreach ($r->getHeaders() as $name => $val) {
                        $headers[$name] = $val[0];
                    }
                    #$c->get(LoggerInterface::class)->debug(print_r($headers, true));
                    return $r;
                };*/

                $stack = HandlerStack::create();
                #$stack->push(\GuzzleHttp\Middleware::mapRequest($debugFunc));
                $cache = new CacheMiddleware(new PrivateCacheStrategy(new DoctrineCacheStorage(
                    new FilesystemCache($c->get(Config::class)['guzzle']['cache']['dir'])
                )));
                $stack->push($cache, 'cache');
                $stack->push($c->get(EsiHeaders::class));
                #$stack->push(\GuzzleHttp\Middleware::mapResponse($debugFunc));

                return new Client([
                    'handler' => $stack,
                    'headers' => [
                        'User-Agent' => $c->get(Config::class)['guzzle']['user_agent'],
                    ],
                ]);
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
            }
        ];
    }
}
