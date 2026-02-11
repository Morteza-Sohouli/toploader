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
        <h2 class="text-2xl font-bold mb-6 text-center">ثبت‌نام کاربر جدید</h2>

        <form @submit.prevent="handleRegister" class="space-y-4">
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
              placeholder="نام کاربری دلخواه خود را انتخاب کنید"
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
              minlength="1"
              class="input-field"
              placeholder="رمز عبور خود را وارد کنید"
              :disabled="isLoading"
            />
          </div>

          <div>
            <label for="confirmPassword" class="block text-sm font-medium mb-2"
              >تکرار رمز عبور</label
            >
            <input
              id="confirmPassword"
              v-model="confirmPassword"
              type="password"
              required
              class="input-field"
              placeholder="رمز عبور را دوباره وارد کنید"
              :disabled="isLoading"
            />
          </div>

          <div>
            <label class="block text-sm font-medium mb-2"
              >انواع فایل‌های مجاز</label
            >
            <div class="space-y-2">
              <div class="flex flex-wrap gap-2">
                <label
                  v-for="type in availableFileTypes"
                  :key="type.value"
                  class="inline-flex items-center"
                >
                  <input
                    type="checkbox"
                    :value="type.value"
                    v-model="selectedFileTypes"
                    class="ml-2 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                    :disabled="isLoading"
                  />
                  <span class="text-sm">{{ type.label }}</span>
                </label>
              </div>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
              انواع فایل‌هایی که می‌توانید آپلود کنید را انتخاب کنید
            </p>
          </div>

          <div
            v-if="error"
            class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 px-4 py-3 rounded-lg text-sm"
          >
            {{ error }}
          </div>

          <div
            v-if="success"
            class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-600 dark:text-green-400 px-4 py-3 rounded-lg text-sm"
          >
            ثبت‌نام با موفقیت انجام شد! در حال انتقال به صفحه ورود...
          </div>

          <button
            type="submit"
            class="btn-primary w-full"
            :disabled="isLoading"
          >
            <span v-if="isLoading">در حال ثبت‌نام...</span>
            <span v-else>ثبت‌نام</span>
          </button>
        </form>

        <div class="mt-6 text-center">
          <p class="text-sm text-gray-600 dark:text-gray-400">
            قبلاً ثبت‌نام کرده‌اید؟
            <router-link
              to="/login"
              class="text-primary-600 dark:text-primary-400 font-medium hover:underline"
            >
              وارد شوید
            </router-link>
          </p>
        </div>
      </div>

      <div class="mt-8 text-center text-sm text-gray-500 dark:text-gray-400">
        <p>ساخته شده توسط تیم توسعه تاپ جی‌اس‌ام</p>
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
const confirmPassword = ref("");
const selectedFileTypes = ref(["jpg", "png", "pdf"]);
const error = ref("");
const success = ref(false);
const isLoading = ref(false);

const availableFileTypes = [
  { value: "jpg", label: "JPG" },
  { value: "jpeg", label: "JPEG" },
  { value: "png", label: "PNG" },
  { value: "gif", label: "GIF" },
  { value: "webp", label: "WebP" },
  { value: "pdf", label: "PDF" },
  { value: "doc", label: "DOC" },
  { value: "docx", label: "DOCX" },
  { value: "txt", label: "TXT" },
  { value: "csv", label: "CSV" },
  { value: "xls", label: "XLS" },
  { value: "xlsx", label: "XLSX" },
  { value: "zip", label: "ZIP" },
  { value: "rar", label: "RAR" },
  { value: "mp3", label: "MP3" },
  { value: "mp4", label: "MP4" },
];

const handleRegister = async () => {
  error.value = "";
  success.value = false;

  if (password.value !== confirmPassword.value) {
    error.value = "رمز عبور و تکرار آن یکسان نیستند";
    return;
  }

  if (selectedFileTypes.value.length === 0) {
    error.value = "لطفاً حداقل یک نوع فایل را انتخاب کنید";
    return;
  }

  isLoading.value = true;

  const allowedTypes = selectedFileTypes.value.join(",");
  const result = await authStore.register(
    username.value,
    password.value,
    allowedTypes,
  );

  if (result.success) {
    success.value = true;
    setTimeout(() => {
      router.push("/login");
    }, 2000);
  } else {
    error.value = result.error || "خطا در ثبت‌نام";
  }

  isLoading.value = false;
};
</script>
