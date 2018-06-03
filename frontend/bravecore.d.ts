
type UserRole = 'anonymous' | 'user' | 'user-manager' | 'group-admin' | 'group-manager' | 'app-manager'

// keep this in sync with swagger definition
interface User {
	roles: string[];
	name: string;
	characterId: number;
	groups: string[];
}

// totally made up for frontend UI mocks
interface Group {
	name: string;
	admins: number[];
	managers: number[];
	members: number[];
}
