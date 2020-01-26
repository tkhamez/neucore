
import 'swagger-ui/dist/swagger-ui.css';
import SwaggerUI from 'swagger-ui'

const ui = SwaggerUI({
    url: location.protocol + "//" + location.hostname + ':' + location.port + "/openapi-3.yaml",
    dom_id: '#swagger-ui',
    deepLinking: true,
    docExpansion: 'none',
    presets: [
        SwaggerUI.presets.apis,
    ],
});

ui.getConfigs().requestInterceptor = function(e) {
    e.url = e.url.replace(
        /http(s)?:\/\/localhost\//i,
        location.protocol + '//' + location.hostname + ':' + location.port + '/'
    );
    return e;
};
