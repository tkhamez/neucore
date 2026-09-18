# MCP Server

The MCP server provides tools that allow AI assistants to query player and corporation member
tracking data and perform authenticated EVE API (ESI) requests for each character.

The server supports only the modern protocol version 2026-07-28.


## Tools Overview

| Tool                   | Purpose                                                               |
|------------------------|-----------------------------------------------------------------------|
| `find_players`         | Search players by name (min. 3 characters)                            |
| `find_characters`      | Search characters by name (min. 3 characters)                         |
| `get_player`           | Get a player with their characters, corporation, alliance, and groups |
| `get_character`        | Get a single character with their corporation and alliance            |
| `get_corporation`      | Get a corporation with its alliance                                   |
| `get_alliance`         | Get an alliance by ID                                                 |
| `get_groups`           | Get groups a player belongs to                                        |
| `get_group_members`    | Get all players that belong to a group                                |
| `get_service_accounts` | Get all service accounts from active plugins for a player             |
| `get_member_tracking`  | Get corporation member tracking data, with filters                    |
| `esi_request`          | Make authenticated ESI API requests using a character's ESI token.    |

Filters for `get_member_tracking`: active days, inactive days, token status, token status changed date, 
missing character mail count, belongs to a player account


## Usage

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


### Test it manually

Use the token created above.

1. Discover server capabilities

```bash
curl -v -X POST https://neucore.domain.tld/api/app/v1/mcp \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -H "MCP-Protocol-Version: 2026-07-28" \
  -H "Mcp-Method: server/discover" \
  -d '{
    "jsonrpc":"2.0",
    "id":1,
    "method":"server/discover",
    "params":{
      "_meta":{
        "io.modelcontextprotocol/protocolVersion":"2026-07-28",
        "io.modelcontextprotocol/clientCapabilities":{}
      }
    }
  }'
```

2. List tools

```bash
curl -v -X POST https://neucore.domain.tld/api/app/v1/mcp \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -H "MCP-Protocol-Version: 2026-07-28" \
  -H "Mcp-Method: tools/list" \
  -d '{
    "jsonrpc":"2.0",
    "id":2,
    "method":"tools/list",
    "params":{
      "_meta":{
        "io.modelcontextprotocol/protocolVersion":"2026-07-28",
        "io.modelcontextprotocol/clientCapabilities":{}
      }
    }
  }'
```

3. Tool call - esi_request, get wallet for character 96061222

```bash
curl -v -X POST https://neucore.domain.tld/api/app/v1/mcp \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -H "MCP-Protocol-Version: 2026-07-28" \
  -H "Mcp-Method: tools/call" \
  -H "Mcp-Name: esi_request" \
  -d '{
    "jsonrpc":"2.0",
    "id":3,
    "method":"tools/call",
    "params":{
      "name":"esi_request",
      "arguments":{
        "characterId":96061222,
        "path":"/characters/96061222/wallet"
      },
      "_meta":{
        "io.modelcontextprotocol/protocolVersion":"2026-07-28",
        "io.modelcontextprotocol/clientCapabilities":{}
      }
    }
  }'
```
