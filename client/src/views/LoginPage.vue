<template>
  <div
    class="min-h-screen flex items-center justify-center bg-gradient-to-br from-primary-50 to-blue-50 dark:from-gray-900 dark:to-gray-800 px-4 py-12"
  >
    <div class="w-full max-w-md animate-fade-in">
      <div class="text-center mb-8">
        <h1
          class="text-4xl font-bold text-primary-600 dark:text-primary-400 mb-2"
        >
          تاپلودر
        </h1>
        <p class="text-gray-600 dark:text-gray-400">
          سامانه اشتراک‌گذاری فایل امن
        </p>
      </div>

      <div class="card animate-scale-in">
        <h2 class="text-2xl font-bold mb-6 text-center">ورود به حساب کاربری</h2>

        <form @submit.prevent="handleLogin" class="space-y-4">
          <div>
            <label for="username" class="block text-sm font-medium mb-2"
              >نام کاربری</label
            >
            <input
              id="username"
              v-model="username"
              type="text"
              required
              class="input-field"
              placeholder="نام کاربری خود را وارد کنید"
              :disabled="isLoading"
            />
          </div>

          <div>
            <label for="password" class="block text-sm font-medium mb-2"
              >رمز عبور</label
            >
            <input
              id="password"
              v-model="password"
              type="password"
              required
              class="input-field"
              placeholder="رمز عبور خود را وارد کنید"
              :disabled="isLoading"
            />
          </div>

          <div
            v-if="error"
            class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 px-4 py-3 rounded-lg text-sm"
          >
            {{ error }}
          </div>

          <button
            type="submit"
            class="btn-primary w-full"
            :disabled="isLoading"
          >
            <span v-if="isLoading">در حال ورود...</span>
            <span v-else>ورود</span>
          </button>
        </form>
      </div>

      <div class="text-center py-8 text-gray-500 dark:text-gray-400 text-sm">
        <p>ساخته شده با ❤️ توسط تیم توسعه تاپ جی‌اس‌ام</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "../store/auth";

const router = useRouter();
const authStore = useAuthStore();

const username = ref("");
const password = ref("");
const error = ref("");
const isLoading = ref(false);

const handleLogin = async () => {
  error.value = "";
  isLoading.value = true;

  const result = await authStore.login(username.value, password.value);

  if (result.success) {
    router.push("/");
  } else {
    error.value = result.error || "خطا در ورود به سیستم";
  }

  isLoading.value = false;
};
</script>
