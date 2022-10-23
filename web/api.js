
const baseUrl = `${location.protocol}//${location.hostname}:${location.port}/`;

const ui = SwaggerUIBundle({
    urls: [
        { url: `${baseUrl}application-api-3.yml`, name: "Application API" },
        { url: `${baseUrl}frontend-api-3.yml`, name: "Frontend API" },
        { url: `${baseUrl}openapi-3.yaml`, name: "Complete API" },
    ],
    dom_id: '#swagger-ui',
    deepLinking: true,
    docExpansion: 'list',
    defaultModelsExpandDepth: 0,
    presets: [
        SwaggerUIBundle.presets.apis,
        SwaggerUIStandalonePreset,
    ],
    layout: 'StandaloneLayout',
    validatorUrl: null,
});

ui.getConfigs().requestInterceptor = function(e) {
    e.url = e.url.replace(/http(s)?:\/\/localhost\//i, baseUrl);
    return e;
};
