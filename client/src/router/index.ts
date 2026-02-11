import {
  createRouter,
  createWebHistory,
  type RouteRecordRaw,
} from "vue-router";
import { watch } from "vue";
import { useAuthStore } from "../store/auth";

const routes: RouteRecordRaw[] = [
  {
    path: "/",
    name: "Home",
    component: () => import("../views/HomePage.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/login",
    name: "Login",
    component: () => import("../views/LoginPage.vue"),
    meta: { guest: true },
  },
  {
    path: "/upload",
    name: "Upload",
    component: () => import("../views/UploadPage.vue"),
    meta: { requiresAuth: true },
  },
  {
    path: "/admin",
    name: "Admin",
    component: () => import("../views/AdminPage.vue"),
    meta: { requiresAuth: true, requiresAdmin: true },
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach(async (to, _from, next) => {
  const authStore = useAuthStore();

  // Wait for any ongoing auth check to complete
  if (authStore.isLoading.value) {
    // Wait for the auth check to finish
    await new Promise((resolve) => {
      const unwatch = watch(
        () => authStore.isLoading.value,
        (isLoading) => {
          if (!isLoading) {
            unwatch();
            resolve(void 0);
          }
        },
        { immediate: false },
      );
    });
  }

  // Check auth status if not already done
  if (!authStore.isAuthenticated.value && !authStore.isLoading.value) {
    await authStore.checkAuth();
  }

  if (to.meta.requiresAuth && !authStore.isAuthenticated.value) {
    // Redirect to login if route requires auth
    next({ name: "Login" });
  } else if (to.meta.requiresAdmin && !authStore.user.value?.is_admin) {
    // Redirect to home if route requires admin
    next({ name: "Home" });
  } else if (to.meta.guest && authStore.isAuthenticated.value) {
    // Redirect to home if already authenticated
    next({ name: "Home" });
  } else {
    next();
  }
});

export default router;
