import axios from 'axios';

interface AuthLoginOpts {
	redirect?: string;
}

export async function userAuthLoginGet(params?: AuthLoginOpts): Promise<string> {
	const resp = await axios.get<string>('/api/user/auth/login-url', { params });
	return resp.data;
}

export async function userInfoGet(): Promise<User> {
	const resp = await axios.get<User>('/api/user/player/show');
	return resp.data;
}


export async function userLogout(): Promise<void> {
	await axios.post<User>('/api/user/auth/logout');
}
