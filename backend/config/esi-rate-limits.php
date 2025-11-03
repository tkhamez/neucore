<?php
return array (
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
  '/corporations/[0-9]+/fw/stats' => 
  array (
    'get' => 
    array (
      'group' => 'factional-warfare',
      'maxTokens' => 150,
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
);