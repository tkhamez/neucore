<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use Neucore\Entity\App;
use Neucore\Entity\AppRequests;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\AppRequestsRepository;
use PHPUnit\Framework\TestCase;
use Tests\Helper;

class AppRequestsRepositoryTest extends TestCase
{
    private static AppRequestsRepository $repository;

    private static App $app1;

    private static App $app2;

    public static function setUpBeforeClass(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
        $om = $helper->getObjectManager();
        self::$app1 = (new App())->setName('a1')->setSecret('s');
        self::$app2 = (new App())->setName('a2')->setSecret('s');
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(45)
            ->setYear(2019)->setMonth(12)->setDayOfMonth(15)->setHour(0));
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(1)
            ->setYear(2020)->setMonth(1)->setDayOfMonth(15)->setHour(1));
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(1)
            ->setYear(2020)->setMonth(2)->setDayOfMonth(15)->setHour(2));
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(1)
            ->setYear(2020)->setMonth(3)->setDayOfMonth(15)->setHour(3));
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(1)
            ->setYear(2020)->setMonth(4)->setDayOfMonth(15)->setHour(4));
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(1)
            ->setYear(2020)->setMonth(5)->setDayOfMonth(15)->setHour(5));
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(1)
            ->setYear(2020)->setMonth(6)->setDayOfMonth(15)->setHour(6));
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(1)
            ->setYear(2020)->setMonth(7)->setDayOfMonth(15)->setHour(7));
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(1)
            ->setYear(2020)->setMonth(8)->setDayOfMonth(15)->setHour(8));
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(1)
            ->setYear(2020)->setMonth(9)->setDayOfMonth(15)->setHour(9));
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(1)
            ->setYear(2020)->setMonth(10)->setDayOfMonth(15)->setHour(10));
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(1)
            ->setYear(2020)->setMonth(11)->setDayOfMonth(15)->setHour(11));
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(50)
            ->setYear(2020)->setMonth(12)->setDayOfMonth(30)->setHour(12));
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(55)
            ->setYear(2020)->setMonth(12)->setDayOfMonth(31)->setHour(13));
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(45)
            ->setYear(2021)->setMonth(1)->setDayOfMonth(1)->setHour(14));
        $om->persist((new AppRequests())->setApp(self::$app2)->setCount(20)
            ->setYear(2021)->setMonth(1)->setDayOfMonth(1)->setHour(14));
        $om->persist((new AppRequests())->setApp(self::$app2)->setCount(10)
            ->setYear(2021)->setMonth(1)->setDayOfMonth(1)->setHour(15));
        $om->persist((new AppRequests())->setApp(self::$app2)->setCount(35)
            ->setYear(2021)->setMonth(1)->setDayOfMonth(2)->setHour(16));
        $om->persist((new AppRequests())->setApp(self::$app2)->setCount(65)
            ->setYear(2021)->setMonth(2)->setDayOfMonth(2)->setHour(17));
        $om->persist(self::$app1);
        $om->persist(self::$app2);
        $om->flush();

        self::$repository = (new RepositoryFactory($om))->getAppRequestsRepository();
    }

    public function testMonthlySummary()
    {
        $this->assertSame([
            ['requests' => 110, 'year' => 2021, 'month' => 1],
            ['requests' => 105, 'year' => 2020, 'month' => 12],
            ['requests' => 1, 'year' => 2020, 'month' => 11],
            ['requests' => 1, 'year' => 2020, 'month' => 10],
            ['requests' => 1, 'year' => 2020, 'month' => 9],
            ['requests' => 1, 'year' => 2020, 'month' => 8],
            ['requests' => 1, 'year' => 2020, 'month' => 7],
            ['requests' => 1, 'year' => 2020, 'month' => 6],
            ['requests' => 1, 'year' => 2020, 'month' => 5],
            ['requests' => 1, 'year' => 2020, 'month' => 4],
            ['requests' => 1, 'year' => 2020, 'month' => 3],
            ['requests' => 1, 'year' => 2020, 'month' => 2],
            ['requests' => 1, 'year' => 2020, 'month' => 1],
            //['requests' => 45, 'year' => 2019, 'month' => 12],
        ], self::$repository->monthlySummary(strtotime('2021-01-15'), 13));
    }

    public function testMonthlySummaryByApp()
    {
        $this->assertSame([
            ['app_id' => self::$app1->getId(), 'app_name' => 'a1', 'requests' => 45, 'year' => 2021, 'month' => 1],
            ['app_id' => self::$app2->getId(), 'app_name' => 'a2', 'requests' => 65, 'year' => 2021, 'month' => 1],
            ['app_id' => self::$app1->getId(), 'app_name' => 'a1', 'requests' => 105, 'year' => 2020, 'month' => 12],
            ['app_id' => self::$app1->getId(), 'app_name' => 'a1', 'requests' => 1, 'year' => 2020, 'month' => 11],
            ['app_id' => self::$app1->getId(), 'app_name' => 'a1', 'requests' => 1, 'year' => 2020, 'month' => 10],
            ['app_id' => self::$app1->getId(), 'app_name' => 'a1', 'requests' => 1, 'year' => 2020, 'month' => 9],
            ['app_id' => self::$app1->getId(), 'app_name' => 'a1', 'requests' => 1, 'year' => 2020, 'month' => 8],
            ['app_id' => self::$app1->getId(), 'app_name' => 'a1', 'requests' => 1, 'year' => 2020, 'month' => 7],
            ['app_id' => self::$app1->getId(), 'app_name' => 'a1', 'requests' => 1, 'year' => 2020, 'month' => 6],
            ['app_id' => self::$app1->getId(), 'app_name' => 'a1', 'requests' => 1, 'year' => 2020, 'month' => 5],
            ['app_id' => self::$app1->getId(), 'app_name' => 'a1', 'requests' => 1, 'year' => 2020, 'month' => 4],
            ['app_id' => self::$app1->getId(), 'app_name' => 'a1', 'requests' => 1, 'year' => 2020, 'month' => 3],
            ['app_id' => self::$app1->getId(), 'app_name' => 'a1', 'requests' => 1, 'year' => 2020, 'month' => 2],
            ['app_id' => self::$app1->getId(), 'app_name' => 'a1', 'requests' => 1, 'year' => 2020, 'month' => 1],
        ], self::$repository->monthlySummaryByApp(strtotime('2021-01-15'), 13));
    }

    public function testDailySummary()
    {
        $this->assertSame([
            ['requests' => 35, 'year' => 2021, 'month' => 1, 'day_of_month' => 2],
            ['requests' => 75, 'year' => 2021, 'month' => 1, 'day_of_month' => 1],
            ['requests' => 55, 'year' => 2020, 'month' => 12, 'day_of_month' => 31],
            ['requests' => 50, 'year' => 2020, 'month' => 12, 'day_of_month' => 30],
        ], self::$repository->dailySummary(strtotime('2021-01-15'), 8));
    }

    public function testHourlySummary()
    {
        $this->assertSame([
            ['app_id' => self::$app2->getId(), 'app_name' => 'a2', 'requests' => 35, 'year' => 2021,
                'month' => 1, 'day_of_month' => 2, 'hour' => 16],
            ['app_id' => self::$app2->getId(), 'app_name' => 'a2', 'requests' => 10, 'year' => 2021,
                'month' => 1, 'day_of_month' => 1, 'hour' => 15],
            ['app_id' => self::$app1->getId(), 'app_name' => 'a1', 'requests' => 45, 'year' => 2021,
                'month' => 1, 'day_of_month' => 1, 'hour' => 14],
            ['app_id' => self::$app2->getId(), 'app_name' => 'a2', 'requests' => 20, 'year' => 2021,
                'month' => 1, 'day_of_month' => 1, 'hour' => 14],
            ['app_id' => self::$app1->getId(), 'app_name' => 'a1', 'requests' => 55, 'year' => 2020,
                'month' => 12, 'day_of_month' => 31, 'hour' => 13],
            ['app_id' => self::$app1->getId(), 'app_name' => 'a1', 'requests' => 50, 'year' => 2020,
                'month' => 12, 'day_of_month' => 30, 'hour' => 12],
        ], self::$repository->hourlySummary(strtotime('2021-01-03 03:30:00'), 5));
    }
}
