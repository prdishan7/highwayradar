import { createRouter, createWebHashHistory } from 'vue-router';
import { sessionToken, currentRole, refreshProfile } from '../services/session';

const routes = [
  { path: '/', redirect: '/community' },
  { path: '/login', component: () => import('../views/Login.vue') },
  { path: '/register', component: () => import('../views/Register.vue') },
  { path: '/dashboard', redirect: '/community' },
  { path: '/alerts', redirect: '/community' },
  { path: '/driver', component: () => import('../views/Driver.vue'), meta: { roles: ['driver'] } },
  { path: '/community', component: () => import('../views/Community.vue') },
  { path: '/planner', component: () => import('../views/TripPlanner.vue') },
  { path: '/report', component: () => import('../views/ReportIncident.vue') },
  { path: '/profile', component: () => import('../views/Profile.vue') },
  { path: '/admin', component: () => import('../views/Admin.vue'), meta: { roles: ['admin', 'superadmin'] } },
  { path: '/superadmin', component: () => import('../views/SuperAdmin.vue'), meta: { roles: ['superadmin'] } }
];

const router = createRouter({
  history: createWebHashHistory(),
  routes
});

function homeForRole(role) {
  if (role === 'superadmin') return '/superadmin';
  if (role === 'admin') return '/admin';
  return '/community';
}

let profileLoaded = false;

router.beforeEach(async (to) => {
  const hasToken = !!sessionToken.value;
  const publicPaths = ['/login', '/register', '/dashboard', '/community'];
  const authOnlyPaths = ['/planner', '/report', '/profile', '/driver', '/admin', '/superadmin'];
  const isPublic = publicPaths.includes(to.path);
  const isAuthOnly = authOnlyPaths.includes(to.path);

  if (!hasToken && isAuthOnly) {
    return '/login';
  }

  if (!hasToken && !isPublic) return '/community';
  if (!hasToken) return true;

  if (!profileLoaded) {
    try {
      await refreshProfile();
      profileLoaded = true;
    } catch (e) {
      return '/login';
    }
  }

  const role = currentRole.value || 'driver';

  if (to.path === '/login' || to.path === '/register') {
    return homeForRole(role);
  }

  if (to.meta.roles && !to.meta.roles.includes(role)) {
    return homeForRole(role);
  }

  return true;
});

export default router;
