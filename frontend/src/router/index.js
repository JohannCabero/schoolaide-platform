import { createRouter, createWebHistory } from 'vue-router'
import LoginPage from '../pages/LoginPage.vue'
import DashboardPage from '../pages/DashboardPage.vue'
import StudentsPage from '../pages/StudentsPage.vue'
import StudentFormPage from '../pages/StudentFormPage.vue'
import ServiceRequestsPage from '../pages/ServiceRequestsPage.vue'
import ImportsPage from '../pages/ImportsPage.vue'

const routes = [
  {
    path: '/',
    redirect: '/dashboard'
  },
  {
    path: '/login',
    component: LoginPage
  },
  {
    path: '/dashboard',
    component: DashboardPage,
    meta: { requiresAuth: true }
  },
  {
    path: '/students',
    component: StudentsPage,
    meta: { requiresAuth: true }
  },
  {
    path: '/students/add',
    component: StudentFormPage,
    meta: { requiresAuth: true }
  },
  {
    path: '/students/:id/edit',
    component: StudentFormPage,
    meta: { requiresAuth: true }
  },
  {
    path: '/service-requests',
    component: ServiceRequestsPage,
    meta: { requiresAuth: true }
  },
  {
    path: '/imports',
    component: ImportsPage,
    meta: { requiresAuth: true }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('token')

  if (to.meta.requiresAuth && !token) {
    next('/login')
  } else if (to.path === '/login' && token) {
    next('/dashboard')
  } else {
    next()
  }
})

export default router
