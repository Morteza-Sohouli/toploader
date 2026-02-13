<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-950">
    <!-- Navigation -->
    <nav
      class="bg-white dark:bg-gray-800 shadow-md border-b border-gray-200 dark:border-gray-700"
    >
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
          <div class="flex items-center gap-8">
            <router-link
              to="/"
              class="text-2xl font-bold text-primary-600 dark:text-primary-400"
            >
              تاپلودر
            </router-link>
            <div class="flex flex-wrap gap-2 md:gap-4">
              <router-link
                to="/"
                class="px-3 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors [&.router-link-exact-active]:bg-gray-200 [&.router-link-exact-active]:dark:bg-gray-600"
              >
                داشبورد
              </router-link>
              <router-link
                to="/upload"
                class="px-3 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors [&.router-link-exact-active]:bg-gray-200 [&.router-link-exact-active]:dark:bg-gray-600"
              >
                آپلود فایل
              </router-link>
              <router-link
                v-if="user?.is_admin"
                to="/admin"
                class="px-3 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-red-600 dark:text-red-400 transition-colors [&.router-link-exact-active]:bg-gray-200 [&.router-link-exact-active]:dark:bg-gray-600"
              >
                پنل مدیریت
              </router-link>
            </div>
          </div>

          <div class="flex items-center gap-4">
            <div
              class="hidden sm:block text-sm text-gray-600 dark:text-gray-400"
            >
              <span class="font-medium">{{ user?.username }}</span>
            </div>

            <button
              @click="toggleTheme"
              class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
              title="تغییر تم"
            >
              <svg
                v-if="theme === 'light'"
                class="w-5 h-5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"
                />
              </svg>
              <svg
                v-else
                class="w-5 h-5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"
                />
              </svg>
            </button>

            <button @click="handleLogout" class="btn-secondary text-sm">
              خروج
            </button>
          </div>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="animate-fade-in space-y-6">
        <!-- Welcome Card -->
        <div class="card">
          <div
            class="flex flex-col md:flex-row md:items-center md:justify-between"
          >
            <div>
              <h1 class="text-3xl font-bold mb-2">
                خوش آمدید، {{ user?.username }}!
              </h1>
              <p class="text-gray-600 dark:text-gray-400">
                شما می‌توانید فایل‌های خود را با امنیت کامل آپلود و مدیریت کنید
              </p>
            </div>
            <router-link
              to="/upload"
              class="btn-primary mt-4 md:mt-0 inline-block"
            >
              آپلود فایل جدید
            </router-link>
          </div>
        </div>

        <!-- Stats Overview Cards -->
        <div v-if="isLoadingStats" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="n in 5"
            :key="n"
            class="card animate-pulse"
          >
            <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-1/2 mb-3"></div>
            <div class="h-8 bg-gray-300 dark:bg-gray-600 rounded w-3/4"></div>
          </div>
        </div>

        <div v-else-if="stats" class="space-y-6">
          <!-- User Stats Row -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="card">
              <div class="flex items-center gap-3">
                <div class="shrink-0 w-10 h-10 bg-primary-100 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500 dark:text-gray-400">فایل‌های شما</p>
                  <p class="text-2xl font-bold">{{ stats.file_count }}</p>
                </div>
              </div>
            </div>

            <div class="card">
              <div class="flex items-center gap-3">
                <div class="shrink-0 w-10 h-10 bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500 dark:text-gray-400">حجم مصرفی شما</p>
                  <p class="text-2xl font-bold">{{ stats.total_size_formatted }}</p>
                </div>
              </div>
            </div>

            <div class="card">
              <div class="flex items-center gap-3">
                <div class="shrink-0 w-10 h-10 bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500 dark:text-gray-400">آپلود ۷ روز اخیر</p>
                  <p class="text-2xl font-bold">{{ stats.uploads_last_7_days }}</p>
                </div>
              </div>
            </div>

            <div class="card">
              <div class="flex items-center gap-3">
                <div class="shrink-0 w-10 h-10 bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500 dark:text-gray-400">کل دانلودها</p>
                  <p class="text-2xl font-bold">{{ stats.total_downloads }}</p>
                </div>
              </div>
            </div>

            <div class="card">
              <div class="flex items-center gap-3">
                <div class="shrink-0 w-10 h-10 bg-cyan-100 dark:bg-cyan-900/30 text-cyan-600 dark:text-cyan-400 rounded-lg flex items-center justify-center">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <div>
                  <p class="text-xs text-gray-500 dark:text-gray-400">دانلود ۷ روز اخیر</p>
                  <p class="text-2xl font-bold">{{ stats.downloads_last_7_days }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Files by Type -->
          <div class="card" v-if="stats.files_by_type.length > 0">
            <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
              <svg class="w-6 h-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
              </svg>
              فایل‌های شما بر اساس نوع
            </h2>
            <div class="space-y-3">
              <div
                v-for="item in stats.files_by_type"
                :key="item.type"
                class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700 last:border-b-0"
              >
                <div class="flex items-center gap-2">
                  <span class="px-2 py-0.5 bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 rounded text-xs font-bold uppercase">
                    {{ item.type }}
                  </span>
                  <span class="text-sm text-gray-600 dark:text-gray-400">
                    {{ item.count }} فایل
                  </span>
                </div>
                <span class="text-sm font-medium">{{ item.total_size_formatted }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Stats Error -->
        <div v-else-if="statsError" class="card text-center text-gray-500 dark:text-gray-400 py-8">
          <p>{{ statsError }}</p>
          <button @click="fetchStats" class="btn-secondary mt-3 text-sm">تلاش مجدد</button>
        </div>

        <!-- User Info Card -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="card">
            <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
              <svg
                class="w-6 h-6 text-primary-600 dark:text-primary-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                />
              </svg>
              اطلاعات کاربری
            </h2>
            <div class="space-y-3">
              <div
                class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700"
              >
                <span class="text-gray-600 dark:text-gray-400"
                  >نام کاربری:</span
                >
                <span class="font-medium">{{ user?.username }}</span>
              </div>
              <div
                class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-700"
              >
                <span class="text-gray-600 dark:text-gray-400"
                  >شناسه کاربری:</span
                >
                <span class="font-medium">#{{ user?.id }}</span>
              </div>
              <div class="flex justify-between items-center py-2">
                <span class="text-gray-600 dark:text-gray-400"
                  >تاریخ عضویت:</span
                >
                <span class="font-medium text-sm">{{
                  formatDate(user?.created_at)
                }}</span>
              </div>
            </div>
          </div>

          <div class="card">
            <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
              <svg
                class="w-6 h-6 text-primary-600 dark:text-primary-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                />
              </svg>
              انواع فایل مجاز
            </h2>
            <div class="flex flex-wrap gap-2">
              <span
                v-for="type in allowedFileTypes"
                :key="type"
                class="px-3 py-1 bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 rounded-full text-sm font-medium"
              >
                {{ type.toUpperCase() }}
              </span>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-4">
              شما فقط می‌توانید فایل‌های با فرمت‌های بالا را آپلود کنید
            </p>
          </div>
        </div>

        <!-- Features -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div class="card-hover">
            <div class="text-center">
              <div
                class="inline-flex items-center justify-center w-12 h-12 bg-primary-100 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 rounded-full mb-4"
              >
                <svg
                  class="w-6 h-6"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                  />
                </svg>
              </div>
              <h3 class="text-lg font-bold mb-2">امنیت بالا</h3>
              <p class="text-sm text-gray-600 dark:text-gray-400">
                فایل‌های شما با امنیت کامل ذخیره می‌شوند
              </p>
            </div>
          </div>

          <div class="card-hover">
            <div class="text-center">
              <div
                class="inline-flex items-center justify-center w-12 h-12 bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 rounded-full mb-4"
              >
                <svg
                  class="w-6 h-6"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M13 10V3L4 14h7v7l9-11h-7z"
                  />
                </svg>
              </div>
              <h3 class="text-lg font-bold mb-2">سرعت بالا</h3>
              <p class="text-sm text-gray-600 dark:text-gray-400">
                آپلود و دانلود سریع با بهترین کیفیت
              </p>
            </div>
          </div>

          <div class="card-hover">
            <div class="text-center">
              <div
                class="inline-flex items-center justify-center w-12 h-12 bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 rounded-full mb-4"
              >
                <svg
                  class="w-6 h-6"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"
                  />
                </svg>
              </div>
              <h3 class="text-lg font-bold mb-2">حجم بالا</h3>
              <p class="text-sm text-gray-600 dark:text-gray-400">
                امکان آپلود فایل‌ تا ۲۰ گیگابایت
              </p>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="text-center py-8 text-gray-500 dark:text-gray-400 text-sm">
          <p>ساخته شده با ❤️ توسط تیم توسعه تاپ جی‌اس‌ام</p>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "../store/auth";
import { useThemeStore } from "../store/theme";
import { statsApi, type StatsResponse } from "../api/stats";

const router = useRouter();
const authStore = useAuthStore();
const themeStore = useThemeStore();

const user = authStore.user;
const allowedFileTypes = authStore.allowedFileTypes;
const theme = themeStore.theme;

const stats = ref<StatsResponse | null>(null);
const isLoadingStats = ref(false);
const statsError = ref("");

const toggleTheme = () => {
  themeStore.toggleTheme();
};

const handleLogout = async () => {
  await authStore.logout();
  router.push("/login");
};

const formatDate = (dateString: string | undefined) => {
  if (!dateString) return "-";
  const date = new Date(dateString);
  return new Intl.DateTimeFormat("fa-IR", {
    year: "numeric",
    month: "long",
    day: "numeric",
  }).format(date);
};

const fetchStats = async () => {
  isLoadingStats.value = true;
  statsError.value = "";
  try {
    const result = await statsApi.getStats();
    if (result.success && result.data) {
      stats.value = result.data;
    } else {
      statsError.value = result.error || "خطا در دریافت آمار";
    }
  } catch {
    statsError.value = "خطا در دریافت آمار";
  } finally {
    isLoadingStats.value = false;
  }
};

onMounted(() => {
  fetchStats();
});
</script>
