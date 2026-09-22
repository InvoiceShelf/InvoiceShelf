import type { RouteRecordRaw } from 'vue-router'

const AuthLayout = () => import('../../layouts/AuthLayout.vue')
const LoginView = () => import('./views/LoginView.vue')
const ClientLoginView = () => import('./views/ClientLoginView.vue')
const ForgotPasswordView = () => import('./views/ForgotPasswordView.vue')
const ResetPasswordView = () => import('./views/ResetPasswordView.vue')
const RegisterWithInvitationView = () => import('./views/RegisterWithInvitationView.vue')

export const authRoutes: RouteRecordRaw[] = [
  {
    path: '/login',
    component: AuthLayout,
    children: [
      {
        path: '',
        name: 'login',
        // A client signs in against a server it must first be pointed at,
        // so its login screen carries the connect flow and the boot state.
        component: __INVOICESHELF_CLIENT__ ? ClientLoginView : LoginView,
        meta: {
          requiresAuth: false,
          title: 'Login',
        },
      },
      {
        path: '/forgot-password',
        name: 'forgot-password',
        component: ForgotPasswordView,
        meta: {
          requiresAuth: false,
          title: 'Forgot Password',
        },
      },
      {
        path: '/reset-password/:token',
        name: 'reset-password',
        component: ResetPasswordView,
        meta: {
          requiresAuth: false,
          title: 'Reset Password',
        },
      },
      {
        path: '/register',
        name: 'register-with-invitation',
        component: RegisterWithInvitationView,
        meta: {
          requiresAuth: false,
          title: 'Register',
        },
      },
    ],
  },
]
