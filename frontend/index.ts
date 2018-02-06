
import Vue from 'vue';
import HelloComponent from './components/Hello.vue';
import 'vue-material/dist/vue-material.min.css';

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