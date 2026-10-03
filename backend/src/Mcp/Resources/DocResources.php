<?php

declare(strict_types=1);

namespace Neucore\Mcp\Resources;

use Mcp\Capability\Attribute\McpResource;
use Neucore\Middleware\Psr15\RateLimit;
use Neucore\Service\EsiClient;

class DocResources
{
    public const TITLE_GLOBAL_RATE_LIMIT = 'Global Rate Limits';

    public const DESC_GLOBAL_RATE_LIMIT = 'Returns Neucore API rate limit header documentation '
        . '(X-Neucore-Rate-Limit-Remain, X-Neucore-Rate-Limit-Reset).';

    public const TITLE_ESI_RATE_LIMITS = 'ESI Rate Limits';

    public const DESC_ESI_RATE_LIMITS = 'Returns ESI rate limit documentation (new floating window + '
        . 'legacy error limits, token costs, advice).';

    public const TITLE_ESI_PAGINATION = 'ESI Pagination';

    public const DESC_ESI_PAGINATION = 'Returns ESI pagination documentation (offset-based and '
        . 'cursor-based pagination).';

    public const TITLE_ESI_ASSETS = 'ESI Asset List — build & parse guide';

    public const DESC_ESI_ASSETS = 'How to fetch, parse and classify /characters/{id}/assets: chain-walk '
        . 'algorithm, location_flag decoding, ship classification, terminal structure/station resolution.';

    /**
     * @return array<string, mixed>
     */
    #[McpResource(
        uri: 'neucore://doc/global-rate-limits',
        title: self::TITLE_GLOBAL_RATE_LIMIT,
        description: self::DESC_GLOBAL_RATE_LIMIT,
    )]
    public function globalRateLimits(): array
    {
        return [
            'headers' => [RateLimit::HEADER_REMAIN, RateLimit::HEADER_RESET],
            'location' => "Included in the 'headers' field of the tool response.",
            'description' => 'Neucore API rate limit. ' . RateLimit::HEADER_REMAIN
                . ' shows remaining requests in the current window. ' . RateLimit::HEADER_RESET
                . ' is a countdown (in seconds) until the window resets.',
            'calculation' => 'Remaining requests = ' . RateLimit::HEADER_REMAIN . '. Window reset = wait until '
                . RateLimit::HEADER_RESET . ' reaches 0.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[McpResource(
        uri: 'neucore://doc/esi-rate-limits',
        title: self::TITLE_ESI_RATE_LIMITS,
        description: self::DESC_ESI_RATE_LIMITS,
    )]
    public function esiRateLimits(): array
    {
        return [
            'new_rate_limits' => [
                'headers' => [
                    EsiClient::HEADER_RATE_LIMIT_GROUP,
                    EsiClient::HEADER_RATE_LIMIT_LIMIT,
                    EsiClient::HEADER_RATE_LIMIT_REMAINING,
                    EsiClient::HEADER_RATE_LIMIT_USED,
                ],
                'location' => "Included in the 'headers' field of the tool response.",
                'description' => 'ESI floating window rate limit. Tokens are consumed per request '
                    . 'based on response status (2xx=2, 3xx=1, 4xx=5, 5xx=0). Tokens are released '
                    . 'back to the bucket after the window size has passed.',
                'buckets' => 'Each combination of rate limit group + userID has its own bucket. '
                    . 'Authenticated routes use <applicationID>:<characterID> from the Access Token. '
                    . 'Unauthenticated routes use <sourceIP>.',
                'token_cost' => ['2xx' => 2, '3xx' => 1, '4xx' => 5, '5xx' => 0],
                'limit_header' => [
                    'format' => 'TOTAL_TOKENS/WINDOW_SIZE',
                    'example' => '150/15m',
                    'note' => 'm=minutes, h=hours. This is the maximum tokens allowed per window, '
                        . 'not a simple counter.',
                ],
                'monitoring' => 'Monitor ' . EsiClient::HEADER_RATE_LIMIT_REMAINING
                    . ' for each group. If it approaches 0, slow down.',
                'on_429' => 'Use the ' . EsiClient::HEADER_RETRY_AFTER
                    . ' header (in seconds) to determine how long to wait before retrying. '
                    . 'Do not calculate from Limit and Remaining.',
            ],
            'legacy_rate_limits' => [
                'headers' => [EsiClient::HEADER_ERROR_LIMIT_REMAIN, EsiClient::HEADER_ERROR_LIMIT_RESET],
                'location' => "Included in the 'headers' field of the tool response.",
                'description' => 'Legacy error rate limit.',
                'limit' => '100 non-2xx/3xx responses per 60-second window',
                'status_code' => 420,
                'calculation' => 'Remaining error quota = ' . EsiClient::HEADER_ERROR_LIMIT_REMAIN
                    . ' . Window reset = wait until ' . EsiClient::HEADER_ERROR_LIMIT_RESET . ' reaches 0.',
                'advice' => 'Minimize 4xx/5xx responses to stay under this limit. If blocked (HTTP 420), wait for '
                    . EsiClient::HEADER_ERROR_LIMIT_RESET . ' to reach 0 before retrying.',
            ],
            'advice' => [
                'general' => "Don't operate at the limit. Spread requests over time rather than bursting. "
                    . "Avoid */5 cronjobs; use staggered scheduling (e.g. 5 minutes after the last job finished).",
                'esi_429' => 'Wait for the number of seconds in the ' . EsiClient::HEADER_RETRY_AFTER
                    . ' header before retrying. Some internal EVE server routes may also return 429 '
                    . 'without rate limit headers — this is being deprecated.',
                'neucore_429' => 'Wait for the number of seconds in ' . RateLimit::HEADER_RESET
                    . ' before retrying.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[McpResource(
        uri: 'neucore://doc/esi-pagination',
        title: self::TITLE_ESI_PAGINATION,
        description: self::DESC_ESI_PAGINATION,
    )]
    public function esiPagination(): array
    {
        return [
            "location" => "Pagination headers are included in the 'headers' field of the tool response.",
            "offset_based" => [
                "description" => "Legacy pagination style used by many standard ESI endpoints.",
                "query_parameter" => "page=N (1-indexed integer)",
                "response_headers" => ["X-Pages (total number of pages)"],
                "page_size" => "Maximum page size varies by endpoint (commonly 5, 10, or 2000). "
                    . "Check the endpoint's OpenAPI spec.",
                "how_to_use" => "Increment the page parameter starting from 1. "
                    . "Continue until the requested page number equals the value in X-Pages.",
            ],
            "token_based" => [
                "description" => "Cursor-based pagination for modified-list endpoints (e.g., "
                    . "/v2/corporations/{id}/projects/). Returns objects based on their last modification time.",
                "response_headers" => ["before (cursor for previous page)", "after (cursor for next page)"],
                "query_parameters" => [
                    "forward" => "?after=<token>",
                    "backward" => "?before=<token>",
                ],
                "end_condition" => "An empty response array [] indicates you have reached the end of the dataset.",
                "duplicate_behavior" => "You may see the same record multiple times. "
                    . "Results are sorted by last-modified time; if a record is updated,"
                    . " it moves in the sort order and can appear again in subsequent pages.",
                "best_practice" => "To track changes over time, keep replaying the last 'after' "
                    . "token until the API returns non-empty results. This ensures you capture all updates.",
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[McpResource(
        uri: 'neucore://doc/esi-assets',
        title: self::TITLE_ESI_ASSETS,
        description: self::DESC_ESI_ASSETS,
    )]
    public function esiAssets(): array
    {
        return [
            'endpoint' => [
                'path' => 'GET /characters/{character_id}/assets',
                'auth' => 'character token required (scope esi-assets.read_all.v1); via Neucore MCP '
                    . 'esi_request with characterId = the character named in the path',
                'pagination' => 'offset-based; follow X-Pages to completion (mechanics: '
                    . 'neucore://doc/esi-pagination) — asset lists frequently exceed one page',
                'note' => 'large responses are frequently offloaded to a file by the client — parse '
                    . 'the file, not a truncated in-memory copy',
            ],
            'response_schema' => [
                'type' => 'list of asset entries',
                'fields' => [
                    'item_id' => 'unique item ID — the only safe identifier (ship names are freely '
                        . 'chosen and can be duplicated — never use a name as a key)',
                    'type_id' => 'item type ID (for classification)',
                    'location_id' => 'ID of the containing location: a station, a system, or another item',
                    'location_type' => 'station | solar_system | item | other (enum). Structure contents '
                        . 'ALSO arrive as "item" (there is no "structure" value). "other" occurs: the '
                        . 'Asset Safety special location 2004.',
                    'location_flag' => 'human-readable flag string (Hangar, Cargo, DroneBay, Unlocked, '
                        . 'AssetSafety, ...)',
                ],
                'rule' => 'trust location_type / location_flag — never re-derive them from ID magnitude '
                    . '(station IDs are 60M-70M, i.e. 8 digits, not 600M). Authoritative cross-check: '
                    . 'https://github.com/esi/eve-glue/tree/master/eve_glue resolves location_type '
                    . 'BY ID RANGE (30000000-39999999=solar_system, 60000000-63999999=station, '
                    . '>=100000000=item, else other) — that is why structure-docked content arrives '
                    . 'as "item" (structure IDs share the item range) and 2004 as "other"; use it only '
                    . 'for UNtyped terminal IDs. Flag IDs: location_flag.py (AssetSafety=36, '
                    . 'ShipHangar=90, HangarAll=1000, AutoFit=0).',
            ],
            'build_algorithm' => [
                'step_1_fetch_all_pages' => 'Fetch every page (X-Pages); build an item_id -> entry map.',
                'step_2_chain_walk' => 'See chain_walk below.',
                'step_3_classify_ships' => 'See classification below.',
                'step_4_contextualize_final_locations' => 'See final_locations below.',
            ],
            'chain_walk' => [
                'rule' => "while an entry's location_type == 'item', replace it with the entry at its "
                    . "location_id (via the item_id map)",
                'safeguards' => [
                    'visited-set (break cycles)',
                    'depth cap ~12',
                ],
                'termination' => 'terminates at a location_type of station or system',
                'terminal_location_id_not_in_item_map' => [
                    'explanation' => 'the container is not an asset — it is the current ship or a structure '
                        . '(a station is never the terminal of an item-parent chain)',
                    'identify_in_this_order' => [
                        '0. Did the walk end ON an item with location_flag "AssetSafety"? It is an Asset '
                        . 'Safety Wrap (see asset_safety): station_form -> resolve the station for context; '
                        . 'other_form (2004) -> stop, never resolve 2004',
                        '1. Are all pages fetched? (re-check X-Pages — a missing page explains a "missing" parent)',
                        "2. Is it the current ship? compare with GET /characters/{id}/ship's ship_item_id",
                        '3. Structure: GET /universe/structures/{id} WITH the character token (auth — a '
                        . 'tokenless/public request returns 401, not 404): 200 = name + owner_id + system_id; '
                        . '403 = the character lost access (stranded assets — items remain in the list but are '
                        . 'unreachable); 404 = structure deleted',
                        "4. Station: GET /universe/stations/{id} -> 200 = name + owner; NPC stations are "
                        . "open to all",
                    ],
                ],
            ],
            'location_flag_decoding' => [
                'ship_fit' => 'HiSlot* / MedSlot* / LoSlot* / RigSlot* = fitted ship modules (parent is a ship)',
                'drones' => 'DroneBay = fitted drones',
                'cargo' => 'Cargo = ship cargo — including REPACKED SHIPS when the parent is a freighter '
                    . '(freighters can only carry repacked ships; the entry is still a ship item and counts as a ship)',
                'hangar_station' => 'Hangar + location_type station = in a station hangar',
                'hangar_item' => 'Hangar + location_type item = inside a container item or a structure: an '
                    . 'assembled ship in a capital bay (carriers / supercarriers / titans carry fitted ships in '
                    . 'bays — the parent is itself a ship, both count) or ships docked in a structure (the parent '
                    . 'may not be in the item map — resolve via the chain-walk terminal algorithm)',
                'station_containers' => [
                    'Unlocked' => 'inside a station container (parent type 17366 Station Container / 17367 '
                        . 'Station Vault Container; the container itself sits in the station hangar)',
                    'AssetSafety' => 'see the dedicated asset_safety section — this flag marks the Asset Safety '
                        . 'Wrap CONTAINER items themselves (type 60), not ordinary cargo',
                    'trap' => 'a large item holding many modules/blueprints is usually a station container, not '
                        . 'a ship — resolve the parent type_id (POST /universe/names) before classifying',
                ],
                'other_flags' => [
                    'Specialized*Hold / Specialized*Bay' => 'industrial-ship cargo holds (repacked ships / ore)',
                    'FighterBay / FighterTube*' => 'fighter bays',
                    'StructureDeedBay' => 'structure deeds (ownership items)',
                    'CapsuleerDeliveries / Deliveries / Locked' => 'other container states',
                    'FleetHangar / ShipHangar / HangarAll' => 'structure hangar variants',
                ],
                'authoritative_list' => 'the authoritative source is the ESI OpenAPI spec (schema '
                    . 'CharactersCharacterIdAssetsGet, property location_flag). Dump the spec instead of '
                    . 'memorizing.',
            ],
            'asset_safety' => [
                'what' => 'When a structure is destroyed, its contents are moved into an "Asset Safety Wrap" '
                    . 'container. The wrap is a REAL item in the asset list: type_id 60, name '
                    . '"Asset Safety Wrap", always with location_flag "AssetSafety". Each wrap = one lost '
                    . 'structure — count wraps as loss history. A wrap still present at vetting time was '
                    . 'never claimed.',
                'forms' => [
                    'station_form' => 'location_type "station" + location_id = a station id: the wrap is '
                        . 'tied to that station (resolve /universe/stations/{id} for context; several wraps '
                        . 'can sit at one station).',
                    'other_form' => 'location_type "other" + location_id 2004: a special asset-safety '
                        . 'location. 2004 is NOT a station (/universe/stations/2004 -> 404). NEVER send 2004 '
                        . 'to POST /universe/names either — that ID collides with an unrelated inventory type '
                        . 'id (resolves to "ECCM - Gravimetric I") and produces garbage labels.',
                ],
                'children' => 'Items inside a wrap keep their ORIGINAL location_flag (Hangar, Deliveries, ...) '
                    . 'and point at the wrap item_id. The chain walk ends AT the wrap (it is in the item map); '
                    . 'classify by the wrap, not by the child flag.',
            ],
            'classification' => [
                'nesting_rule' => 'classify ship types across ALL entries, not just top-level ones — a Titan '
                    . 'in a hangar with full bays can carry an entire fitted fleet',
                'recipe' => 'unique type_id -> GET /universe/types/{id} (name + group_id) -> '
                    . 'GET /universe/groups/{id} (group name)',
            ],
            'final_locations' => [
                'system' => 'ship in flight — where the player actually operates',
                'structure' => 'terminal structure_id -> GET /universe/structures/{id} (auth) -> owner_id -> '
                    . 'resolve owner corp. A 403 is information, not an error: the character can no longer see '
                    . 'that structure (removed / kicked / changed corps) and the asset is stranded there — '
                    . 'identify the structure from the character notifications (structure join/leave/ACL texts) '
                    . 'or the zKillboard structure page, then use the last known owning corp',
                'station' => 'name only (station constellation = where the fleet is kept)',
            ],
            'related_endpoints' => [
                'current_ship' => 'GET /characters/{id}/ship -> ship_item_id + ship_type_id (sanity '
                    . 'anchor: the current ship must appear in the asset list)',
                'asset_names' => 'POST /characters/{id}/assets/names — body: 1-1000 unique item_ids -> '
                    . '[{item_id, name}] (named singletons)',
                'asset_locations' => 'POST /characters/{id}/assets/locations — body: item_ids -> '
                    . '[{item_id, position{x,y,z}}] (in-space items / exact slot inside containers)',
                'universe_structures' => 'GET /universe/structures/{id} — AUTH '
                    . '(scope esi-universe.read_structures.v1; tokenless request -> 401) — 200 object: '
                    . 'name, owner_id, position, solar_system_id, type_id (NOTE: solar_system_id, NOT '
                    . 'system_id). 403 body {"error":"Forbidden"} = character lost access; '
                    . '404 = deleted/unknown. Save the raw body to raw/authed-{char}-structure-{id}.json',
                'universe_stations' => 'GET /universe/stations/{id} — object: station_id, name, '
                    . 'owner, type_id',
            ],
        ];
    }
}
