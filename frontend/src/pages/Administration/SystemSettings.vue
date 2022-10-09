<template>
<div class="container-fluid">

    <div class="row mb-3 mt-3">
        <div class="col-lg-12">
            <h1>System Settings</h1>
        </div>
    </div>

    <ul class="nav nav-pills nav-fill">
        <li class="nav-item">
            <a class="nav-link" :class="{ 'active': tab === 'Customization' }"
               :href="'#SystemSettings/Customization'">Customization</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" :class="{ 'active': tab === 'Features' }"
               :href="'#SystemSettings/Features'">Features</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" :class="{ 'active': tab === 'Mails' }"
               :href="'#SystemSettings/Mails'">EVE Mails</a>
        </li>
    </ul>

    <component v-bind:is="tab"
               @changeSettingDelayed="changeSettingDelayed"
               @changeSetting="changeSetting"
    ></component>

</div>
</template>

<script>
import _ from 'lodash';
import {SettingsApi} from 'neucore-js-client';
import Helper from "../../classes/Helper";
import Customization from './SystemSettings--Customization.vue';
import Features from './SystemSettings--Features.vue';
import Mails from './SystemSettings--Mails.vue';

export default {
    components: {
        Customization,
        Features,
        Mails,
    },

    props: {
        route: Array,
    },

    data() {
        return {
            h: new Helper(this),
            tab: 'Customization',
            alliances: [],
        }
    },

    mounted() {
        window.scrollTo(0,0);
        setTab(this);

        // Make sure the data is up-to-date.
        this.emitter.emit('settingsChange');
    },

    unmounted() {
        // Make sure the data is updated everywhere.
        this.emitter.emit('settingsChange');
    },

    watch: {
        route() {
            setTab(this);
            this.emitter.emit('settingsChange');
        },
    },

    methods: {
        changeSettingDelayed(name, value) {
            // use value from parameter (input event) instead of value from this.settings
            // because the model is not updated on touch devices during IME composition
            this.changeSettingDebounced(this, name, value);
        },

        changeSettingDebounced: _.debounce((vm, name, value) => {
            vm.changeSetting(name, value);
        }, 250),

        changeSetting(name, value) {
            new SettingsApi().systemChange(name, value, (error, data, response) => {
                if (error && response.statusCode === 403) {
                    this.h.message('Unauthorized.', 'error');
                }
            });
        },

        /**
         * Helper function for alliance and corporation form selects used by child components.
         */
        buildIdString (newValues, oldValues, model) {
            if (oldValues === null) {
                return null;
            }
            const oldIds = [];
            for (const oldValue of oldValues) {
                oldIds.push(oldValue.id);
            }
            const newIds = [];
            for (const item of model) {
                newIds.push(item.id);
            }
            if (newIds.join(',') === oldIds.join(',')) {
                return null;
            }
            return newIds.join(',');
        },
    },
}

function setTab(vm) {
    const tabs = ['Customization', 'Features', 'Mails'];
    if (tabs.indexOf(vm.route[1]) !== -1) {
        vm.tab = vm.route[1];
    }
}
</script>
