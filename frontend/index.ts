
import Vue from 'vue';
import HelloComponent from './components/Hello.vue';
import UserComponent from './components/User.vue';

new Vue({
	el: '#content',
	template: `
	<div>
		Name: <input v-model="name" type="text">
		<hello-component :name="name" :initialEnthusiasm="5" />
		<user-component/>
	</div>
	`,
	data: { name: 'Brave Vue' },
	components: {
		HelloComponent,
		UserComponent
	}
});