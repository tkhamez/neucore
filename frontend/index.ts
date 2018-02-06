
import Vue from 'vue';
import HelloComponent from './components/Hello.vue';

new Vue({
	el: '#content',
	template: `
	<div>
		Name: <input v-model="name" type="text">
		<hello-component :name="name" :initialEnthusiasm="5" />
	</div>
	`,
	data: { name: 'Brave Vue' },
	components: {
		HelloComponent
	}
});