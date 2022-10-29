<template>
<div class="container-fluid">
    <div class="row mb-3 mt-3">
        <div class="col-lg-12">
            <h1>Statistics</h1>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-12">
            Until <input v-model="untilPlayerLogins" type="text" class="until">,
            Periods <input v-model="periodsPlayerLogins" type="text" class="periods"> months
            <div id="statisticsPlayerLogins"></div>

            Until <input v-model="untilTotalMonthlyApp" type="text" class="until">,
            Periods <input v-model="periodsTotalMonthlyApp" type="text" class="periods"> months
            <div id="statisticsTotalMonthlyApp"></div>

            Until <input v-model="untilMonthlyAppRequests" type="text" class="until">,
            Periods <input v-model="periodsMonthlyAppRequests" type="text" class="periods"> months
            <div id="statisticsMonthlyAppRequests"></div>

            Until <input v-model="untilTotalDailyApp" type="text" class="until">,
            Periods <input v-model="periodsTotalDailyApp" type="text" class="periods"> weeks
            <div id="statisticsTotalDailyApp"></div>

            Until <input v-model="untilHourlyAppRequests" type="text" class="until">,
            Periods <input v-model="periodsHourlyAppRequests" type="text" class="periods"> days
            <div id="statisticsHourlyAppRequests"></div>
        </div>
    </div>
</div>
</template>

<script>
import {StatisticsApi} from "neucore-js-client";
import * as echarts from 'echarts';

export default {
    data() {
        return {
            charts: {},
            api: new StatisticsApi(),
            periodsPlayerLogins: 12,
            periodsTotalMonthlyApp: 12,
            periodsMonthlyAppRequests: 12,
            periodsTotalDailyApp: 4,
            periodsHourlyAppRequests: 7,
            untilPlayerLogins: null,
            untilTotalMonthlyApp: null,
            untilMonthlyAppRequests: null,
            untilTotalDailyApp: null,
            untilHourlyAppRequests: null,
        }
    },

    mounted() {
        window.scrollTo(0, 0);

        window.addEventListener('resize', () => {
            for (const prop in this.charts) { // for...of does not work here
                this.charts[prop].resize();
            }
        });

        const now = new Date();
        this.untilPlayerLogins = `${now.getUTCFullYear()}-${now.getUTCMonth() + 1}`;
        this.untilTotalMonthlyApp = `${now.getUTCFullYear()}-${now.getUTCMonth() + 1}`;
        this.untilMonthlyAppRequests = `${now.getUTCFullYear()}-${now.getUTCMonth() + 1}`;
        this.untilTotalDailyApp = `${now.getUTCFullYear()}-${now.getUTCMonth() + 1}-${now.getUTCDate()}`;
        this.untilHourlyAppRequests =
            `${now.getUTCFullYear()}-${now.getUTCMonth() + 1}-${now.getUTCDate()} ${now.getUTCHours()}`;
    },

    unmounted() {
        for (const id in this.charts) {
            this.charts[id].dispose();
        }
    },

    watch: {
        periodsPlayerLogins() {
            getPlayerLoginsData(this);
        },
        periodsTotalMonthlyApp() {
            getTotalMonthlyAppData(this);
        },
        periodsMonthlyAppRequests() {
            getMonthlyAppRequestsData(this);
        },
        periodsTotalDailyApp() {
            getTotalDailyAppData(this);
        },
        periodsHourlyAppRequests() {
            getHourlyAppRequestsData(this)
        },
        untilPlayerLogins() {
            getPlayerLoginsData(this);
        },
        untilTotalMonthlyApp() {
            getTotalMonthlyAppData(this)
        },
        untilMonthlyAppRequests() {
            getMonthlyAppRequestsData(this)
        },
        untilTotalDailyApp() {
            getTotalDailyAppData(this)
        },
        untilHourlyAppRequests() {
            getHourlyAppRequestsData(this)
        },
    },
}

function getPlayerLoginsData(vm) {
    vm.api.statisticsPlayerLogins({until: vm.untilPlayerLogins, periods: vm.periodsPlayerLogins}, (error, data) => {
        if (!error) {
            chartPlayerLogins(vm, data.reverse());
        }
    });
}

function getTotalMonthlyAppData(vm) {
    vm.api.statisticsTotalMonthlyAppRequests(
        {until: vm.untilTotalMonthlyApp, periods: vm.periodsTotalMonthlyApp}, (error, data) => {
            if (!error) {
                chartTotalMonthlyApp(vm, data.reverse());
            }
        }
    );
}

function getMonthlyAppRequestsData(vm) {
    vm.api.statisticsMonthlyAppRequests(
        {until: vm.untilMonthlyAppRequests, periods: vm.periodsMonthlyAppRequests}, (error, data) => {
            if (!error) {
                chartAppRequests(vm, data.reverse(), 'App requests, monthly', 'months', 'statisticsMonthlyAppRequests');
            }
        }
    );
}

function getTotalDailyAppData(vm) {
    vm.api.statisticsTotalDailyAppRequests(
        {until: vm.untilTotalDailyApp, periods: vm.periodsTotalDailyApp}, (error, data) => {
            if (!error) {
                chartTotalDailyApp(vm, data.reverse());
            }
        }
    );
}

function getHourlyAppRequestsData(vm) {
    vm.api.statisticsHourlyAppRequests(
        {until: vm.untilHourlyAppRequests, periods: vm.periodsHourlyAppRequests}, (error, data) => {
            if (!error) {
                chartAppRequests(vm, data.reverse(), 'App requests, hourly', 'hours', 'statisticsHourlyAppRequests');
            }
        }
    );
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
        renderMode: 'richText',
    },
    grid: {
        left: 80,
        right: 10,
        bottom: 60,
    },
    legend: {
        bottom: 10,
        data: [],
    },
    toolbox: { feature: { saveAsImage: {} } },
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

function chartTotalMonthlyApp(vm, items) {
    const options = JSON.parse(JSON.stringify(chartOption));
    options.title.text = 'App requests, monthly total';
    options.series.push(copyObjectData(chartSeries));

    for (const data of items) {
        options.xAxis.data.push(`${data.year}-${data.month}`);
        options.series[0].data.push(data.requests);
    }

    initChart(vm, 'statisticsTotalMonthlyApp', options);
}

function chartAppRequests(vm, items, title, ticks, charId) {
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

function chartTotalDailyApp(vm, items) {
    const options = JSON.parse(JSON.stringify(chartOption));

    options.title.text = 'App requests, daily total';
    options.series.push(copyObjectData(chartSeries));
    for (const data of items) {
        options.xAxis.data.push(`${data.year}-${data.month}-${data.day_of_month}`);
        options.series[0].data.push(data.requests);
    }

    initChart(vm, 'statisticsTotalDailyApp', options);
}

function initChart(vm, id, options) {
    if (vm.charts[id]) {
        vm.charts[id].dispose();
    }
    const chart = echarts.init(
        document.getElementById(id),
        'dark',
        { renderer: 'canvas' }
    );
    chart.setOption(options, true);
    vm.charts[id] = chart;
}
</script>

<style scoped>
    #statisticsPlayerLogins,
    #statisticsTotalMonthlyApp,
    #statisticsMonthlyAppRequests,
    #statisticsTotalDailyApp,
    #statisticsHourlyAppRequests {
        width: 100%;
        height: 400px;
    }
    #statisticsPlayerLogins,
    #statisticsTotalMonthlyApp,
    #statisticsMonthlyAppRequests,
    #statisticsTotalDailyApp {
        margin-bottom: 30px;
    }

    input.periods {
        width: 50px;
    }
    input.until {
        width: 120px;
    }
</style>
