
<template>
    <div v-cloak class="modal" id="copyText">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Copy Text</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <textarea class="form-control" rows="10" v-model="text"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import {Modal} from "bootstrap";
import Helper from "../classes/Helper";

export default {
    data() {
        return {
            h: new Helper(this),
            text: '',
            modal: null,
        }
    },

    methods: {

        /**
         * @param {string} text
         */
        exec(text) {
            this.text = text;

            const openModal = () => {
                if (!this.modal) {
                    this.modal = new Modal('#copyText');
                }
                this.modal.show();
            };

            if (navigator.clipboard) { // Needs secure context (HTTPS or localhost).
                navigator.clipboard.writeText(this.text).then(
                    () => { // success
                        this.h.message('Successfully copied character list to clipboard.', 'success', 2500);
                    },
                    () => { // fail
                        openModal();
                    }
                );
            } else {
                openModal();
            }
        },
    }
}
</script>
