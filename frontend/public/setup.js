
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

// Restore the colour mode from localStorage; the default matches the system preference.
(function() {
    const saved = localStorage.getItem('neucore-theme');
    const darkQuery = window.matchMedia('(prefers-color-scheme: dark)');
    const mode =
        ['dark', 'light'].indexOf(saved) !== -1
        ? saved
        : (darkQuery.matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-bs-theme', mode);
})();
