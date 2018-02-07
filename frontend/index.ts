
import Vue from 'vue';
import Vuex from 'vuex';
import IndexComponent from './components/Index.vue';
import VueMaterial from 'vue-material';
import { UserApi, User } from './api';

// const userApi = new UserApi({});

Vue.use(Vuex);
Vue.use(VueMaterial);

interface RootState {
	user: User | undefined;
}

const store = new Vuex.Store<RootState>({
	state: {
		// defaults
		user: undefined,
	},
	mutations: {
		login(state, user: User) {
			state.user = user;
		}
	}
});


(window as any).instance = new Vue({
	el: '#content',
	template: `
		<index-component/>
	`,
	data: { name: 'Brave Vue' },
	components: {
		IndexComponent,
	},
	async created() {
		this.getUser();

	},
	store,
	methods: {
		async getUser() {
			const apiKey = ''; // TODO api stuff
			if (!apiKey) {
				return;
			}

			const userApi = new UserApi((url, init) => fetch(url, { ...init, headers: { ...init.headers, Authorization: `Bearer: ${apiKey}` } }));
			const user = await userApi.userInfoGet();
			this.$store.commit('login', user);
		}
	}
});