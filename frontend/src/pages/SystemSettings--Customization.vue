<template>
<div class="card border-secondary mb-3">
    <div class="card-body">
        <label class="col-form-label" for="customizationDocumentTitle">Document Title</label>
        <input id="customizationDocumentTitle" type="text" class="form-control"
               v-model="settings.customization_document_title"
               v-on:input="$emit('changeSettingDelayed', 'customization_document_title', $event.target.value)">
        <span class="form-text">
            Value for HTML head title tag, i. e. name of the browser tab or bookmark.
        </span>
        <hr>
        <label class="col-form-label" for="customizationHomepage">Website</label>
        <input id="customizationHomepage" type="text" class="form-control"
               v-model="settings.customization_website"
               v-on:input="$emit('changeSettingDelayed', 'customization_website', $event.target.value)">
        <span class="form-text">
            URL for the links of the logos in the navigation bar and on the home page.
        </span>
        <hr>
        <label class="col-form-label" for="customizationNavTitle">Navigation Title</label>
        <input id="customizationNavTitle" type="text" class="form-control"
               v-model="settings.customization_nav_title"
               v-on:input="$emit('changeSettingDelayed', 'customization_nav_title', $event.target.value)">
        <span class="form-text">Organization name used in navigation bar.</span>
        <hr>
        <label for="customizationNavLogo" class="col-form-label">Navigation Logo</label><br>
        <img :src="settings.customization_nav_logo" alt="logo"> &nbsp;
        <input type="file" class="mt-1" ref="customization_nav_logo"
               id="customizationNavLogo" v-on:change="handleFileUpload('customization_nav_logo')"><br>
        <span class="form-text">Organization logo used in navigation bar.</span>
        <hr>
        <label class="col-form-label" for="customizationHomeHeadline">Home Page Headline</label>
        <input id="customizationHomeHeadline" type="text" class="form-control"
               v-model="settings.customization_home_headline"
               v-on:input="$emit('changeSettingDelayed', 'customization_home_headline', $event.target.value)">
        <span class="form-text">Headline on the home page.</span>
        <hr>
        <label class="col-form-label" for="customizationHomeDescription">Home Page Description</label>
        <input id="customizationHomeDescription" type="text" class="form-control"
               v-model="settings.customization_home_description"
               v-on:input="$emit('changeSettingDelayed', 'customization_home_description', $event.target.value)">
        <span class="form-text">Text below the headline on the home page.</span>
        <hr>
        <label for="customizationHomeLogo" class="col-form-label">Home Page Logo</label><br>
        <img :src="settings.customization_home_logo" alt="logo">
        <input type="file" class="mt-1" ref="customization_home_logo"
               id="customizationHomeLogo" v-on:change="handleFileUpload('customization_home_logo')"><br>
        <span class="form-text">Organization logo used on the home page.</span>
        <hr>
        <label class="col-form-label" for="customizationLoginText">Login Text</label>
        <textarea id="customizationLoginText" class="form-control" rows="3"
                  v-model="settings.customization_login_text"
               v-on:input="$emit('changeSettingDelayed', 'customization_login_text', $event.target.value)"></textarea>
        <span class="form-text">Optional text below the login button, supports Markdown (see next field).</span>
        <hr>
        <label for="customizationHomeMarkdown" class="col-form-label">Home Page Text Area</label><br>
        <textarea id="customizationHomeMarkdown" class="form-control" rows="9"
                  v-model="settings.customization_home_markdown"
                  v-on:input="$emit('changeSettingDelayed', 'customization_home_markdown', $event.target.value)"></textarea>
        <span class="form-text">
            Optional text area on the home page. Supports
            <a href="https://markdown-it.github.io/" target="_blank" rel="noopener noreferrer">Markdown</a>,
            with "typographer" and these plugins:
            <a href="https://github.com/arve0/markdown-it-attrs"
               target="_blank" rel="noopener noreferrer">attrs</a>
            (use with Bootstrap classes "text-primary", "bg-warning"
            <a href="https://bootswatch.com/darkly/" target="_blank" rel="noopener noreferrer">etc.</a>),
            <a href="https://github.com/markdown-it/markdown-it-emoji/blob/master/lib/data/light.json"
               target="_blank" rel="noopener noreferrer">emoji</a> light,
            <a href="https://github.com/markdown-it/markdown-it-mark"
               target="_blank" rel="noopener noreferrer">mark</a>,
            <a href="https://github.com/markdown-it/markdown-it-sub"
               target="_blank" rel="noopener noreferrer">sub</a>,
            <a href="https://github.com/markdown-it/markdown-it-sup"
               target="_blank" rel="noopener noreferrer">sup</a>,
            <a href="https://github.com/markdown-it/markdown-it-ins"
               target="_blank" rel="noopener noreferrer">ins</a>,
            <a href="https://github.com/markdown-it/markdown-it-abbr"
               target="_blank" rel="noopener noreferrer">abbr</a>.
        </span><br>
        <hr>
        <label class="col-form-label" for="customizationFooterText">Footer Text</label>
        <input id="customizationFooterText" type="text" class="form-control"
               v-model="settings.customization_footer_text"
               v-on:input="$emit('changeSettingDelayed', 'customization_footer_text', $event.target.value)">
        <span class="form-text">Text for the footer.</span>
        <hr>
        <label class="col-form-label" for="customizationGithub">GitHub</label>
        <input id="customizationGithub" type="text" class="form-control"
               v-model="settings.customization_github"
               v-on:input="$emit('changeSettingDelayed', 'customization_github', $event.target.value)">
        <span class="form-text">
            URL of GitHub repository for various links to the documentation.
        </span>
    </div>
</div>
</template>

<script>
export default {
    props: {
        settings: Object,
    },

    methods: {
        handleFileUpload (name) {
            const vm = this;
            const file = this.$refs[name].files[0];
            const reader  = new FileReader();

            reader.addEventListener('load', () => {
                const image = reader.result;
                vm.$emit('changeSetting', name, image);
            }, false);

            if (file) {
                reader.readAsDataURL(file)
            }
        },
    },
}
</script>
