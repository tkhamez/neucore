
window.addEventListener('load', function () {
    if (!window.APP_SUPPORTED_BROWSER) {
        // noinspection ES6ConvertVarToLetConst
        var div = document.createElement('div');
        div.innerHTML =
            '<h2>&nbsp; Neucore - Alliance Core Services</h2>' +
            '<p>' +
            '    &nbsp; You are using an <strong>outdated browser</strong>. Please' +
            '    <a href="https://browsehappy.com/" target="_blank" rel="noopener noreferrer">' +
            '        upgrade your browser</a> to improve your experience and security.' +
            '</p>';
        document.body.insertBefore(div, document.getElementById('app'));
    }
});

function setTheme(theme) {
    if (!theme) {
        return;
    }
    window.APP_DEFAULT_THEME = theme;
    const links = document.getElementsByTagName('link');
    for (let i = 0; i < links.length; i++) {
        const href = links[i].getAttribute('href');
        if (href && href.indexOf('css/theme-' + theme.toLowerCase()) !== -1) {
            links[i].disabled = false;
            links[i].setAttribute('rel', 'stylesheet');
            break;
        }
    }
}
