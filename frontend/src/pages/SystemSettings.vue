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
        <li class="nav-item">
            <a class="nav-link" :class="{ 'active': tab === 'Directors' }"
               :href="'#SystemSettings/Directors'">Directors</a>
        </li>
    </ul>

    <!-- "allAlliances" and "allCorporations" are only for "Features" and "Mails" tabs -->
    <component v-bind:is="tab"
               :settings="settings"
               :allAlliances="alliances"
               :allCorporations="corporations"
               @changeSettingDelayed="changeSettingDelayed"
               @changeSetting="changeSetting"
    ></component>

</div>
</template>

<script>
import _ from 'lodash';
import {AllianceApi, CorporationApi, SettingsApi} from 'neucore-js-client';
import Customization from './SystemSettings--Customization.vue';
import Directors from './SystemSettings--Directors.vue';
import Features from './SystemSettings--Features.vue';
import Mails from './SystemSettings--Mails.vue';

export default {
    components: {
        Customization,
        Directors,
        Features,
        Mails,
    },

    props: {
        route: Array,
        settings: Object,
    },

    data () {
        return {
            tab: 'Customization',
            alliances: [],
            corporations: [],
        }
    },

    mounted () {
        window.scrollTo(0,0);
        setTab(this);

        // Make sure the data is up-to-date.
        this.emitter.emit('settingsChange');
    },

    watch: {
        route () {
            setTab(this);
            this.emitter.emit('settingsChange');
        },
    },

    methods: {
        changeSettingDelayed (name, value) {
            // use value from parameter (input event) instead of value from this.settings
            // because the model is not updated on touch devices during IME composition
            this.changeSettingDebounced(this, name, value);
        },

        changeSettingDebounced: _.debounce((vm, name, value) => {
            vm.changeSetting(name, value);
        }, 250),

        changeSetting (name, value) {
            const vm = this;
            new SettingsApi().systemChange(name, value, (error, data, response) => {
                if (error) { // 403 usually
                    if (response.statusCode === 403) {
                        vm.message('Unauthorized.', 'error');
                    }
                    return;
                }

                // Only propagate the change of variables that need it.
                // Also do not trigger for text fields because that can interfere negatively with editing, i.e.
                // the cursor will sometimes jump to the end of the input field or textarea.
                if ([
                        'allow_character_deletion',
                        'customization_home_logo',
                        'customization_nav_logo',
                    ].indexOf(name) !== -1 ||
                    name.indexOf('director_char_') !== -1
                ) {
                    vm.emitter.emit('settingsChange');
                }
            });
        },

        /**
         * Load alliance and corporation list, used by child components.
         */
        loadLists () {
            const vm = this;

            // get alliances
            new AllianceApi().all((error, data) => {
                if (error) { // 403 usually
                    return;
                }
                vm.alliances = data;
            });

            // get corporations
            new CorporationApi().userCorporationAll((error, data) => {
                if (error) { // 403 usually
                    return;
                }
                vm.corporations = data;
            });
        },

        /**
         * Helper function for alliance and corporation form selects used by child components.
         */
        buildIdArray (value, list) {
            const result = [];
            for (const id of value.split(',')) {
                for (const item of list) {
                    if (item.id === parseInt(id)) {
                        result.push(item);
                        break;
                    }
                }
            }
            return result;
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
    const tabs = ['Customization', 'Directors', 'Features', 'Mails'];
    if (tabs.indexOf(vm.route[1]) !== -1) {
        vm.tab = vm.route[1];
    }
}
</script>
