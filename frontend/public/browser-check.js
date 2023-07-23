// Test for dynamic import and Promise support.
try {
    import('./browser-check-import.js').then(() => window.APP_SUPPORTED_BROWSER = true);
} catch (err) {
    window.APP_SUPPORTED_BROWSER = false;
}
