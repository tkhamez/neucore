import { reactive, readonly } from 'vue';

const state = reactive({

    loadingCount: 0,

});

export default {
	state: readonly(state),

    increaseLoadingCount() {
        state.loadingCount++;
    },
    decreaseLoadingCount() {
        state.loadingCount--;
    },
};
