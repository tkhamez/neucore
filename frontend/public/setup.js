
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
