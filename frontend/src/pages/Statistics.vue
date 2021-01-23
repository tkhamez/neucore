<template>
<div class="container-fluid">
    <div class="row mb-3 mt-3">
        <div class="col-lg-12">
            <h1>Statistics</h1>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-12">
            {{ logins }}<br>
            <br>
            {{ requestsMonthly }}<br>
            <br>
            {{ requestsMonthlyPerApp }}<br>
            <br>
            {{ requestsDaily }}<br>
            <br>
        </div>
    </div>
</div>
</template>

<script>
import {StatisticsApi} from "neucore-js-client";

export default {
    data () {
        return {
            logins: [],
            requestsMonthly: [],
            requestsMonthlyPerApp: [],
            requestsDaily: [],
        }
    },

    mounted () {
        window.scrollTo(0, 0);
        getData(this);
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
            vm.logins = data;
        }
    });
    api.statisticsTotalMonthlyAppRequests((error, data) => {
        if (!error) {
            vm.requestsMonthly = data;
        }
    });
    api.statisticsMonthlyAppRequests((error, data) => {
        if (!error) {
            vm.requestsMonthlyPerApp = data;
        }
    });
    api.statisticsTotalDailyAppRequests((error, data) => {
        if (!error) {
            vm.requestsDaily = data;
        }
    });
}
</script>

<style scoped>

</style>
