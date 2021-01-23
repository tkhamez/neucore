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
    /**
     * @var AppRequestsRepository
     */
    private static $repository;

    /**
     * @var App
     */
    private static $app1;

    /**
     * @var App
     */
    private static $app2;

    public static function setUpBeforeClass(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
        $om = $helper->getObjectManager();
        self::$app1 = (new App())->setName('a1')->setSecret('s');
        self::$app2 = (new App())->setName('a2')->setSecret('s');
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(45)
            ->setYear(2019)->setMonth(12)->setDayOfMonth(15));
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(1)
            ->setYear(2020)->setMonth(1)->setDayOfMonth(15));
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(1)
            ->setYear(2020)->setMonth(2)->setDayOfMonth(15));
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(1)
            ->setYear(2020)->setMonth(3)->setDayOfMonth(15));
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(1)
            ->setYear(2020)->setMonth(4)->setDayOfMonth(15));
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(1)
            ->setYear(2020)->setMonth(5)->setDayOfMonth(15));
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(1)
            ->setYear(2020)->setMonth(6)->setDayOfMonth(15));
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(1)
            ->setYear(2020)->setMonth(7)->setDayOfMonth(15));
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(1)
            ->setYear(2020)->setMonth(8)->setDayOfMonth(15));
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(1)
            ->setYear(2020)->setMonth(9)->setDayOfMonth(15));
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(1)
            ->setYear(2020)->setMonth(10)->setDayOfMonth(15));
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(1)
            ->setYear(2020)->setMonth(11)->setDayOfMonth(15));
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(50)
            ->setYear(2020)->setMonth(12)->setDayOfMonth(30));
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(55)
            ->setYear(2020)->setMonth(12)->setDayOfMonth(31));
        $om->persist((new AppRequests())->setApp(self::$app1)->setCount(45)
            ->setYear(2021)->setMonth(1)->setDayOfMonth(1));
        $om->persist((new AppRequests())->setApp(self::$app2)->setCount(30)
            ->setYear(2021)->setMonth(1)->setDayOfMonth(1));
        $om->persist((new AppRequests())->setApp(self::$app2)->setCount(35)
            ->setYear(2021)->setMonth(1)->setDayOfMonth(2));
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
        ], self::$repository->monthlySummary());
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
            //['app_id' => self::$app1->getId(), 'app_name' => 'a1', 'requests' => 45, 'year' => 2019, 'month' => 12],
        ], self::$repository->monthlySummaryByApp());
    }

    public function testDailySummary()
    {
        $this->assertSame([
            ['requests' => 35, 'year' => 2021, 'month' => 1, 'day_of_month' => 2],
            ['requests' => 75, 'year' => 2021, 'month' => 1, 'day_of_month' => 1],
            ['requests' => 55, 'year' => 2020, 'month' => 12, 'day_of_month' => 31],
            ['requests' => 50, 'year' => 2020, 'month' => 12, 'day_of_month' => 30],
            /*['requests' => 1, 'year' => 2020, 'month' => 11, 'day_of_month' => 15],
            ['requests' => 1, 'year' => 2020, 'month' => 10, 'day_of_month' => 15],
            ['requests' => 1, 'year' => 2020, 'month' => 9, 'day_of_month' => 15],
            ['requests' => 1, 'year' => 2020, 'month' => 8, 'day_of_month' => 15],
            ['requests' => 1, 'year' => 2020, 'month' => 7, 'day_of_month' => 15],
            ['requests' => 1, 'year' => 2020, 'month' => 6, 'day_of_month' => 15],
            ['requests' => 1, 'year' => 2020, 'month' => 5, 'day_of_month' => 15],
            ['requests' => 1, 'year' => 2020, 'month' => 4, 'day_of_month' => 15],
            ['requests' => 1, 'year' => 2020, 'month' => 3, 'day_of_month' => 15],
            ['requests' => 1, 'year' => 2020, 'month' => 2, 'day_of_month' => 15],
            ['requests' => 1, 'year' => 2020, 'month' => 1, 'day_of_month' => 15],
            ['requests' => 45, 'year' => 2019, 'month' => 12, 'day_of_month' => 15],*/
        ], self::$repository->dailySummary());
    }
}
