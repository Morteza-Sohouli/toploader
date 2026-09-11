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

    <!-- Notification -->
    <div
      v-if="notification"
      class="fixed top-20 left-1/2 transform -translate-x-1/2 z-50 animate-fade-in"
    >
      <div
        :class="[
          'px-6 py-3 rounded-lg shadow-lg text-white text-sm font-medium',
          notification.type === 'success' ? 'bg-green-500' : 'bg-red-500',
        ]"
      >
        {{ notification.message }}
      </div>
    </div>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="animate-fade-in">
        <div class="card">
          <h1 class="text-3xl font-bold mb-8">آپلود فایل</h1>

          <div
            @dragenter.prevent="handleDragEnter"
            @dragover.prevent="handleDragOver"
            @dragleave.prevent="handleDragLeave"
            @drop.prevent="handleDrop"
            :class="[
              'border-2 border-dashed rounded-xl p-8 text-center transition-all duration-200',
              isDragging
                ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20 cursor-pointer'
                : 'border-gray-300 dark:border-gray-600 hover:border-primary-400 dark:hover:border-primary-500 cursor-pointer',
            ]"
          >
            <input
              ref="fileInput"
              type="file"
              multiple
              @change="handleFileSelect"
              class="hidden"
              :accept="acceptedTypes"
            />

            <div v-if="selectedFiles.length === 0" class="cursor-pointer">
              <svg
                class="mx-auto h-16 w-16 text-gray-400 mb-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                />
              </svg>
              <p class="text-lg font-medium mb-2">
                فایل‌های خود را اینجا رها کنید
              </p>
              <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">یا</p>
              <button @click="() => fileInput?.click()" class="btn-primary">
                انتخاب فایل‌ها
              </button>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-4">
                انواع فایل مجاز: {{ allowedFileTypes.join(", ").toUpperCase() }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                حداکثر حجم: 30 گیگابایت
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                آپلودها قابل ادامه هستند — حتی بعد از بستن مرورگر تا ۲۴ ساعت
              </p>
            </div>

            <div v-else class="space-y-4">
              <div class="space-y-3">
                <div class="flex items-center justify-center gap-2 mb-3">
                  <svg
                    class="h-8 w-8 text-primary-600 dark:text-primary-400"
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
                  <span class="font-medium text-lg"
                    >{{ selectedFiles.length }} فایل انتخاب شده</span
                  >
                </div>

                <div
                  class="max-h-40 overflow-y-auto space-y-2 bg-gray-50 dark:bg-gray-800 rounded-lg p-3"
                >
                  <div
                    v-for="(file, index) in selectedFiles"
                    :key="index"
                    class="flex items-center justify-between bg-white dark:bg-gray-700 rounded-lg p-2 text-sm"
                  >
                    <div class="flex items-center gap-2 flex-1 min-w-0">
                      <svg
                        class="h-4 w-4 text-gray-400 flex-shrink-0"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                        ></path>
                      </svg>
                      <span class="font-medium truncate">{{ file.name }}</span>
                      <span
                        class="text-gray-500 dark:text-gray-400 text-xs flex-shrink-0"
                        >{{ formatFileSize(file.size) }}</span
                      >
                    </div>
                    <button
                      @click="removeSelectedFile(index)"
                      class="p-1 hover:bg-gray-200 dark:hover:bg-gray-600 rounded transition-colors flex-shrink-0"
                      title="حذف فایل"
                    >
                      <svg
                        class="h-3 w-3"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M6 18L18 6M6 6l12 12"
                        ></path>
                      </svg>
                    </button>
                  </div>
                </div>
              </div>

              <div v-if="uploadProgress > 0" class="w-full">
                <div class="flex justify-between items-center mb-2">
                  <span class="text-sm font-medium">در حال آپلود...</span>
                  <span class="text-sm text-gray-600 dark:text-gray-400"
                    >{{ uploadProgress }}%</span
                  >
                </div>
                <div
                  class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden"
                >
                  <div
                    class="bg-primary-600 h-2 transition-all duration-300"
                    :style="{ width: uploadProgress + '%' }"
                  ></div>
                </div>
              </div>

              <div class="flex gap-3 justify-center">
                <button
                  v-if="uploadProgress === 0"
                  @click="uploadFiles"
                  class="btn-primary"
                  :disabled="isUploading"
                >
                  آپلود {{ selectedFiles.length }} فایل
                </button>
                <button
                  @click="clearFiles"
                  class="btn-secondary"
                  :disabled="isCancelling"
                >
                  {{
                    isCancelling
                      ? "در حال لغو..."
                      : isUploading
                        ? "لغو آپلود"
                        : "لغو"
                  }}
                </button>
              </div>
            </div>
          </div>

          <div
            v-if="error"
            class="mt-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 px-4 py-3 rounded-lg text-sm font-mono whitespace-pre-wrap break-words"
          >
            {{ error }}
          </div>

          <!-- Pending Uploads (resumable) -->
          <div v-if="pendingUploads.length > 0" class="mt-8">
            <h2 class="text-xl font-bold mb-4">آپلودهای ناتمام</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
              این فایل‌ها آپلود کامل نشده‌اند. فایل اصلی را انتخاب کنید تا ادامه
              آپلود شروع شود.
            </p>
            <div class="space-y-3">
              <div
                v-for="pending in pendingUploads"
                :key="pending.id"
                class="border rounded-lg p-4 bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800"
              >
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-3 flex-1 min-w-0">
                    <div
                      class="flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center bg-amber-100 dark:bg-amber-800 text-amber-600 dark:text-amber-400"
                    >
                      <svg
                        class="w-5 h-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        ></path>
                      </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="font-medium text-sm truncate">
                        {{ pending.filename }}
                      </p>
                      <p
                        class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1"
                      >
                        <span>{{ formatFileSize(pending.size) }}</span>
                        <span class="mx-1">•</span>
                        <span>{{ formatTimeAgo(pending.createdAt) }}</span>
                      </p>
                      <!-- Resume progress -->
                      <div
                        v-if="resumingUploads.has(pending.id)"
                        class="mt-2 space-y-1"
                      >
                        <div class="flex justify-between text-xs">
                          <span>در حال ادامه آپلود...</span>
                          <span
                            >{{ resumeProgress.get(pending.id) ?? 0 }}%</span
                          >
                        </div>
                        <div
                          class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 overflow-hidden"
                        >
                          <div
                            class="bg-amber-500 h-1.5 transition-all duration-300"
                            :style="{
                              width:
                                (resumeProgress.get(pending.id) ?? 0) + '%',
                            }"
                          ></div>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="flex items-center gap-2 flex-shrink-0">
                    <label
                      v-if="!resumingUploads.has(pending.id)"
                      class="btn-primary text-xs px-3 py-1.5 cursor-pointer"
                    >
                      ادامه آپلود
                      <input
                        type="file"
                        class="hidden"
                        @change="(e) => handleResumeFile(e, pending)"
                      />
                    </label>
                    <button
                      v-if="resumingUploads.has(pending.id)"
                      @click="cancelResume(pending.id)"
                      class="btn-secondary text-xs px-3 py-1.5"
                    >
                      لغو
                    </button>
                    <button
                      v-if="!resumingUploads.has(pending.id)"
                      @click="dismissPending(pending.id)"
                      class="p-1 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                      title="حذف"
                    >
                      <svg
                        class="w-4 h-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M6 18L18 6M6 6l12 12"
                        ></path>
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Recent Uploads -->
          <div v-if="recentUploads.length > 0" class="mt-8">
            <h2 class="text-xl font-bold mb-4">آپلودهای اخیر</h2>
            <div class="space-y-4">
              <div
                v-for="upload in recentUploads"
                :key="upload.id"
                :class="[
                  'border rounded-lg p-4 transition-all duration-200',
                  upload.status === 'completed'
                    ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800'
                    : upload.status === 'error'
                      ? 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800'
                      : 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800',
                ]"
              >
                <div class="flex items-start justify-between">
                  <div class="flex items-start gap-3 flex-1">
                    <div
                      :class="[
                        'flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center',
                        upload.status === 'completed'
                          ? 'bg-green-100 dark:bg-green-800 text-green-600 dark:text-green-400'
                          : upload.status === 'error'
                            ? 'bg-red-100 dark:bg-red-800 text-red-600 dark:text-red-400'
                            : 'bg-blue-100 dark:bg-blue-800 text-blue-600 dark:text-blue-400',
                      ]"
                    >
                      <svg
                        v-if="upload.status === 'completed'"
                        class="w-5 h-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M5 13l4 4L19 7"
                        ></path>
                      </svg>
                      <svg
                        v-else-if="upload.status === 'error'"
                        class="w-5 h-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M6 18L18 6M6 6l12 12"
                        ></path>
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
                          d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"
                        ></path>
                      </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center gap-2 mb-2">
                        <p class="font-medium text-sm truncate">
                          {{ upload.file.name }}
                        </p>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                          {{ formatFileSize(upload.file.size) }}
                        </span>
                      </div>

                      <div
                        v-if="upload.status === 'uploading'"
                        class="space-y-2"
                      >
                        <div class="flex justify-between items-center text-xs">
                          <span>در حال آپلود...</span>
                          <span>{{ upload.progress }}%</span>
                        </div>
                        <div
                          class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden"
                        >
                          <div
                            class="bg-blue-600 h-2 transition-all duration-300"
                            :style="{ width: upload.progress + '%' }"
                          ></div>
                        </div>
                        <div
                          class="flex justify-between text-xs text-gray-600 dark:text-gray-400"
                        >
                          <span>سرعت: {{ formatSpeed(upload.speed) }}</span>
                          <span
                            >زمان باقی‌مانده:
                            {{ formatTime(upload.estimatedTime) }}</span
                          >
                        </div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">
                          آپلود شده:
                          {{ formatFileSize(upload.uploadedBytes) }} از
                          {{ formatFileSize(upload.totalBytes) }}
                        </div>
                      </div>

                      <div
                        v-else-if="
                          upload.status === 'completed' && upload.fileInfo
                        "
                        class="space-y-2"
                      >
                        <div class="text-xs text-green-600 dark:text-green-400">
                          فایل با موفقیت آپلود شد!
                        </div>
                        <div class="text-xs space-y-1">
                          <p>
                            <span class="font-medium">نوع:</span>
                            {{ upload.fileInfo.extension?.toUpperCase() }}
                          </p>
                          <p>
                            <span class="font-medium">زمان:</span>
                            {{ upload.fileInfo.uploaded_at }}
                          </p>
                        </div>
                        <div
                          v-if="upload.fileInfo.download_url"
                          class="mt-3 pt-3 border-t border-green-300 dark:border-green-700"
                        >
                          <p class="font-medium text-xs mb-2">لینک دانلود:</p>
                          <div
                            class="flex items-center gap-2 bg-white dark:bg-gray-800 rounded-lg p-2 border border-green-300 dark:border-green-600"
                          >
                            <input
                              :value="upload.fileInfo.download_url"
                              readonly
                              class="flex-1 text-xs font-mono bg-transparent border-none outline-none text-gray-800 dark:text-gray-200 cursor-pointer"
                              @click="
                                () =>
                                  copyToClipboardFromUrl(
                                    upload.fileInfo!.download_url,
                                  )
                              "
                            />
                            <button
                              @click="
                                () =>
                                  copyToClipboardFromUrl(
                                    upload.fileInfo!.download_url,
                                  )
                              "
                              class="flex items-center gap-1 px-2 py-1 bg-primary-600 hover:bg-primary-700 text-white text-xs rounded-md transition-colors cursor-pointer"
                              title="کپی کردن لینک"
                            >
                              <svg
                                class="w-3 h-3"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                              >
                                <path
                                  stroke-linecap="round"
                                  stroke-linejoin="round"
                                  stroke-width="2"
                                  d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                                ></path>
                              </svg>
                              کپی
                            </button>
                          </div>
                        </div>
                      </div>

                      <div
                        v-else-if="upload.status === 'error'"
                        class="text-xs text-red-600 dark:text-red-400"
                      >
                        خطا: {{ upload.error }}
                      </div>
                    </div>
                  </div>
                  <button
                    @click="removeUpload(upload.id)"
                    class="flex-shrink-0 p-1 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                    title="حذف"
                  >
                    <svg
                      class="w-4 h-4"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M6 18L18 6M6 6l12 12"
                      ></path>
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Previous Uploads -->
          <div v-if="userFiles.length > 0 || isLoadingUserFiles" class="mt-8">
            <div class="mb-4">
              <h2 class="text-xl font-bold mb-4">آپلودهای قبلی</h2>
              <div
                class="flex flex-col sm:flex-row items-start sm:items-end gap-3"
              >
                <div class="flex-1 w-full">
                  <label class="block text-sm font-medium mb-1"
                    >جستجو بر اساس نام فایل</label
                  >
                  <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="نام فایل را وارد کنید..."
                    @input="debounceSearch"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                  />
                </div>
                <div class="flex-1 w-full">
                  <label class="block text-sm font-medium mb-1">از تاریخ</label>
                  <input
                    v-model="fromDate"
                    type="date"
                    @change="applyFilters"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                  />
                </div>
                <div class="flex-1 w-full">
                  <label class="block text-sm font-medium mb-1">تا تاریخ</label>
                  <input
                    v-model="toDate"
                    type="date"
                    @change="applyFilters"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                  />
                </div>
                <button
                  v-if="searchQuery || fromDate || toDate"
                  @click="clearAllFilters"
                  class="px-4 py-2 bg-gray-200 dark:bg-gray-600 hover:bg-gray-300 dark:hover:bg-gray-500 text-gray-700 dark:text-gray-300 rounded-lg transition-colors whitespace-nowrap"
                  title="پاک کردن فیلترها"
                >
                  پاک کردن
                </button>
              </div>
            </div>
            <div v-if="isLoadingUserFiles" class="space-y-3">
              <!-- Loading skeleton -->
              <div
                v-for="n in 3"
                :key="n"
                class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg animate-pulse"
              >
                <div class="flex items-center gap-3 flex-1 min-w-0">
                  <div
                    class="w-8 h-8 bg-gray-300 dark:bg-gray-600 rounded flex-shrink-0"
                  ></div>
                  <div class="flex-1 min-w-0">
                    <div
                      class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-3/4 mb-2"
                    ></div>
                    <div
                      class="h-3 bg-gray-300 dark:bg-gray-600 rounded w-1/2"
                    ></div>
                  </div>
                </div>
                <div
                  class="w-16 h-6 bg-gray-300 dark:bg-gray-600 rounded"
                ></div>
              </div>
            </div>
            <div v-else-if="userFiles.length > 0" class="space-y-3">
              <div
                v-for="file in userFiles"
                :key="file.id"
                class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
              >
                <div class="flex items-center gap-3 flex-1 min-w-0">
                  <svg
                    class="w-8 h-8 text-primary-600 dark:text-primary-400 flex-shrink-0"
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
                  <div class="flex-1 min-w-0">
                    <p class="font-medium text-sm truncate">
                      {{ file.filename }}
                    </p>
                    <p
                      class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1"
                    >
                      <span>{{ file.file_size_formatted }}</span>
                      <span class="mx-1">•</span>
                      <span>{{ formatDate(file.created_at) }}</span>
                    </p>
                  </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0">
                  <button
                    @click="copyToClipboardFromUrl(file.download_url)"
                    class="btn-secondary text-xs px-3 py-1 whitespace-nowrap"
                  >
                    لینک دانلود
                  </button>
                  <span
                    v-if="getFileDeleteRequest(file.id)"
                    class="text-xs px-2 py-1 rounded-lg whitespace-nowrap"
                    :class="{
                      'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400': getFileDeleteRequest(file.id)?.status === 'pending',
                      'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400': getFileDeleteRequest(file.id)?.status === 'approved',
                      'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400': getFileDeleteRequest(file.id)?.status === 'rejected',
                    }"
                  >
                    {{
                      getFileDeleteRequest(file.id)?.status === 'pending' ? 'درخواست حذف در انتظار'
                      : getFileDeleteRequest(file.id)?.status === 'approved' ? 'حذف تایید شد'
                      : 'درخواست رد شد'
                    }}
                  </span>
                  <button
                    v-if="!getFileDeleteRequest(file.id)"
                    @click="openDeleteRequestModal(file)"
                    class="text-xs px-2 py-1 rounded-lg bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/40 transition-colors whitespace-nowrap"
                  >
                    درخواست حذف
                  </button>
                </div>
              </div>
            </div>
            <div
              v-else-if="hasActiveFilters && !isLoadingUserFiles"
              class="text-center py-8 text-gray-500 dark:text-gray-400"
            >
              هیچ فایلی با فیلترهای انتخاب شده یافت نشد
            </div>

            <!-- Pagination -->
            <div
              v-if="totalPages > 1"
              class="mt-6 flex items-center justify-between"
            >
              <div class="text-sm text-gray-600 dark:text-gray-400">
                نمایش {{ (currentPage - 1) * 10 + 1 }} تا
                {{ Math.min(currentPage * 10, totalFiles) }} از
                {{ totalFiles }} فایل
              </div>
              <div class="flex items-center gap-2">
                <button
                  @click="goToPrevPage"
                  :disabled="!hasPrev"
                  class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  قبلی
                </button>

                <div class="flex items-center gap-1">
                  <button
                    v-for="page in visiblePages"
                    :key="page"
                    @click="goToPage(page)"
                    :class="[
                      'px-3 py-1 text-sm border rounded-md',
                      page === currentPage
                        ? 'bg-primary-600 text-white border-primary-600'
                        : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700',
                    ]"
                  >
                    {{ page }}
                  </button>
                </div>

                <button
                  @click="goToNextPage"
                  :disabled="!hasNext"
                  class="px-3 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  بعدی
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- Delete Request Modal -->
    <Teleport to="body">
      <div v-if="showDeleteRequestModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showDeleteRequestModal = false">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-sm p-6 space-y-4">
          <h3 class="text-xl font-bold text-red-600 dark:text-red-400">درخواست حذف فایل</h3>
          <p class="text-sm text-gray-600 dark:text-gray-400">
            درخواست حذف فایل <strong class="text-gray-800 dark:text-gray-200">{{ deleteRequestFilename }}</strong> ارسال خواهد شد. فایل پس از تأیید ادمین حذف می‌شود.
          </p>
          <div>
            <label class="block text-sm font-medium mb-1">دلیل حذف (اختیاری)</label>
            <textarea
              v-model="deleteRequestReason"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-red-500 focus:border-transparent resize-none text-sm"
              placeholder="دلیل درخواست حذف را بنویسید..."
            />
          </div>
          <div class="flex justify-end gap-2 pt-1">
            <button @click="showDeleteRequestModal = false" class="btn-secondary">انصراف</button>
            <button
              @click="submitDeleteRequest"
              :disabled="isSubmittingDeleteRequest"
              class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors disabled:opacity-50 text-sm font-medium"
            >
              {{ isSubmittingDeleteRequest ? 'در حال ارسال...' : 'ارسال درخواست' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "../store/auth";
import { useThemeStore } from "../store/theme";
import {
  fileApi,
  type UploadedFileInfo,
  type UserFile,
  type PendingUpload,
  type TusUploadHandle,
} from "../api/files";
import type { DeleteRequestItem } from "../api/files";

const router = useRouter();
const authStore = useAuthStore();
const themeStore = useThemeStore();

const user = authStore.user;
const allowedFileTypes = authStore.allowedFileTypes;
const theme = themeStore.theme;

const fileInput = ref<HTMLInputElement>();
const selectedFiles = ref<File[]>([]);
const isDragging = ref(false);
const dragCounter = ref(0);
const isUploading = ref(false);
const isCancelling = ref(false);
const uploadProgress = ref(0);
const error = ref("");
const shouldCancel = ref(false);

interface UploadItem {
  id: string;
  file: File;
  progress: number;
  uploadedBytes: number;
  totalBytes: number;
  speed: number;
  estimatedTime: number;
  status: "uploading" | "completed" | "error";
  startTime: number;
  endTime?: number;
  fileInfo?: UploadedFileInfo;
  error?: string;
  handle?: TusUploadHandle;
}

const recentUploads = ref<UploadItem[]>([]);
const pendingUploads = ref<PendingUpload[]>([]);
const resumingUploads = reactive(new Set<string>());
const resumeProgress = reactive(new Map<string, number>());
const resumeHandles = new Map<string, TusUploadHandle>();
const activeHandles = ref<TusUploadHandle[]>([]);
const userFiles = ref<UserFile[]>([]);
const isLoadingUserFiles = ref(false);
const currentPage = ref(1);
const totalPages = ref(1);
const totalFiles = ref(0);
const hasNext = ref(false);
const hasPrev = ref(false);
const searchQuery = ref("");
const fromDate = ref("");
const toDate = ref("");
const notification = ref<{ message: string; type: "success" | "error" } | null>(
  null,
);

const visiblePages = computed(() => {
  const pages: number[] = [];
  const maxVisible = 5;
  const half = Math.floor(maxVisible / 2);

  let start = Math.max(1, currentPage.value - half);
  let end = Math.min(totalPages.value, start + maxVisible - 1);

  if (end - start + 1 < maxVisible) {
    start = Math.max(1, end - maxVisible + 1);
  }

  for (let i = start; i <= end; i++) {
    pages.push(i);
  }

  return pages;
});

const hasActiveFilters = computed(() => {
  return (
    searchQuery.value.trim() !== "" ||
    fromDate.value !== "" ||
    toDate.value !== ""
  );
});

const acceptedTypes = computed(() => {
  return allowedFileTypes.value.map((type) => `.${type}`).join(",");
});

const toggleTheme = () => {
  themeStore.toggleTheme();
};

const handleLogout = async () => {
  await authStore.logout();
  router.push("/login");
};

const formatFileSize = (bytes: number): string => {
  if (bytes === 0) return "0 بایت";
  const k = 1024;
  const sizes = ["بایت", "کیلوبایت", "مگابایت", "گیگابایت"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + " " + sizes[i];
};

const formatSpeed = (bytesPerSecond: number): string => {
  if (bytesPerSecond === 0) return "0 B/s";
  const k = 1024;
  const sizes = ["B/s", "KB/s", "MB/s", "GB/s"];
  const i = Math.floor(Math.log(bytesPerSecond) / Math.log(k));
  return (
    Math.round((bytesPerSecond / Math.pow(k, i)) * 100) / 100 + " " + sizes[i]
  );
};

const formatTime = (seconds: number): string => {
  if (!isFinite(seconds) || seconds <= 0) return "--";
  if (seconds < 60) return Math.round(seconds) + " ثانیه";
  const minutes = Math.floor(seconds / 60);
  const remainingSeconds = Math.round(seconds % 60);
  return `${minutes}:${remainingSeconds.toString().padStart(2, "0")}`;
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

const formatTimeAgo = (timestamp: number): string => {
  const diff = Date.now() - timestamp;
  const minutes = Math.floor(diff / 60000);
  if (minutes < 1) return "همین الان";
  if (minutes < 60) return `${minutes} دقیقه پیش`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours} ساعت پیش`;
  return "بیش از یک روز پیش";
};

const validateFile = (file: File): boolean => {
  const extension = file.name.split(".").pop()?.toLowerCase();

  if (!extension || !allowedFileTypes.value.includes(extension)) {
    error.value = `نوع فایل مجاز نیست. فقط فایل‌های ${allowedFileTypes.value.join(", ").toUpperCase()} مجاز هستند.`;
    return false;
  }

  const maxSize = 30 * 1024 * 1024 * 1024;
  if (file.size > maxSize) {
    error.value = "حجم فایل بیش از 30 گیگابایت است";
    return false;
  }

  return true;
};

const handleFileSelect = (event: Event) => {
  const target = event.target as HTMLInputElement;
  const files = Array.from(target.files || []);

  if (files.length > 0) {
    error.value = "";
    const validFiles: File[] = [];

    for (const file of files) {
      if (validateFile(file)) {
        validFiles.push(file);
      } else {
        return;
      }
    }

    selectedFiles.value = validFiles;
  }
};

const handleDragEnter = (event: DragEvent) => {
  event.preventDefault();
  dragCounter.value++;
  isDragging.value = true;
};

const handleDragOver = (event: DragEvent) => {
  event.preventDefault();
};

const handleDragLeave = (event: DragEvent) => {
  event.preventDefault();
  dragCounter.value--;
  if (dragCounter.value === 0) {
    isDragging.value = false;
  }
};

const handleDrop = (event: DragEvent) => {
  event.preventDefault();
  dragCounter.value = 0;
  isDragging.value = false;
  const files = Array.from(event.dataTransfer?.files || []);

  if (files.length > 0) {
    error.value = "";
    const validFiles: File[] = [];

    for (const file of files) {
      if (validateFile(file)) {
        validFiles.push(file);
      } else {
        return;
      }
    }

    selectedFiles.value = validFiles;
  }
};

const clearFiles = () => {
  if (isUploading.value) {
    cancelAllUploads();
  } else {
    selectedFiles.value = [];
    uploadProgress.value = 0;
    error.value = "";
    if (fileInput.value) {
      fileInput.value.value = "";
    }
  }
};

const cancelAllUploads = () => {
  isCancelling.value = true;
  shouldCancel.value = true;

  for (const handle of activeHandles.value) {
    try {
      handle.abort();
      const uploadItem = recentUploads.value.find(
        (item) => item.handle?.pendingId === handle.pendingId,
      );
      if (uploadItem && uploadItem.status === "uploading") {
        uploadItem.status = "error";
        uploadItem.error = "آپلود لغو شد";
      }
    } catch (e) {
      console.warn("Failed to abort upload:", e);
    }
  }

  recentUploads.value.forEach((item) => {
    if (item.status === "uploading") {
      item.status = "error";
      item.error = "آپلود لغو شد";
    }
  });

  activeHandles.value = [];

  setTimeout(() => {
    isUploading.value = false;
    isCancelling.value = false;
    shouldCancel.value = false;
    uploadProgress.value = 0;
    selectedFiles.value = [];
    if (fileInput.value) {
      fileInput.value.value = "";
    }
  }, 500);
};

const removeSelectedFile = (index: number) => {
  selectedFiles.value.splice(index, 1);
  if (selectedFiles.value.length === 0 && fileInput.value) {
    fileInput.value.value = "";
  }
};

const uploadFiles = async () => {
  if (selectedFiles.value.length === 0) return;

  error.value = "";
  isUploading.value = true;
  isCancelling.value = false;
  shouldCancel.value = false;
  uploadProgress.value = 0;
  activeHandles.value = [];

  const filesToUpload = [...selectedFiles.value];
  const totalFileCount = filesToUpload.length;
  let completedFiles = 0;
  let hasErrors = false;

  for (const file of filesToUpload) {
    if (shouldCancel.value) break;

    const uploadId = `${Date.now()}-${Math.random()}`;
    const startTime = Date.now();

    const uploadItem: UploadItem = {
      id: uploadId,
      file,
      progress: 0,
      uploadedBytes: 0,
      totalBytes: file.size,
      speed: 0,
      estimatedTime: 0,
      status: "uploading",
      startTime,
    };

    recentUploads.value.unshift(uploadItem);

    try {
      const handle = await fileApi.startTusUpload(
        file,
        (progress, loaded, total) => {
          if (shouldCancel.value) return;

          const currentTime = Date.now();
          const elapsed = (currentTime - startTime) / 1000;

          uploadItem.progress = progress;
          uploadItem.uploadedBytes = loaded;
          uploadItem.totalBytes = total;

          if (elapsed > 0) {
            uploadItem.speed = loaded / elapsed;
            const remainingBytes = total - loaded;
            uploadItem.estimatedTime = remainingBytes / uploadItem.speed;
          }

          const overallProgress =
            ((completedFiles + progress / 100) / totalFileCount) * 100;
          uploadProgress.value = Math.round(overallProgress);
        },
        (fileInfo) => {
          if (!shouldCancel.value) {
            uploadItem.status = "completed";
            uploadItem.endTime = Date.now();
            uploadItem.fileInfo = fileInfo;
            completedFiles++;
            refreshPendingUploads();
          }
        },
        (uploadError) => {
          if (!shouldCancel.value) {
            uploadItem.status = "error";
            uploadItem.error = uploadError.message;
            if (uploadError.details) {
              uploadItem.error += ` - ${uploadError.details}`;
            }
            hasErrors = true;
          }
        },
      );

      uploadItem.handle = handle;
      activeHandles.value.push(handle);

      // Wait for this upload to finish before starting the next
      await new Promise<void>((resolve) => {
        const checkDone = setInterval(() => {
          if (
            uploadItem.status === "completed" ||
            uploadItem.status === "error" ||
            shouldCancel.value
          ) {
            clearInterval(checkDone);
            resolve();
          }
        }, 500);
      });
    } catch (err: any) {
      uploadItem.status = "error";
      uploadItem.error = err.message || "خطای نامشخص";
      hasErrors = true;
    }
  }

  activeHandles.value = [];

  if (!shouldCancel.value) {
    uploadProgress.value = 100;
    if (!hasErrors) {
      selectedFiles.value = [];
      if (fileInput.value) {
        fileInput.value.value = "";
      }
    }
  } else {
    error.value = "آپلود توسط کاربر لغو شد";
  }

  isUploading.value = false;
  isCancelling.value = false;
  shouldCancel.value = false;
};

const handleResumeFile = async (event: Event, pending: PendingUpload) => {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];
  if (!file) return;

  if (file.name !== pending.filename || file.size !== pending.size) {
    showNotification(
      "فایل انتخاب شده با فایل اصلی مطابقت ندارد. لطفاً همان فایل را انتخاب کنید.",
      "error",
    );
    target.value = "";
    return;
  }

  resumingUploads.add(pending.id);
  resumeProgress.set(pending.id, 0);

  const handle = fileApi.resumeTusUpload(
    file,
    pending,
    (_progress, loaded, total) => {
      const pct = Math.round((loaded / total) * 100);
      resumeProgress.set(pending.id, pct);
    },
    (fileInfo) => {
      resumingUploads.delete(pending.id);
      resumeProgress.delete(pending.id);
      resumeHandles.delete(pending.id);

      recentUploads.value.unshift({
        id: `resume-${Date.now()}`,
        file,
        progress: 100,
        uploadedBytes: file.size,
        totalBytes: file.size,
        speed: 0,
        estimatedTime: 0,
        status: "completed",
        startTime: Date.now(),
        endTime: Date.now(),
        fileInfo,
      });

      refreshPendingUploads();
      showNotification("آپلود با موفقیت تکمیل شد", "success");
    },
    (uploadError) => {
      resumingUploads.delete(pending.id);
      resumeProgress.delete(pending.id);
      resumeHandles.delete(pending.id);
      showNotification(uploadError.message, "error");
    },
  );

  resumeHandles.set(pending.id, handle);
  target.value = "";
};

const cancelResume = (pendingId: string) => {
  const handle = resumeHandles.get(pendingId);
  if (handle) {
    handle.abort();
  }
  resumingUploads.delete(pendingId);
  resumeProgress.delete(pendingId);
  resumeHandles.delete(pendingId);
};

const dismissPending = (id: string) => {
  fileApi.removePendingUpload(id);
  refreshPendingUploads();
};

const refreshPendingUploads = () => {
  pendingUploads.value = fileApi.getPendingUploads();
};

const removeUpload = (uploadId: string) => {
  const index = recentUploads.value.findIndex((item) => item.id === uploadId);
  if (index > -1) {
    recentUploads.value.splice(index, 1);
  }
};

const copyToClipboardFromUrl = async (url: string) => {
  try {
    await navigator.clipboard.writeText(url);
    showNotification("لینک دانلود در کلیپ‌بورد کپی شد", "success");
  } catch (err) {
    const textArea = document.createElement("textarea");
    textArea.value = url;
    document.body.appendChild(textArea);
    textArea.select();
    document.execCommand("copy");
    document.body.removeChild(textArea);
    showNotification("لینک دانلود در کلیپ‌بورد کپی شد", "success");
  }
};

const showNotification = (
  message: string,
  type: "success" | "error" = "success",
) => {
  notification.value = { message, type };
  setTimeout(() => {
    notification.value = null;
  }, 3000);
};

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const debounceSearch = () => {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }
  searchTimeout = setTimeout(() => {
    applyFilters();
  }, 500);
};

const applyFilters = async () => {
  currentPage.value = 1;
  await fetchUserFiles(1);
};

const clearAllFilters = async () => {
  searchQuery.value = "";
  fromDate.value = "";
  toDate.value = "";
  currentPage.value = 1;
  await fetchUserFiles(1);
};

const fetchUserFiles = async (page: number = 1) => {
  isLoadingUserFiles.value = true;
  try {
    const result = await fileApi.getUserFiles(
      page,
      30,
      searchQuery.value,
      fromDate.value,
      toDate.value,
    );
    if (result.success && result.data) {
      userFiles.value = result.data.files;
      currentPage.value = result.data.pagination.current_page;
      totalPages.value = result.data.pagination.total_pages;
      totalFiles.value = result.data.pagination.total_files;
      hasNext.value = result.data.pagination.has_next;
      hasPrev.value = result.data.pagination.has_prev;
    }
  } finally {
    isLoadingUserFiles.value = false;
  }
};

const goToPage = async (page: number) => {
  if (page >= 1 && page <= totalPages.value) {
    await fetchUserFiles(page);
  }
};

const goToNextPage = async () => {
  if (hasNext.value) {
    await fetchUserFiles(currentPage.value + 1);
  }
};

const goToPrevPage = async () => {
  if (hasPrev.value) {
    await fetchUserFiles(currentPage.value - 1);
  }
};

// ==================== DELETE REQUESTS ====================
const myDeleteRequests = ref<DeleteRequestItem[]>([]);
const showDeleteRequestModal = ref(false);
const deleteRequestFileId = ref(0);
const deleteRequestFilename = ref("");
const deleteRequestReason = ref("");
const isSubmittingDeleteRequest = ref(false);

async function fetchMyDeleteRequests() {
  const result = await fileApi.getUserDeleteRequests();
  if (result.success && result.data) {
    myDeleteRequests.value = result.data.requests;
  }
}

function getFileDeleteRequest(fileId: number): DeleteRequestItem | undefined {
  return myDeleteRequests.value.find((r) => r.file_id === fileId);
}

function openDeleteRequestModal(file: UserFile) {
  deleteRequestFileId.value = file.id;
  deleteRequestFilename.value = file.filename;
  deleteRequestReason.value = "";
  showDeleteRequestModal.value = true;
}

async function submitDeleteRequest() {
  isSubmittingDeleteRequest.value = true;
  const result = await fileApi.requestFileDeletion(
    deleteRequestFileId.value,
    deleteRequestReason.value.trim() || undefined,
  );
  isSubmittingDeleteRequest.value = false;
  if (result.success) {
    showDeleteRequestModal.value = false;
    showNotification("درخواست حذف ارسال شد. در انتظار تایید ادمین", "success");
    await fetchMyDeleteRequests();
  } else {
    showNotification(result.error || "خطا در ارسال درخواست", "error");
  }
}

onMounted(() => {
  fileApi.cleanExpiredPendingUploads();
  refreshPendingUploads();
  fetchUserFiles();
  fetchMyDeleteRequests();
});
</script>
