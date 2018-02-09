import axios from 'axios';

interface AuthLoginOpts {
	redirect_url?: string;
}

interface AuthLoginResp {
	oauth_url: string;
}
export async function userAuthLoginGet(params?: AuthLoginOpts): Promise<string> {
	const resp = await axios.get<AuthLoginResp>('/api/user/auth/login', { params });
	return resp.data.oauth_url;
}

export async function userInfoGet(): Promise<User> {
	const resp = await axios.get<User>('/api/user/info', );
	return resp.data;
}