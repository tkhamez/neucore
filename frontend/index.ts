
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
	groups: Group[];
}

/*
const state: RootState = {
	user: undefined,
	allUsers: [],
	groups: []
};
// */
// mock state
const groupNames = ['fc', 'cap', 'dojo'];
const allUsers = [
	{
		characterId: 12,
		roles: ['app-manager', 'group-manager', 'group-admin', 'user'],
		name: 'admin dood',
		groups: ['fc', 'cap', 'dojo']
	},
	{
		characterId: 128,
		roles: ['user-manager', 'user'],
		name: 'manager guy',
		groups: ['fc', 'cap', 'dojo']
	},
	{
		characterId: 45,
		roles: ['user'],
		name: 'foobar45',
		groups: ['dojo']
	},
	{
		characterId: 50,
		roles: ['user'],
		name: 'foobar50',
		groups: ['cap']
	},
	{
		characterId: 125,
		roles: ['user'],
		name: 'foobar125',
		groups: ['fc']
	},
	{
		characterId: 126,
		roles: ['user'],
		name: 'foobar126',
		groups: ['cap']
	},
	{
		characterId: 127,
		roles: ['user'],
		name: 'foobar127',
		groups: []
	},
	{
		characterId: 128,
		roles: ['user'],
		name: 'foobar128',
		groups: []
	},
	{
		characterId: 129,
		roles: ['anonymous'],
		name: 'panda',
		groups: []
	},
];
const groups: Group[] = groupNames.map((name) => {
	return {
		name,
		admins: [allUsers[0].characterId],
		managers: [allUsers[1].characterId],
		members: allUsers.filter((user) => user.groups.includes(name)).map((user) => user.characterId),
	};
});

const state: RootState = {
	user: allUsers[0],
	allUsers,
	groups,
};
// */

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