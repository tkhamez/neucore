<?php declare(strict_types=1);

namespace Tests\Unit\Middleware\Guzzle;

use Neucore\Entity\SystemVariable;
use Neucore\Factory\RepositoryFactory;
use Neucore\Middleware\Guzzle\EsiHeaders;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Tests\Logger;

class EsiHeadersTest extends TestCase
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var RepositoryFactory
     */
    private $repositoryFactory;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var \Neucore\Middleware\Guzzle\EsiHeaders
     */
    private $obj;

    public function setUp()
    {
        $helper = new Helper();
        $helper->emptyDb();
        $this->em = $helper->getEm();

        $this->repositoryFactory = new RepositoryFactory($this->em);
        $this->logger = new Logger('test');
        $this->obj = new \Neucore\Middleware\Guzzle\EsiHeaders($this->logger, $this->repositoryFactory, $this->em);
    }

    public function testInvokeErrorLimit()
    {
        $var = (new SystemVariable(SystemVariable::ESI_ERROR_LIMIT))->setScope(SystemVariable::SCOPE_BACKEND);
        $this->em->persist($var);
        $this->em->flush();

        $response = new Response(
            200,
            ['X-Esi-Error-Limit-Remain' => [100], 'X-Esi-Error-Limit-Reset' => [60]]
        );

        $function = $this->obj->__invoke($this->getHandler($response));
        $function(new Request('GET', 'https://local.host/esi/path'), []);

        $var = $this->repositoryFactory->getSystemVariableRepository()->find(SystemVariable::ESI_ERROR_LIMIT);
        $val = \json_decode($var->getValue());

        $this->assertSame(100, $val->remain);
        $this->assertSame(60, $val->reset);
        $this->assertLessThanOrEqual(time(), $val->updated);
    }

    public function testInvokeDeprecated()
    {
        $response = new Response(
            200,
            ['warning' => ['299 - This route is deprecated']]
        );

        $function = $this->obj->__invoke($this->getHandler($response));
        $function(new Request('GET', 'https://local.host/esi/path'), []);

        $this->assertSame(
            'https://local.host/esi/path: 299 - This route is deprecated',
            $this->logger->getHandler()->getRecords()[0]['message']
        );
    }

    private function getHandler($response)
    {
        return function () use ($response) {
            return new class($response) {
                private $response;
                public function __construct($response)
                {
                    $this->response = $response;
                }
                public function then(callable $onFulfilled)
                {
                    $onFulfilled($this->response);
                }
            };
        };
    }
}
