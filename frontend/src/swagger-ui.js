
import 'swagger-ui-dist/swagger-ui.css';
import SwaggerUIBundle from 'swagger-ui-dist/swagger-ui-bundle.js'
import SwaggerUIStandalonePreset from 'swagger-ui-dist/swagger-ui-standalone-preset.js'

const baseUrl = location.protocol + "//" + location.hostname + ':' + location.port + "/";
const ui = SwaggerUIBundle({
    urls: [
        { url: baseUrl + "application-api-3.yml", name: "Application API" },
        { url: baseUrl + "frontend-api-3.yml", name: "Frontend API" },
        { url: baseUrl + "openapi-3.yaml", name: "Complete API" },
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
});

ui.getConfigs().requestInterceptor = function(e) {
    e.url = e.url.replace(
        /http(s)?:\/\/localhost\//i,
        location.protocol + '//' + location.hostname + ':' + location.port + '/'
    );
    return e;
};
