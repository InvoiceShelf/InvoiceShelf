import '../sass/invoiceshelf.scss'
import 'v-tooltip/dist/v-tooltip.css'
import '@/scripts/plugins/axios.js'
import * as VueRouter from 'vue-router'
import router from '@/scripts/router/index'
import * as pinia from 'pinia'
import * as Vue from 'vue'
import * as Vuelidate from '@vuelidate/core'

import.meta.glob([
  '../static/img/**',
  '../static/fonts/**',
]);

window.pinia = pinia
window.Vuelidate = Vuelidate
import InvoiceShelf from './InvoiceShelf.js'

window.Vue = Vue
window.router = router
window.VueRouter = VueRouter

window.InvoiceShelf = new InvoiceShelf()