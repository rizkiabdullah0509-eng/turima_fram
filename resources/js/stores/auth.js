import { defineStore } from 'pinia';
import api from '../lib/api';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem('tf_token') || null,
    user: JSON.parse(localStorage.getItem('tf_user') || 'null'),
  }),
  getters: {
    isLoggedIn: (state) => !!state.token,
    role: (state) => state.user?.role || null,
    // employee dgn can_manage_schedule, atau manajer/admin -> boleh kelola jadwal & tugas tim
    canManageTeam: (state) => {
      if (!state.user) return false;
      return state.user.role === 'manager' || state.user.role === 'admin' || !!state.user.can_manage_schedule;
    },
  },
  actions: {
    async login(username, password) {
      const { data } = await api.post('/login', { username, password });
      this.token = data.token;
      this.user = data.user;
      localStorage.setItem('tf_token', data.token);
      localStorage.setItem('tf_user', JSON.stringify(data.user));
    },
    async logout() {
      try { await api.post('/logout'); } catch (e) { /* abaikan */ }
      this.token = null;
      this.user = null;
      localStorage.removeItem('tf_token');
      localStorage.removeItem('tf_user');
    },
    setUser(user) {
      this.user = user;
      localStorage.setItem('tf_user', JSON.stringify(user));
    },
  },
});
