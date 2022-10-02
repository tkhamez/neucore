<template>
    <div>
        <a :href="settings.customization_website">
            <img v-if="settings.customization_home_logo" :src="settings.customization_home_logo" alt="Logo"
                 class="float-end ms-3">
        </a>
        <h1 class="display-3">{{ settings.customization_home_headline }}</h1>
        <p class="lead">{{ settings.customization_home_description }}</p>
        <hr class="my-4">
    </div>
</template>

<script>
import {toRef} from "vue";

export default {
    inject: ['store'],

    data() {
        return {
            settings: toRef(this.store.state, 'settings'),
        }
    },
}
</script>

<style scoped>
    @media (max-width: 767px) {
        img {
            max-width: 35%;
        }
        h1 {
            font-size: 2.2rem;
        }
    }
</style>
