<template>
    <div>
        <a :href="settings.customization_website">
            <img v-if="settings.customization_home_logo" class="float-right" alt="Logo"
                 :src="settings.customization_home_logo">
        </a>
        <h1 class="display-3">{{ settings.customization_home_headline }}</h1>
        <p class="lead">{{ settings.customization_home_description }}</p>
        <hr class="my-4">
    </div>
</template>

<script>
module.exports = {
    props: {
        settings: Object,
    },
}
</script>
