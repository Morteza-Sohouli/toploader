<template>
  <router-view v-if="!isLoading" />
  <div
    v-else
    class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-950"
  >
    <div class="text-center">
      <div
        class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-primary-600 border-t-transparent"
      ></div>
      <p class="mt-4 text-gray-600 dark:text-gray-400">در حال بارگذاری...</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useAuthStore } from "./store/auth";
import { useThemeStore } from "./store/theme";

const authStore = useAuthStore();
const themeStore = useThemeStore();
const isLoading = ref(true);

onMounted(async () => {
  // Initialize theme
  themeStore.initTheme();

  // Check authentication status
  await authStore.checkAuth();

  isLoading.value = false;
});
</script>
