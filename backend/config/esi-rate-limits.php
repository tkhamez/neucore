<?php
return array (
  '/alliances/[0-9]+/contacts' => 
  array (
    'get' => 
    array (
      'group' => 'alliance-social',
      'maxTokens' => 300,
      'windowSize' => '15m',
    ),
  ),
  '/alliances/[0-9]+/contacts/labels' => 
  array (
    'get' => 
    array (
      'group' => 'alliance-social',
      'maxTokens' => 300,
      'windowSize' => '15m',
    ),
  ),
  '/characters/[0-9]+/clones' => 
  array (
    'get' => 
    array (
      'group' => 'char-location',
      'maxTokens' => 1200,
      'windowSize' => '15m',
    ),
  ),
  '/characters/[0-9]+/fatigue' => 
  array (
    'get' => 
    array (
      'group' => 'char-location',
      'maxTokens' => 1200,
      'windowSize' => '15m',
    ),
  ),
  '/characters/[0-9]+/fittings' => 
  array (
    'get' => 
    array (
      'group' => 'fitting',
      'maxTokens' => 150,
      'windowSize' => '15m',
    ),
    'post' => 
    array (
      'group' => 'fitting',
      'maxTokens' => 150,
      'windowSize' => '15m',
    ),
  ),
  '/characters/[0-9]+/fittings/[0-9]+' => 
  array (
    'delete' => 
    array (
      'group' => 'fitting',
      'maxTokens' => 150,
      'windowSize' => '15m',
    ),
  ),
  '/characters/[0-9]+/fleet' => 
  array (
    'get' => 
    array (
      'group' => 'fleet',
      'maxTokens' => 1800,
      'windowSize' => '15m',
    ),
  ),
  '/characters/[0-9]+/fw/stats' => 
  array (
    'get' => 
    array (
      'group' => 'factional-warfare',
      'maxTokens' => 150,
      'windowSize' => '15m',
    ),
  ),
  '/characters/[0-9]+/killmails/recent' => 
  array (
    'get' => 
    array (
      'group' => 'char-killmail',
      'maxTokens' => 30,
      'windowSize' => '15m',
    ),
  ),
  '/characters/[0-9]+/location' => 
  array (
    'get' => 
    array (
      'group' => 'char-location',
      'maxTokens' => 1200,
      'windowSize' => '15m',
    ),
  ),
  '/characters/[0-9]+/notifications' => 
  array (
    'get' => 
    array (
      'group' => 'char-notification',
      'maxTokens' => 15,
      'windowSize' => '15m',
    ),
  ),
  '/characters/[0-9]+/online' => 
  array (
    'get' => 
    array (
      'group' => 'char-location',
      'maxTokens' => 1200,
      'windowSize' => '15m',
    ),
  ),
  '/characters/[0-9]+/ship' => 
  array (
    'get' => 
    array (
      'group' => 'char-location',
      'maxTokens' => 1200,
      'windowSize' => '15m',
    ),
  ),
  '/corporation/[0-9]+/mining/extractions' => 
  array (
    'get' => 
    array (
      'group' => 'corp-industry',
      'maxTokens' => 600,
      'windowSize' => '15m',
    ),
  ),
  '/corporation/[0-9]+/mining/observers' => 
  array (
    'get' => 
    array (
      'group' => 'corp-industry',
      'maxTokens' => 600,
      'windowSize' => '15m',
    ),
  ),
  '/corporation/[0-9]+/mining/observers/{observer_id}' => 
  array (
    'get' => 
    array (
      'group' => 'corp-industry',
      'maxTokens' => 600,
      'windowSize' => '15m',
    ),
  ),
  '/corporations/[0-9]+/blueprints' => 
  array (
    'get' => 
    array (
      'group' => 'corp-industry',
      'maxTokens' => 600,
      'windowSize' => '15m',
    ),
  ),
  '/corporations/[0-9]+/contacts' => 
  array (
    'get' => 
    array (
      'group' => 'corp-social',
      'maxTokens' => 300,
      'windowSize' => '15m',
    ),
  ),
  '/corporations/[0-9]+/contacts/labels' => 
  array (
    'get' => 
    array (
      'group' => 'corp-social',
      'maxTokens' => 300,
      'windowSize' => '15m',
    ),
  ),
  '/corporations/[0-9]+/contracts' => 
  array (
    'get' => 
    array (
      'group' => 'corp-contract',
      'maxTokens' => 600,
      'windowSize' => '15m',
    ),
  ),
  '/corporations/[0-9]+/contracts/[0-9]+/bids' => 
  array (
    'get' => 
    array (
      'group' => 'corp-contract',
      'maxTokens' => 600,
      'windowSize' => '15m',
    ),
  ),
  '/corporations/[0-9]+/contracts/[0-9]+/items' => 
  array (
    'get' => 
    array (
      'group' => 'corp-contract',
      'maxTokens' => 600,
      'windowSize' => '15m',
    ),
  ),
  '/corporations/[0-9]+/customs_offices' => 
  array (
    'get' => 
    array (
      'group' => 'corp-industry',
      'maxTokens' => 600,
      'windowSize' => '15m',
    ),
  ),
  '/corporations/[0-9]+/divisions' => 
  array (
    'get' => 
    array (
      'group' => 'corp-wallet',
      'maxTokens' => 300,
      'windowSize' => '15m',
    ),
  ),
  '/corporations/[0-9]+/fw/stats' => 
  array (
    'get' => 
    array (
      'group' => 'factional-warfare',
      'maxTokens' => 150,
      'windowSize' => '15m',
    ),
  ),
  '/corporations/[0-9]+/industry/jobs' => 
  array (
    'get' => 
    array (
      'group' => 'corp-industry',
      'maxTokens' => 600,
      'windowSize' => '15m',
    ),
  ),
  '/corporations/[0-9]+/killmails/recent' => 
  array (
    'get' => 
    array (
      'group' => 'corp-killmail',
      'maxTokens' => 30,
      'windowSize' => '15m',
    ),
  ),
  '/corporations/[0-9]+/medals' => 
  array (
    'get' => 
    array (
      'group' => 'corp-detail',
      'maxTokens' => 300,
      'windowSize' => '15m',
    ),
  ),
  '/corporations/[0-9]+/medals/issued' => 
  array (
    'get' => 
    array (
      'group' => 'corp-detail',
      'maxTokens' => 300,
      'windowSize' => '15m',
    ),
  ),
  '/corporations/[0-9]+/members' => 
  array (
    'get' => 
    array (
      'group' => 'corp-member',
      'maxTokens' => 300,
      'windowSize' => '15m',
    ),
  ),
  '/corporations/[0-9]+/members/limit' => 
  array (
    'get' => 
    array (
      'group' => 'corp-member',
      'maxTokens' => 300,
      'windowSize' => '15m',
    ),
  ),
  '/corporations/[0-9]+/members/titles' => 
  array (
    'get' => 
    array (
      'group' => 'corp-member',
      'maxTokens' => 300,
      'windowSize' => '15m',
    ),
  ),
  '/corporations/[0-9]+/membertracking' => 
  array (
    'get' => 
    array (
      'group' => 'corp-member',
      'maxTokens' => 300,
      'windowSize' => '15m',
    ),
  ),
  '/corporations/[0-9]+/roles' => 
  array (
    'get' => 
    array (
      'group' => 'corp-member',
      'maxTokens' => 300,
      'windowSize' => '15m',
    ),
  ),
  '/corporations/[0-9]+/roles/history' => 
  array (
    'get' => 
    array (
      'group' => 'corp-member',
      'maxTokens' => 300,
      'windowSize' => '15m',
    ),
  ),
  '/corporations/[0-9]+/shareholders' => 
  array (
    'get' => 
    array (
      'group' => 'corp-detail',
      'maxTokens' => 300,
      'windowSize' => '15m',
    ),
  ),
  '/corporations/[0-9]+/standings' => 
  array (
    'get' => 
    array (
      'group' => 'corp-member',
      'maxTokens' => 300,
      'windowSize' => '15m',
    ),
  ),
  '/corporations/[0-9]+/titles' => 
  array (
    'get' => 
    array (
      'group' => 'corp-detail',
      'maxTokens' => 300,
      'windowSize' => '15m',
    ),
  ),
  '/corporations/[0-9]+/wallets' => 
  array (
    'get' => 
    array (
      'group' => 'corp-wallet',
      'maxTokens' => 300,
      'windowSize' => '15m',
    ),
  ),
  '/corporations/[0-9]+/wallets/{division}/journal' => 
  array (
    'get' => 
    array (
      'group' => 'corp-wallet',
      'maxTokens' => 300,
      'windowSize' => '15m',
    ),
  ),
  '/corporations/[0-9]+/wallets/{division}/transactions' => 
  array (
    'get' => 
    array (
      'group' => 'corp-wallet',
      'maxTokens' => 300,
      'windowSize' => '15m',
    ),
  ),
  '/fleets/[0-9]+' => 
  array (
    'get' => 
    array (
      'group' => 'fleet',
      'maxTokens' => 1800,
      'windowSize' => '15m',
    ),
    'put' => 
    array (
      'group' => 'fleet',
      'maxTokens' => 1800,
      'windowSize' => '15m',
    ),
  ),
  '/fleets/[0-9]+/members' => 
  array (
    'get' => 
    array (
      'group' => 'fleet',
      'maxTokens' => 1800,
      'windowSize' => '15m',
    ),
    'post' => 
    array (
      'group' => 'fleet',
      'maxTokens' => 1800,
      'windowSize' => '15m',
    ),
  ),
  '/fleets/[0-9]+/members/[0-9]+' => 
  array (
    'delete' => 
    array (
      'group' => 'fleet',
      'maxTokens' => 1800,
      'windowSize' => '15m',
    ),
    'put' => 
    array (
      'group' => 'fleet',
      'maxTokens' => 1800,
      'windowSize' => '15m',
    ),
  ),
  '/fleets/[0-9]+/squads/[0-9]+' => 
  array (
    'delete' => 
    array (
      'group' => 'fleet',
      'maxTokens' => 1800,
      'windowSize' => '15m',
    ),
    'put' => 
    array (
      'group' => 'fleet',
      'maxTokens' => 1800,
      'windowSize' => '15m',
    ),
  ),
  '/fleets/[0-9]+/wings' => 
  array (
    'get' => 
    array (
      'group' => 'fleet',
      'maxTokens' => 1800,
      'windowSize' => '15m',
    ),
    'post' => 
    array (
      'group' => 'fleet',
      'maxTokens' => 1800,
      'windowSize' => '15m',
    ),
  ),
  '/fleets/[0-9]+/wings/[0-9]+' => 
  array (
    'delete' => 
    array (
      'group' => 'fleet',
      'maxTokens' => 1800,
      'windowSize' => '15m',
    ),
    'put' => 
    array (
      'group' => 'fleet',
      'maxTokens' => 1800,
      'windowSize' => '15m',
    ),
  ),
  '/fleets/[0-9]+/wings/[0-9]+/squads' => 
  array (
    'post' => 
    array (
      'group' => 'fleet',
      'maxTokens' => 1800,
      'windowSize' => '15m',
    ),
  ),
  '/fw/leaderboards' => 
  array (
    'get' => 
    array (
      'group' => 'factional-warfare',
      'maxTokens' => 150,
      'windowSize' => '15m',
    ),
  ),
  '/fw/leaderboards/characters' => 
  array (
    'get' => 
    array (
      'group' => 'factional-warfare',
      'maxTokens' => 150,
      'windowSize' => '15m',
    ),
  ),
  '/fw/leaderboards/corporations' => 
  array (
    'get' => 
    array (
      'group' => 'factional-warfare',
      'maxTokens' => 150,
      'windowSize' => '15m',
    ),
  ),
  '/fw/stats' => 
  array (
    'get' => 
    array (
      'group' => 'factional-warfare',
      'maxTokens' => 150,
      'windowSize' => '15m',
    ),
  ),
  '/fw/systems' => 
  array (
    'get' => 
    array (
      'group' => 'factional-warfare',
      'maxTokens' => 150,
      'windowSize' => '15m',
    ),
  ),
  '/fw/wars' => 
  array (
    'get' => 
    array (
      'group' => 'factional-warfare',
      'maxTokens' => 150,
      'windowSize' => '15m',
    ),
  ),
  '/incursions' => 
  array (
    'get' => 
    array (
      'group' => 'incursion',
      'maxTokens' => 150,
      'windowSize' => '15m',
    ),
  ),
  '/industry/facilities' => 
  array (
    'get' => 
    array (
      'group' => 'industry',
      'maxTokens' => 150,
      'windowSize' => '15m',
    ),
  ),
  '/industry/systems' => 
  array (
    'get' => 
    array (
      'group' => 'industry',
      'maxTokens' => 150,
      'windowSize' => '15m',
    ),
  ),
  '/insurance/prices' => 
  array (
    'get' => 
    array (
      'group' => 'insurance',
      'maxTokens' => 150,
      'windowSize' => '15m',
    ),
  ),
  '/killmails/[0-9]+/[0-9a-fA-F]+' => 
  array (
    'get' => 
    array (
      'group' => 'killmail',
      'maxTokens' => 3600,
      'windowSize' => '15m',
    ),
  ),
  '/sovereignty/campaigns' => 
  array (
    'get' => 
    array (
      'group' => 'sovereignty',
      'maxTokens' => 600,
      'windowSize' => '15m',
    ),
  ),
  '/sovereignty/map' => 
  array (
    'get' => 
    array (
      'group' => 'sovereignty',
      'maxTokens' => 600,
      'windowSize' => '15m',
    ),
  ),
  '/sovereignty/structures' => 
  array (
    'get' => 
    array (
      'group' => 'sovereignty',
      'maxTokens' => 600,
      'windowSize' => '15m',
    ),
  ),
  '/status' => 
  array (
    'get' => 
    array (
      'group' => 'status',
      'maxTokens' => 600,
      'windowSize' => '15m',
    ),
  ),
  '/ui/autopilot/waypoint' => 
  array (
    'post' => 
    array (
      'group' => 'ui',
      'maxTokens' => 900,
      'windowSize' => '15m',
    ),
  ),
  '/ui/openwindow/contract' => 
  array (
    'post' => 
    array (
      'group' => 'ui',
      'maxTokens' => 900,
      'windowSize' => '15m',
    ),
  ),
  '/ui/openwindow/information' => 
  array (
    'post' => 
    array (
      'group' => 'ui',
      'maxTokens' => 900,
      'windowSize' => '15m',
    ),
  ),
  '/ui/openwindow/marketdetails' => 
  array (
    'post' => 
    array (
      'group' => 'ui',
      'maxTokens' => 900,
      'windowSize' => '15m',
    ),
  ),
  '/ui/openwindow/newmail' => 
  array (
    'post' => 
    array (
      'group' => 'ui',
      'maxTokens' => 900,
      'windowSize' => '15m',
    ),
  ),
  '/wars' => 
  array (
    'get' => 
    array (
      'group' => 'killmail',
      'maxTokens' => 3600,
      'windowSize' => '15m',
    ),
  ),
  '/wars/[0-9]+' => 
  array (
    'get' => 
    array (
      'group' => 'killmail',
      'maxTokens' => 3600,
      'windowSize' => '15m',
    ),
  ),
  '/wars/[0-9]+/killmails' => 
  array (
    'get' => 
    array (
      'group' => 'killmail',
      'maxTokens' => 3600,
      'windowSize' => '15m',
    ),
  ),
);