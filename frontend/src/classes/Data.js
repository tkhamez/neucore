
export default class Data {

    static themes = [
        'Basic',
        'Cerulean',
        'Cosmo',
        'Cyborg',
        'Darkly',
        'Flatly',
        'Journal',
        'Litera',
        'Lumen',
        'Lux',
        'Materia',
        'Minty',
        'Pulse',
        'Sandstone',
        'Simplex',
        'Sketchy',
        'Slate',
        'Solar',
        'Spacelab',
        'Superhero',
        'United',
        'Yeti',
    ];

    static messages = {
        errorRequiredForbiddenGroup:
            'This player is not a member of any of the required groups or a member' +
            ' of one of the forbidden groups.',
        errorRoleRequiredGroup: 'This player is not a member of a group required for this role.',
        itemNameAllowedCharsHelp: 'Allowed characters (no spaces): A-Z a-z 0-9 - . _',
    };

    static loginNames = {
        default:    'core.default',
        managed:    'core.managed',
        mail:       'core.mail',
        director:   'core.director',
    };

    static userRoles = [
        'app-admin',
        'app-manager',
        'esi',
        'group-admin',
        'group-manager',
        'settings',
        'service-admin',
        'statistics',
        'tracking',
        'tracking-admin',
        'user-admin',
        'user-chars',
        'user-manager',
        'watchlist',
        'watchlist-admin',
        'watchlist-manager',
    ];
}
