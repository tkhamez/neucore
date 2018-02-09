
import Vue from 'vue';
import Vuex from 'vuex';
import IndexComponent from './components/Index.vue';
import VueMaterial from 'vue-material';
import { userInfoGet } from './api';

Vue.use(Vuex);
Vue.use(VueMaterial);

interface RootState {
	user: User | undefined;
	allUsers: User[];
}

const state: RootState = {
	user: undefined,
	allUsers: []
};
// */
/*

// mock state
const state: RootState = {
	user: {
		characterId: 1234,
		name: 'foobar',
		groups: ['fc', 'cap']
	},
	allUsers: [
		{
			characterId: 12,
			name: 'foobar1',
			groups: []
		},
		{
			characterId: 128,
			name: 'foobar2',
			groups: ['fc']
		},
		{
			characterId: 126,
			name: 'foobar45',
			groups: ['cap']
		},
		{
			characterId: 125,
			name: 'foobar3',
			groups: ['fc']
		},
	]
};
*/

const store = new Vuex.Store<RootState>({
	state,
	mutations: {
		login(state: RootState, user: User) {
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
			const user = await userInfoGet();
			this.$store.commit('login', user);
		}
	}
});