<?php declare(strict_types=1);

namespace Tests\Unit\Core\Middleware;

use Brave\Core\Entity\SystemVariable;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Middleware\GuzzleEsiHeaders;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Tests\Helper;
use Tests\Logger;

class GuzzleEsiHeadersTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var RepositoryFactory
     */
    private $repositoryFactory;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var GuzzleEsiHeaders
     */
    private $obj;

    public function setUp()
    {
        $em = (new Helper())->getEm();

        $this->repositoryFactory = new RepositoryFactory($em);
        $this->logger = new Logger('test');
        $this->obj = new GuzzleEsiHeaders($this->logger, $em);
    }

    public function testInvokeErrorLimit()
    {
        $response = new Response(
            200,
            ['X-Esi-Error-Limit-Remain' => [100], 'X-Esi-Error-Limit-Reset' => [60]]
        );

        $function = $this->obj->__invoke($this->getHandler($response));
        $function(new Request('', ''), []);

        $var = $this->repositoryFactory->getSystemVariableRepository()->find(SystemVariable::ESI_ERROR_LIMIT);
        $val = \json_decode($var->getValue());

        $this->assertSame(100, $val->remain);
        $this->assertSame(60, $val->reset);
        $this->assertGreaterThanOrEqual(time(), $val->updated);
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
        return function() use ($response) {
            return new class($response) {
                private $response;
                public function __construct($response) {
                    $this->response = $response;
                }
                public function then(callable $onFulfilled) {
                    $onFulfilled($this->response);
                }
            };
        };
    }
}
