
import Vue from 'vue';
import IndexComponent from './components/Index.vue';
import VueMaterial from 'vue-material';

Vue.use(VueMaterial);
new Vue({
	el: '#content',
	template: `
	<div>
		<index-component/>
	</div>
	`,
	data: { name: 'Brave Vue' },
	components: {
		IndexComponent,
	}
});