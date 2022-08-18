<template>
<div class="container-fluid">
    <div class="row mb-3 mt-3">
        <div class="col-lg-12">
            <h1>Statistics</h1>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-12">
            Periods <input v-model="periodsPlayerLogins" type="text"> months
            <div id="statisticsPlayerLogins"></div>

            Periods <input v-model="periodsRequestsMonthly" type="text"> months
            <div id="statisticsRequestsMonthly"></div>

            Periods <input v-model="periodsRequestsMonthlyPerApp" type="text"> months
            <div id="statisticsRequestsMonthlyPerApp"></div>

            Periods <input v-model="periodsRequestsDaily" type="text"> weeks
            <div id="statisticsRequestsDaily"></div>

            Periods <input v-model="periodsRequestsHourly" type="text"> days
            <div id="statisticsRequestsHourly"></div>
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
            charts: {},
            api: new StatisticsApi(),
            until: Math.floor(Date.now() / 1000),
            periodsPlayerLogins: 12,
            periodsRequestsMonthly: 12,
            periodsRequestsMonthlyPerApp: 12,
            periodsRequestsDaily: 4,
            periodsRequestsHourly: 7,
        }
    },

    mounted () {
        window.scrollTo(0, 0);
        getData(this);

        const vm = this;
        window.addEventListener('resize', () => {
            for (const prop in vm.charts) { // for...of does not work here
                if (!vm.charts.hasOwnProperty(prop)) {
                    console.log(prop);
                }
                vm.charts[prop].resize();
            }
        });
    },

    watch: {
        periodsPlayerLogins () {
            getPlayerLoginsData(this);
        },
        periodsRequestsMonthly () {
            getTotalMonthlyAppRequestsData(this);
        },
        periodsRequestsMonthlyPerApp () {
            getMonthlyAppRequestsData(this);
        },
        periodsRequestsDaily () {
            getTotalDailyAppRequestsData(this);
        },
        periodsRequestsHourly () {
            getHourlyAppRequestsData(this)
        },
    },
}

function getData(vm) {
    getPlayerLoginsData(vm);
    getTotalMonthlyAppRequestsData(vm);
    getMonthlyAppRequestsData(vm);
    getTotalDailyAppRequestsData(vm);
    getHourlyAppRequestsData(vm);
}

function getPlayerLoginsData(vm) {
    vm.api.statisticsPlayerLogins({until: vm.until, periods: vm.periodsPlayerLogins}, (error, data) => {
        if (!error) {
            chartPlayerLogins(vm, data.reverse());
        }
    });
}

function getTotalMonthlyAppRequestsData(vm) {
    vm.api.statisticsTotalMonthlyAppRequests({until: vm.until, periods: vm.periodsRequestsMonthly}, (error, data) => {
        if (!error) {
            chartRequestsMonthly(vm, data.reverse());
        }
    });
}

function getMonthlyAppRequestsData(vm) {
    vm.api.statisticsMonthlyAppRequests({until: vm.until, periods: vm.periodsRequestsMonthlyPerApp}, (error, data) => {
        if (!error) {
            chartAppsRequests(vm, data.reverse(), 'App requests, monthly', 'months', 'statisticsRequestsMonthlyPerApp');
        }
    });
}

function getTotalDailyAppRequestsData(vm) {
    vm.api.statisticsTotalDailyAppRequests({until: vm.until, periods: vm.periodsRequestsDaily}, (error, data) => {
        if (!error) {
            chartRequestsDaily(vm, data.reverse());
        }
    });
}

function getHourlyAppRequestsData(vm) {
    vm.api.statisticsHourlyAppRequests({until: vm.until, periods: vm.periodsRequestsHourly}, (error, data) => {
        if (!error) {
            chartAppsRequests(vm, data.reverse(), 'App requests, hourly', 'hours', 'statisticsRequestsHourly');
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

function chartPlayerLogins(vm, items) {
    const options = copyObjectData(chartOption);
    options.title.text = 'Player logins';
    options.series.push(copyObjectData(chartSeries));
    options.series.push(copyObjectData(chartSeries));
    options.legend.data = ['total logins', 'unique logins'];
    options.series[0].name = 'total logins';
    options.series[1].name = 'unique logins';

    for (const data of items) {
        options.xAxis.data.push(`${data.year}-${data.month}`);
        options.series[0].data.push(data.total_logins);
        options.series[1].data.push(data.unique_logins);
    }

    initChart(vm, 'statisticsPlayerLogins', options);
}

function chartRequestsMonthly(vm, items) {
    const options = JSON.parse(JSON.stringify(chartOption));
    options.title.text = 'App requests, monthly total';
    options.series.push(copyObjectData(chartSeries));

    for (const data of items) {
        options.xAxis.data.push(`${data.year}-${data.month}`);
        options.series[0].data.push(data.requests);
    }

    initChart(vm, 'statisticsRequestsMonthly', options);
}

function chartAppsRequests(vm, items, title, ticks, charId) {
    const options = JSON.parse(JSON.stringify(chartOption));
    options.title.text = title;
    options.grid.bottom = 81; // more space for legend (2 rows)

    const appToSeries = {};
    const ticksToData = {};
    let nextSeries = -1;
    let nextData = -1;
    for (const data of items) {
        let ident; // = label
        if (ticks === 'months') {
            ident = `${data.year}-${data.month}`;
        } else if (ticks === 'hours') {
            ident = `${data.year}-${data.month}-${data.day_of_month} ${data.hour} h`;
        } else {
            return;
        }

        // Note: the data is ordered by date (time) ascending

        // get current series and data index
        if (!appToSeries.hasOwnProperty(data.app_id)) {
            nextSeries ++;
            appToSeries[data.app_id] = nextSeries;
        }
        if (!ticksToData.hasOwnProperty(ident)) {
            nextData ++;
            ticksToData[ident] = nextData;
        }
        const seriesIndex = appToSeries[data.app_id];
        const dataIndex = ticksToData[ident];

        // add axis labels
        if (options.xAxis.data.length - 1 < dataIndex) {
            options.xAxis.data.push(ident);
        }

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

    initChart(vm, charId, options);
}

function chartRequestsDaily(vm, items) {
    const options = JSON.parse(JSON.stringify(chartOption));

    options.title.text = 'App requests, daily total';
    options.series.push(copyObjectData(chartSeries));
    for (const data of items) {
        options.xAxis.data.push(`${data.year}-${data.month}-${data.day_of_month}`);
        options.series[0].data.push(data.requests);
    }

    initChart(vm, 'statisticsRequestsDaily', options);
}

function initChart(vm, id, options) {
    if (vm.charts[id]) {
        vm.charts[id].dispose();
    }
    const chart = echarts.init(document.getElementById(id), 'dark', {renderer: 'svg'});
    chart.setOption(options, true);
    vm.charts[id] = chart;
}
</script>

<style scoped>
    #statisticsPlayerLogins,
    #statisticsRequestsMonthly,
    #statisticsRequestsMonthlyPerApp,
    #statisticsRequestsDaily,
    #statisticsRequestsHourly {
        width: 100%;
        height: 400px;
    }
    #statisticsPlayerLogins,
    #statisticsRequestsMonthly,
    #statisticsRequestsMonthlyPerApp,
    #statisticsRequestsDaily {
        margin-bottom: 30px;
    }

    input {
        width: 50px;
    }
</style>
