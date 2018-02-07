
import Vue from 'vue';
import Vuex, { StoreOptions } from 'vuex';
import IndexComponent from './components/Index.vue';
import VueMaterial from 'vue-material';
import { UserApi, User } from './api';
import * as Cookies from 'js-cookie';

// const userApi = new UserApi({});

Vue.use(Vuex);
Vue.use(VueMaterial);

interface RootState {
	user?: User;
}

const store: StoreOptions<RootState> = {
	state: {
		// defaults
	},
};

export default new Vuex.Store<RootState>(store);


new Vue({
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
	methods: {
		async getUser() {
			const apiKey = Cookies.get('apiKey');
			if (!apiKey) {
				return;
			}

			const userApi = new UserApi((url, init) => fetch(url, { ...init, headers: { ...init.headers, Authorization: apiKey } }));
			const user = await userApi.userInfoGet();
			this.$store.commit('user', user);
		}
	}
});