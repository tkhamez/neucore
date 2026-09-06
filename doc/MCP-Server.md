# MCP Server

The following examples demonstrate how to use the Neucore MCP server.

The server supports only the modern protocol version 2026-07-28.


## Using an Agent

1. Create app and token

Create a new app in Neucore with the role `app-mcp` and generate a secret for it.
Then create the token like this:

```bash
echo -n "<app id>:<app secret>" | base64
```

2. Configure your agent
 
- URL: `https://neucore.domain.tld/api/app/v1/mcp`
- HTTP header: `Authorization: Bearer <token>`.

The model used by the agent must support tool calling.


## Test it manually

Use the token created above.

1. Discover server capabilities

```bash
curl -v -X POST https://neucore.domain.tld/api/app/v1/mcp \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -H "MCP-Protocol-Version: 2026-07-28" \
  -H "Mcp-Method: server/discover" \
  -d '{"jsonrpc":"2.0","id":1,"method":"server/discover","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{}}}}'
```

2. List tools

```bash
curl -v -X POST https://neucore.domain.tld/api/app/v1/mcp \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -H "MCP-Protocol-Version: 2026-07-28" \
  -H "Mcp-Method: tools/list" \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{}}}}'
```

3. Tool call - esi_request, get wallet for character 96061222

```bash
curl -v -X POST https://neucore.domain.tld/api/app/v1/mcp \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -H "MCP-Protocol-Version: 2026-07-28" \
  -H "Mcp-Method: tools/call" \
  -H "Mcp-Name: esi_request" \
  -d '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"esi_request","arguments":{"characterId":96061222, "path":"/characters/96061222/wallet"},"_meta":{"io.modelcontextprotocol/protocolVersion":"2026-07-28","io.modelcontextprotocol/clientCapabilities":{}}}}'
```
