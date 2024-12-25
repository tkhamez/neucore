"""
Neucore ESI proxy example using EsiPy (https://github.com/Kyria/EsiPy).
"""

import base64
import json
import os

import requests
from esipy import App, EsiClient, EsiSecurity


# Configuration values
core_domain = 'your.domain.tld'
core_app_token = base64.b64encode(b'1:secret')
core_char_id = '96061222'  # Character with token in Neucore


# Create OpenAPI definition file for Neucore ESI proxy
openapi_file = os.path.dirname(os.path.abspath(__file__)) + '/latest_swagger_core.json'
swagger = requests.get('https://esi.evetech.net/latest/swagger.json')
swagger_data = swagger.json()
swagger_data['basePath'] = '/api/app/v2/esi/latest'
swagger_data['host'] = core_domain
del swagger_data['parameters']['datasource']['enum']
with open(openapi_file, 'w') as f:
    json.dump(swagger_data, f)


# Create EsiPy client
app = App.create(openapi_file)
auth = EsiSecurity(
    redirect_uri='http://localhost',  # value doesn't matter
    client_id=1,  # value doesn't matter
    secret_key=1,  # value doesn't matter
    headers={'User-Agent': 'Neucore API Example'}
)
auth.update_token({
    'access_token': core_app_token.decode(),
    'expires_in': 9000,  # 150 minutes
    'refresh_token': ''
})
client = EsiClient(security=auth, headers={'User-Agent': 'Neucore API Example'})


# Make request
assets_request = app.op['get_characters_character_id_assets'](character_id=core_char_id, datasource=core_char_id)
assets_response = client.request(assets_request)
print(assets_response.data[0])
