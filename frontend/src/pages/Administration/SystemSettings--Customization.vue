<template>
<div class="card border-secondary mb-3">
    <div class="card-body">
        <label class="col-form-label" for="customizationDocumentTitle">Document Title</label>
        <input id="customizationDocumentTitle" type="text" class="form-control"
               v-model="settings.customization_document_title"
               v-on:input="$emit('changeSettingDelayed', 'customization_document_title', $event.target.value)">
        <div class="form-text ln-sm">
            Value for HTML head title tag, i. e. name of the browser tab or bookmark.
        </div>
        <hr>
        <label class="col-form-label" for="customizationHomepage">Website</label>
        <input id="customizationHomepage" type="text" class="form-control"
               v-model="settings.customization_website"
               v-on:input="$emit('changeSettingDelayed', 'customization_website', $event.target.value)">
        <div class="form-text ln-sm">
            URL for the links of the logos in the navigation bar and on the home page.
        </div>
        <hr>
        <label class="col-form-label" for="customizationNavTitle">Navigation Title</label>
        <input id="customizationNavTitle" type="text" class="form-control"
               v-model="settings.customization_nav_title"
               v-on:input="$emit('changeSettingDelayed', 'customization_nav_title', $event.target.value)">
        <div class="form-text ln-sm">Organization name used in navigation bar.</div>
        <hr>
        <label for="customizationNavLogo" class="col-form-label">Navigation Logo</label><br>
        <img :src="settings.customization_nav_logo" alt="logo"> &nbsp;
        <input type="file" class="mt-1" ref="customization_nav_logo"
               id="customizationNavLogo" v-on:change="handleFileUpload('customization_nav_logo')"><br>
        <div class="form-text ln-sm">Organization logo used in navigation bar.</div>
        <hr>
        <label class="col-form-label" for="customizationHomeHeadline">Home Page Headline</label>
        <input id="customizationHomeHeadline" type="text" class="form-control"
               v-model="settings.customization_home_headline"
               v-on:input="$emit('changeSettingDelayed', 'customization_home_headline', $event.target.value)">
        <div class="form-text ln-sm">Headline on the home page.</div>
        <hr>
        <label class="col-form-label" for="customizationHomeDescription">Home Page Description</label>
        <input id="customizationHomeDescription" type="text" class="form-control"
               v-model="settings.customization_home_description"
               v-on:input="$emit('changeSettingDelayed', 'customization_home_description', $event.target.value)">
        <div class="form-text ln-sm">Text below the headline on the home page.</div>
        <hr>
        <label for="customizationHomeLogo" class="col-form-label">Home Page Logo</label><br>
        <img :src="settings.customization_home_logo" alt="logo"> &nbsp;
        <input type="file" class="mt-1" ref="customization_home_logo"
               id="customizationHomeLogo" v-on:change="handleFileUpload('customization_home_logo')"><br>
        <div class="form-text ln-sm">Organization logo used on the home page.</div>
        <hr>
        <label class="col-form-label" for="customizationLoginText">Login Text</label>
        <textarea id="customizationLoginText" class="form-control" rows="3"
                  v-model="settings.customization_login_text"
               v-on:input="$emit('changeSettingDelayed', 'customization_login_text', $event.target.value)"></textarea>
        <div class="form-text ln-sm">Optional text below the login button, supports Markdown (see next field).</div>
        <hr>
        <label for="customizationHomeMarkdown" class="col-form-label">Home Page Text Area</label><br>
        <textarea id="customizationHomeMarkdown" class="form-control" rows="9"
                  v-model="settings.customization_home_markdown"
                  v-on:input="$emit('changeSettingDelayed', 'customization_home_markdown', $event.target.value)"></textarea>
        <div class="form-text lh-sm">
            Optional text area on the home page. Supports
            <a class="external" href="https://markdown-it.github.io/" target="_blank"
               rel="noopener noreferrer">Markdown</a>, with "typographer" enabled and these plugins:
            <a class="external" href="https://github.com/arve0/markdown-it-attrs" target="_blank"
               rel="noopener noreferrer">attrs</a>
            (use, for example,  with Bootstrap classes "text-primary", "bg-warning"
            <a class="external" href="https://bootswatch.com/darkly/" target="_blank"
               rel="noopener noreferrer">etc.</a>),
            <a class="external" href="https://github.com/markdown-it/markdown-it-emoji/blob/master/lib/data/light.mjs"
               target="_blank" rel="noopener noreferrer">emoji</a> light,
            <a class="external" href="https://github.com/markdown-it/markdown-it-mark"
               target="_blank" rel="noopener noreferrer">mark</a>,
            <a class="external" href="https://github.com/markdown-it/markdown-it-sub"
               target="_blank" rel="noopener noreferrer">sub</a>,
            <a class="external" href="https://github.com/markdown-it/markdown-it-sup"
               target="_blank" rel="noopener noreferrer">sup</a>,
            <a class="external" href="https://github.com/markdown-it/markdown-it-ins"
               target="_blank" rel="noopener noreferrer">ins</a>,
            <a class="external" href="https://github.com/markdown-it/markdown-it-abbr"
               target="_blank" rel="noopener noreferrer">abbr</a>.<br>
            Tip: You can create external links with an icon like this:
            <code>[Neucore](https://github.com/tkhamez/neucore){target="_blank" rel="noopener noreferrer" class="external"}</code>
        </div>
        <br>
        <hr>
        <label class="col-form-label" for="customizationFooterText">Footer Text</label>
        <input id="customizationFooterText" type="text" class="form-control"
               v-model="settings.customization_footer_text"
               v-on:input="$emit('changeSettingDelayed', 'customization_footer_text', $event.target.value)">
        <div class="form-text ln-sm">Text for the footer.</div>
    </div>
</div>
</template>

<script>
export default {
    inject: ['store'],

    data() {
        return {
            settings: { ...this.store.state.settings },
        }
    },

    methods: {
        handleFileUpload(name) {
            const file = this.$refs[name].files[0];
            const reader  = new FileReader();

            reader.addEventListener('load', () => {
                const image = reader.result;
                this.$emit('changeSetting', name, image);
                if (name === 'customization_nav_logo') {
                    this.settings.customization_nav_logo = image;
                } else if (name === 'customization_home_logo') {
                    this.settings.customization_home_logo = image;
                }
            }, false);

            if (file) {
                reader.readAsDataURL(file)
            }
        },
    },
}
</script>
