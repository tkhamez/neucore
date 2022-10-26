// Test for dynamic import and Promise support.
try {
    import('data:text/javascript;base64,Cg==').then(() => window.APP_SUPPORTED_BROWSER = true);
} catch (err) {
    window.APP_SUPPORTED_BROWSER = false;
}
