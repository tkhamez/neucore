<?php

declare(strict_types=1);

namespace Tests\Functional\Controller\User;

use Neucore\Entity\App;
use Neucore\Entity\AppRequests;
use Neucore\Entity\Player;
use Neucore\Entity\PlayerLogins;
use Neucore\Entity\Role;
use Tests\Functional\WebTestCase;
use Tests\Helper;

class StatisticsControllerTest extends WebTestCase
{
    private static App $app;

    public static function setUpBeforeClass(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
        $player = new Player();
        $login = (new PlayerLogins())->setPlayer($player)->setCount(4)->setYear(2021)->setMonth(1);
        self::$app = (new App())->setName('a1')->setSecret('s');
        $req = (new AppRequests())->setApp(self::$app)->setCount(43)
            ->setYear(2021)->setMonth(1)->setDayOfMonth(23)->setHour(13);
        $helper->getEm()->persist($player);
        $helper->getEm()->persist($login);
        $helper->getEm()->persist(self::$app);
        $helper->getEm()->persist($req);
        $helper->getEm()->flush();

        $helper->addCharacterMain('User', 1, [Role::USER]);
        $helper->addCharacterMain('Admin', 2, [Role::USER, Role::STATISTICS]);
    }

    protected function tearDown(): void
    {
        $_SESSION = null;
    }

    public function testPlayerLogins403()
    {
        $response1 = $this->runApp('GET', '/api/user/statistics/player-logins');

        $this->loginUser(1);
        $response2 = $this->runApp('GET', '/api/user/statistics/player-logins');

        $this->assertEquals(403, $response1->getStatusCode());
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testPlayerLogins200()
    {
        $this->loginUser(2);

        $response0 = $this->runApp('GET', '/api/user/statistics/player-logins?until=invalid');
        $this->assertEquals(200, $response0->getStatusCode()); // test that this will work (until = current date)

        $response = $this->runApp('GET', '/api/user/statistics/player-logins?until=2021-03&periods=12');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            [['unique_logins' => 1, 'total_logins' => 4, 'year' => 2021, 'month' => 1]],
            $this->parseJsonBody($response)
        );
    }

    public function testTotalMonthlyAppRequests403()
    {
        $response1 = $this->runApp('GET', '/api/user/statistics/total-monthly-app-requests');

        $this->loginUser(1);
        $response2 = $this->runApp('GET', '/api/user/statistics/total-monthly-app-requests');

        $this->assertEquals(403, $response1->getStatusCode());
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testTotalMonthlyAppRequests200()
    {
        $this->loginUser(2);

        $response0 = $this->runApp('GET', '/api/user/statistics/total-monthly-app-requests?until=invalid');
        $this->assertEquals(200, $response0->getStatusCode()); // test that this will work (until = current date)

        $response1 = $this->runApp('GET', '/api/user/statistics/total-monthly-app-requests?until=2021-03&periods=12');
        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertSame(
            [['requests' => 43, 'year' => 2021, 'month' => 1]],
            $this->parseJsonBody($response1)
        );
    }

    public function testMonthlyAppRequests403()
    {
        $response1 = $this->runApp('GET', '/api/user/statistics/monthly-app-requests');

        $this->loginUser(1);
        $response2 = $this->runApp('GET', '/api/user/statistics/monthly-app-requests');

        $this->assertEquals(403, $response1->getStatusCode());
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testMonthlyAppRequests200()
    {
        $this->loginUser(2);

        $response0 = $this->runApp('GET', '/api/user/statistics/monthly-app-requests?until=invalid');
        $this->assertEquals(200, $response0->getStatusCode()); // test that this will work (until = current date)

        $response = $this->runApp('GET', '/api/user/statistics/monthly-app-requests?until=2021-03&periods=12');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            [['app_id' => self::$app->getId(), 'app_name' => 'a1', 'requests' => 43, 'year' => 2021, 'month' => 1]],
            $this->parseJsonBody($response)
        );
    }

    public function testTotalDailyAppRequests403()
    {
        $response1 = $this->runApp('GET', '/api/user/statistics/total-daily-app-requests');

        $this->loginUser(1);
        $response2 = $this->runApp('GET', '/api/user/statistics/total-daily-app-requests');

        $this->assertEquals(403, $response1->getStatusCode());
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testTotalDailyAppRequests200()
    {
        $this->loginUser(2);

        $response0 = $this->runApp('GET', '/api/user/statistics/total-daily-app-requests?until=invalid');
        $this->assertEquals(200, $response0->getStatusCode()); // test that this will work (until = current date)

        $response = $this->runApp('GET', '/api/user/statistics/total-daily-app-requests?until=2021-01-30&periods=4');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            [['requests' => 43, 'year' => 2021, 'month' => 1, 'day_of_month' => 23]],
            $this->parseJsonBody($response)
        );
    }

    public function testHourlyAppRequests403()
    {
        $response1 = $this->runApp('GET', '/api/user/statistics/hourly-app-requests');

        $this->loginUser(1);
        $response2 = $this->runApp('GET', '/api/user/statistics/hourly-app-requests');

        $this->assertEquals(403, $response1->getStatusCode());
        $this->assertEquals(403, $response2->getStatusCode());
    }

    public function testHourlyAppRequests200()
    {
        $this->loginUser(2);

        $response0 = $this->runApp('GET', '/api/user/statistics/hourly-app-requests?until=invalid');
        $this->assertEquals(200, $response0->getStatusCode()); // test that this will work (until = current date)

        $response = $this->runApp('GET', '/api/user/statistics/hourly-app-requests?until=2021-01-25%2001&periods=7');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame(
            [[
                'app_id' => self::$app->getId(),
                'app_name' => 'a1',
                'requests' => 43,
                'year' => 2021,
                'month' => 1,
                'day_of_month' => 23,
                'hour' => 13,
            ]],
            $this->parseJsonBody($response)
        );
    }
}
