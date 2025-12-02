import Vue from 'vue'
import App from './App.vue'
import { generateFilePath } from '@nextcloud/router'
import { getRequestToken } from '@nextcloud/auth'

// eslint-disable-next-line
__webpack_nonce__ = btoa(getRequestToken())
// eslint-disable-next-line
__webpack_public_path__ = generateFilePath('docuseal_integration', '', 'js/')

Vue.mixin({ methods: { t, n } })

export default new Vue({
    el: '#content',
    render: h => h(App),
})
