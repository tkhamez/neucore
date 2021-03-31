<template>
<div class="container-fluid">
    <div class="row mb-3 mt-3">
        <div class="col-lg-12">
            <h1>Statistics</h1>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-12">
            <div id="statisticsPlayerLogins"></div>
            <div id="statisticsRequestsMonthly"></div>
            <div id="statisticsRequestsMonthlyPerApp"></div>
            <div id="statisticsRequestsDaily"></div>
        </div>
    </div>
</div>
</template>

<script>
import {StatisticsApi} from "neucore-js-client";
import * as echarts from 'echarts';

export default {
    data () {
        return {
            playerLogins: [],
            requestsMonthly: [],
            requestsMonthlyPerApp: [],
            requestsDaily: [],
            charts: [],
        }
    },

    mounted () {
        window.scrollTo(0, 0);
        getData(this);

        const vm = this;
        window.addEventListener('resize', () => {
            for (const chart of vm.charts) {
                chart.resize();
            }
        });
    },
}

function getData(vm) {
    vm.logins = [];
    vm.requestsMonthly = [];
    vm.requestsMonthlyPerApp = [];
    vm.requestsDaily = [];
    const api = new StatisticsApi();
    api.statisticsPlayerLogins((error, data) => {
        if (!error) {
            vm.playerLogins = data.reverse();
            chartPlayerLogins(vm);
        }
    });
    api.statisticsTotalMonthlyAppRequests((error, data) => {
        if (!error) {
            vm.requestsMonthly = data.reverse();
            chartRequestsMonthly(vm);
        }
    });
    api.statisticsMonthlyAppRequests({startTime: 0}, (error, data) => {
        if (!error) {
            vm.requestsMonthlyPerApp = data.reverse();
            chartRequestsMonthlyPerApp(vm);
        }
    });
    api.statisticsTotalDailyAppRequests({startTime: 0}, (error, data) => {
        if (!error) {
            vm.requestsDaily = data.reverse();
            chartRequestsDaily(vm);
        }
    });
}

const chartOption = {
    title: {
        text: '',
    },
    tooltip: {
        backgroundColor: 'rgba(50, 50, 50, 0.9)',
        borderColor: 'rgba(50, 50, 50, 0.9)',
        textStyle: {
            color: 'rgb(205, 205, 205)',
        },
        trigger: 'axis',
        enterable: true,
        //confine: true,
    },
    grid: {
        bottom: 60,
    },
    legend: {
        bottom: 10,
        data: [],
    },
    //toolbox: { feature: { saveAsImage: {} } },
    xAxis: {
        type: 'category',
        data: [],
    },
    yAxis: {
        type: 'value',
    },
    series: [],
    backgroundColor: '#1e1d23' // rgba(16, 12, 42, 0.2), #100C2A
};

const chartSeries = {
    name: '',
    type: 'line',
    data: [],
}

function copyObjectData(object) {
    return JSON.parse(JSON.stringify(object));
}

function chartPlayerLogins(vm) {
    const options = copyObjectData(chartOption);
    options.title.text = 'Player logins';
    options.series.push(copyObjectData(chartSeries));
    options.series.push(copyObjectData(chartSeries));
    options.legend.data = ['total logins', 'unique logins'];
    options.series[0].name = 'total logins';
    options.series[1].name = 'unique logins';

    for (const data of vm.playerLogins) {
        options.xAxis.data.push(`${data.year}-${data.month}`);
        options.series[0].data.push(data.total_logins);
        options.series[1].data.push(data.unique_logins);
    }

    initChart(vm, 'statisticsPlayerLogins', options);
}

function chartRequestsMonthly(vm) {
    const options = JSON.parse(JSON.stringify(chartOption));
    options.title.text = 'App requests, monthly total';
    options.series.push(copyObjectData(chartSeries));

    for (const data of vm.requestsMonthly) {
        options.xAxis.data.push(`${data.year}-${data.month}`);
        options.series[0].data.push(data.requests);
    }

    initChart(vm, 'statisticsRequestsMonthly', options);
}

function chartRequestsMonthlyPerApp(vm) {
    const options = JSON.parse(JSON.stringify(chartOption));
    options.title.text = 'App requests, monthly';
    options.grid.bottom = 81; // more space for legend (2 rows)

    const appToSeries = {};
    const monthToData = {};
    let nextSeries = -1;
    let nextData = -1;
    for (const data of vm.requestsMonthlyPerApp) {
        const month = `${data.year}-${data.month}`;

        // Note: the data is ordered by date (year-month) ascending

        // add axis labels
        if (options.xAxis.data.indexOf(month) === -1) {
            options.xAxis.data.push(month);
        }

        // get current series and data index
        if (!appToSeries.hasOwnProperty(data.app_id)) {
            nextSeries ++;
            appToSeries[data.app_id] = nextSeries;
        }
        if (!monthToData.hasOwnProperty(month)) {
            nextData ++;
            monthToData[month] = nextData;
        }
        const seriesIndex = appToSeries[data.app_id];
        const dataIndex = monthToData[month];

        // create new series and data if missing
        if (options.series.length - 1 < seriesIndex) {
            options.series.push(copyObjectData(chartSeries));
            options.legend.data.push(data.app_name);
            options.series[seriesIndex].name = data.app_name;
        }
        if (options.series[seriesIndex].data.length + 1 < dataIndex) {
            options.series[seriesIndex].data.push(null);
        }

        options.series[seriesIndex].data[dataIndex] = data.requests;
    }

    initChart(vm, 'statisticsRequestsMonthlyPerApp', options);
}

function chartRequestsDaily(vm) {
    const options = JSON.parse(JSON.stringify(chartOption));

    options.title.text = 'App requests, daily total';
    options.series.push(copyObjectData(chartSeries));
    for (const data of vm.requestsDaily) {
        options.xAxis.data.push(`${data.year}-${data.month}-${data.day_of_month}`);
        options.series[0].data.push(data.requests);
    }

    initChart(vm, 'statisticsRequestsDaily', options);
}

function initChart(vm, id, options) {
    const chart = echarts.init(document.getElementById(id), 'dark', {renderer: 'svg'});
    chart.setOption(options);
    vm.charts.push(chart);
}
</script>

<style scoped>
    #statisticsPlayerLogins,
    #statisticsRequestsMonthly,
    #statisticsRequestsMonthlyPerApp,
    #statisticsRequestsDaily {
        width: 100%;
        height: 400px;
    }
    #statisticsRequestsMonthly,
    #statisticsRequestsMonthlyPerApp,
    #statisticsRequestsDaily {
        margin-top: 30px;
    }
</style>
