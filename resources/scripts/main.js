import '../css/invoiceshelf.css'
import 'v-tooltip/dist/v-tooltip.css'
import '@/scripts/http'
import * as VueRouter from 'vue-router'
import router from '@/scripts/router/index'
import * as pinia from 'pinia'
import * as Vue from 'vue'
import * as Vuelidate from '@vuelidate/core'

window.pinia = pinia
window.Vuelidate = Vuelidate
import InvoiceShelf from './InvoiceShelf.js'

window.Vue = Vue
window.router = router
window.VueRouter = VueRouter

window.InvoiceShelf = new InvoiceShelf()