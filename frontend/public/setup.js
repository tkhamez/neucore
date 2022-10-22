
function setTheme(theme) {
    if (!theme) {
        return;
    }
    window.APP_DEFAULT_THEME = theme;
    const links = document.getElementsByTagName('link');
    for (let i = 0; i < links.length; i++) {
        // noinspection JSStringConcatenationToES6Template
        if (links[i].getAttribute('href').indexOf('css/theme-' + theme.toLowerCase()) !== -1) {
            links[i].disabled = false;
            links[i].setAttribute('rel', 'stylesheet');
            break;
        }
    }
}
