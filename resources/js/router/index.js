import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';

import LoginView from '../views/LoginView.vue';
import ScheduleView from '../views/manager/ScheduleView.vue';
import TasksView from '../views/manager/TasksView.vue';
import EmployeesView from '../views/manager/EmployeesView.vue';
import RequestsView from '../views/manager/RequestsView.vue';
import ReportsView from '../views/manager/ReportsView.vue';
import AttendanceRulesView from '../views/manager/AttendanceRulesView.vue';
import MyScheduleView from '../views/employee/MyScheduleView.vue';
import MyTasksView from '../views/employee/MyTasksView.vue';
import MyRequestsView from '../views/employee/MyRequestsView.vue';
import AttendanceHistoryView from '../views/employee/AttendanceHistoryView.vue';
import LateWarningsView from '../views/employee/LateWarningsView.vue';

const routes = [
  { path: '/login', name: 'login', component: LoginView, meta: { guestOnly: true } },

  // Manajer / Admin / karyawan dgn can_manage_schedule
  { path: '/jadwal', name: 'jadwal', component: ScheduleView, meta: { requiresTeamAccess: true } },
  { path: '/tugas-tim', name: 'tugas-tim', component: TasksView, meta: { requiresTeamAccess: true } },
  { path: '/karyawan', name: 'karyawan', component: EmployeesView, meta: { requiresManagerOnly: true } },
  { path: '/permintaan', name: 'permintaan', component: RequestsView, meta: { requiresManagerOnly: true } },
  { path: '/laporan', name: 'laporan', component: ReportsView, meta: { requiresManagerOnly: true } },
  { path: '/aturan-absen', name: 'aturan-absen', component: AttendanceRulesView, meta: { requiresManagerOnly: true } },

  // Karyawan biasa
  { path: '/jadwal-saya', name: 'jadwal-saya', component: MyScheduleView, meta: { requiresAuth: true } },
  { path: '/tugas-saya', name: 'tugas-saya', component: MyTasksView, meta: { requiresAuth: true } },
  { path: '/ajukan', name: 'ajukan', component: MyRequestsView, meta: { requiresAuth: true } },
  { path: '/riwayat-absensi', name: 'riwayat-absensi', component: AttendanceHistoryView, meta: { requiresAuth: true } },
  { path: '/warning-telat', name: 'warning-telat', component: LateWarningsView, meta: { requiresAuth: true } },

  { path: '/', redirect: '/login' },
  { path: '/:pathMatch(.*)*', redirect: '/login' },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach((to) => {
  const auth = useAuthStore();

  if (to.meta.guestOnly && auth.isLoggedIn) {
    return auth.role === 'employee' ? { name: 'jadwal-saya' } : { name: 'jadwal' };
  }

  if ((to.meta.requiresAuth || to.meta.requiresTeamAccess || to.meta.requiresManagerOnly) && !auth.isLoggedIn) {
    return { name: 'login' };
  }

  if (to.meta.requiresManagerOnly && auth.role !== 'manager') {
    return { name: 'jadwal-saya' };
  }

  if (to.meta.requiresTeamAccess && !auth.canManageTeam) {
    return { name: 'jadwal-saya' };
  }

  return true;
});

export default router;
