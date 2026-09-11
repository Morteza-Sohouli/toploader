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
              <span class="mr-1 px-1.5 py-0.5 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded text-xs">ادمین</span>
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
        <!-- Header -->
        <div class="card">
          <h1 class="text-3xl font-bold mb-2">پنل مدیریت</h1>
          <p class="text-gray-600 dark:text-gray-400">
            مدیریت کاربران، فایل‌ها و مشاهده آمار سیستم
          </p>
        </div>

        <!-- Tab Navigation -->
        <div class="flex gap-2 border-b border-gray-200 dark:border-gray-700 pb-0">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="activeTab = tab.id"
            class="px-4 py-2.5 text-sm font-medium rounded-t-lg transition-colors border-b-2 -mb-px"
            :class="activeTab === tab.id
              ? 'border-primary-600 dark:border-primary-400 text-primary-600 dark:text-primary-400 bg-white dark:bg-gray-800'
              : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300'"
          >
            {{ tab.label }}
          </button>
        </div>

        <!-- Tab: Stats -->
        <div v-if="activeTab === 'stats'">
          <div v-if="isLoadingStats" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div v-for="n in 6" :key="n" class="card animate-pulse">
              <div class="h-4 bg-gray-300 dark:bg-gray-600 rounded w-1/2 mb-3"></div>
              <div class="h-8 bg-gray-300 dark:bg-gray-600 rounded w-3/4"></div>
            </div>
          </div>

          <div v-else-if="adminStats" class="space-y-6">
            <!-- Overview Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div class="card">
                <div class="flex items-center gap-3">
                  <div class="shrink-0 w-10 h-10 bg-primary-100 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                  </div>
                  <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">کل فایل‌ها</p>
                    <p class="text-2xl font-bold">{{ adminStats.files.total_count }}</p>
                  </div>
                </div>
              </div>

              <div class="card">
                <div class="flex items-center gap-3">
                  <div class="shrink-0 w-10 h-10 bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" /></svg>
                  </div>
                  <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">حجم کل</p>
                    <p class="text-2xl font-bold">{{ adminStats.files.total_size_formatted }}</p>
                  </div>
                </div>
              </div>

              <div class="card">
                <div class="flex items-center gap-3">
                  <div class="shrink-0 w-10 h-10 bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" /></svg>
                  </div>
                  <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">کل کاربران</p>
                    <p class="text-2xl font-bold">{{ adminStats.users.total_count }}</p>
                  </div>
                </div>
              </div>

              <div class="card">
                <div class="flex items-center gap-3">
                  <div class="shrink-0 w-10 h-10 bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg>
                  </div>
                  <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">آپلود ۷ روز اخیر</p>
                    <p class="text-2xl font-bold">{{ adminStats.files.uploads_last_7_days }}</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Files by Type -->
            <div class="card" v-if="adminStats.files.by_type.length > 0">
              <h2 class="text-xl font-bold mb-4">فایل‌ها بر اساس نوع</h2>
              <div class="space-y-3">
                <div
                  v-for="item in adminStats.files.by_type"
                  :key="item.type"
                  class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700 last:border-b-0"
                >
                  <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 rounded text-xs font-bold uppercase">{{ item.type }}</span>
                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ item.count }} فایل</span>
                  </div>
                  <span class="text-sm font-medium">{{ item.total_size_formatted }}</span>
                </div>
              </div>
            </div>

            <!-- Top Users -->
            <div class="card" v-if="adminStats.users.top_by_files.length > 0">
              <h2 class="text-xl font-bold mb-4">برترین کاربران (بر اساس تعداد فایل)</h2>
              <div class="space-y-3">
                <div
                  v-for="(item, index) in adminStats.users.top_by_files"
                  :key="item.user_id"
                  class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700 last:border-b-0"
                >
                  <div class="flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xs font-bold">{{ index + 1 }}</span>
                    <span class="font-medium">{{ item.username }}</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ item.file_count }} فایل</span>
                  </div>
                  <span class="text-sm font-medium">{{ item.total_size_formatted }}</span>
                </div>
              </div>
            </div>

            <!-- Uploads Per Day -->
            <div class="card" v-if="adminStats.files.uploads_per_day.length > 0">
              <h2 class="text-xl font-bold mb-4">آپلود روزانه (۳۰ روز اخیر)</h2>
              <div class="space-y-2 max-h-80 overflow-y-auto">
                <div
                  v-for="item in adminStats.files.uploads_per_day"
                  :key="item.date"
                  class="flex items-center justify-between py-2 border-b border-gray-100 dark:border-gray-700 last:border-b-0"
                >
                  <span class="text-sm font-medium">{{ item.date }}</span>
                  <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ item.count }} فایل</span>
                    <span class="text-sm font-medium">{{ item.total_size_formatted }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-else-if="statsError" class="card text-center text-gray-500 py-8">
            <p>{{ statsError }}</p>
            <button @click="fetchStats" class="btn-secondary mt-3 text-sm">تلاش مجدد</button>
          </div>
        </div>

        <!-- Tab: Files -->
        <div v-if="activeTab === 'files'">
          <!-- Search & Filters -->
          <div class="card mb-4 space-y-3">
            <div class="flex flex-col md:flex-row gap-3">
              <input
                v-model="fileSearch"
                @input="debouncedFetchFiles"
                type="text"
                placeholder="جستجوی نام فایل..."
                class="input-field flex-1"
              />
              <input
                v-model="fileOwnerFilter"
                @input="debouncedFetchFiles"
                type="text"
                placeholder="نام مالک"
                class="input-field w-full md:w-40"
              />
              <input
                v-model="fileTypeFilter"
                @input="debouncedFetchFiles"
                type="text"
                placeholder="نوع فایل (مثلاً pdf)"
                class="input-field w-full md:w-40"
              />
              <input
                v-model="fileHostFilter"
                @input="debouncedFetchFiles"
                type="text"
                placeholder="میزبان"
                class="input-field w-full md:w-40"
              />
              <select
                v-model="fileSortBy"
                @change="fetchFiles(1)"
                class="input-field w-full md:w-44"
              >
                <option value="created_at:desc">جدیدترین</option>
                <option value="created_at:asc">قدیمی‌ترین</option>
                <option value="size:desc">بیشترین حجم</option>
                <option value="size:asc">کمترین حجم</option>
                <option value="downloads:desc">بیشترین دانلود</option>
                <option value="downloads:asc">کمترین دانلود</option>
              </select>
              <button @click="fetchFiles(1)" class="btn-primary whitespace-nowrap">جستجو</button>
            </div>
            <div class="flex flex-col md:flex-row gap-3 items-end">
              <div class="flex-1 flex gap-3 items-end">
                <div class="flex-1">
                  <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">تاریخ آپلود از</label>
                  <input
                    v-model="fileFromDate"
                    @change="fetchFiles(1)"
                    type="date"
                    class="input-field w-full"
                  />
                </div>
                <div class="flex-1">
                  <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">تاریخ آپلود تا</label>
                  <input
                    v-model="fileToDate"
                    @change="fetchFiles(1)"
                    type="date"
                    class="input-field w-full"
                  />
                </div>
              </div>
              <div class="flex-1 flex gap-3 items-end">
                <div class="flex-1">
                  <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">دانلود از تاریخ</label>
                  <input
                    v-model="downloadFromDate"
                    @change="fetchFiles(1)"
                    type="date"
                    class="input-field w-full"
                  />
                </div>
                <div class="flex-1">
                  <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">دانلود تا تاریخ</label>
                  <input
                    v-model="downloadToDate"
                    @change="fetchFiles(1)"
                    type="date"
                    class="input-field w-full"
                  />
                </div>
              </div>
            </div>
          </div>

          <!-- Files Table -->
          <div class="card overflow-x-auto">
            <div v-if="isLoadingFiles" class="text-center py-12">
              <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-primary-600 border-t-transparent"></div>
              <p class="mt-3 text-gray-500">در حال بارگذاری...</p>
            </div>

            <div v-else-if="files.length === 0" class="text-center py-12 text-gray-500">
              <p>فایلی یافت نشد</p>
            </div>

            <table v-else class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                  <th class="text-right py-3 px-2 font-medium text-gray-500 dark:text-gray-400">شناسه</th>
                  <th class="text-right py-3 px-2 font-medium text-gray-500 dark:text-gray-400">نام فایل</th>
                  <th class="text-right py-3 px-2 font-medium text-gray-500 dark:text-gray-400">نوع</th>
                  <th class="text-right py-3 px-2 font-medium text-gray-500 dark:text-gray-400">حجم</th>
                  <th class="text-right py-3 px-2 font-medium text-gray-500 dark:text-gray-400">دانلودها</th>
                  <th class="text-right py-3 px-2 font-medium text-gray-500 dark:text-gray-400">میزبان</th>
                  <th class="text-right py-3 px-2 font-medium text-gray-500 dark:text-gray-400">مالک</th>
                  <th class="text-right py-3 px-2 font-medium text-gray-500 dark:text-gray-400">تاریخ</th>
                  <th class="text-right py-3 px-2 font-medium text-gray-500 dark:text-gray-400">عملیات</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="file in files"
                  :key="file.id"
                  class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                >
                  <td class="py-3 px-2 text-gray-500">#{{ file.id }}</td>
                  <td class="py-3 px-2 font-medium max-w-xs truncate" :title="file.filename">{{ file.filename }}</td>
                  <td class="py-3 px-2">
                    <span class="px-2 py-0.5 bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 rounded text-xs font-bold uppercase">{{ file.type }}</span>
                  </td>
                  <td class="py-3 px-2 text-gray-600 dark:text-gray-400">{{ file.size_formatted }}</td>
                  <td class="py-3 px-2 text-gray-600 dark:text-gray-400">{{ file.download_count }}</td>
                  <td class="py-3 px-2 text-gray-600 dark:text-gray-400 max-w-[10rem] truncate" :title="file.host || ''">{{ file.host || '—' }}</td>
                  <td class="py-3 px-2">{{ file.owner_name }}</td>
                  <td class="py-3 px-2 text-gray-500 text-xs">{{ formatDate(file.created_at) }}</td>
                  <td class="py-3 px-2">
                    <div class="flex gap-1">
                      <button
                        type="button"
                        @click="copyFileUrl(file)"
                        title="کپی آدرس فایل"
                        class="px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded text-xs hover:bg-green-200 dark:hover:bg-green-900/50 transition-colors"
                      >
                        کپی آدرس
                      </button>
                      <button
                        @click="confirmDeleteFile(file)"
                        class="px-2 py-1 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded text-xs hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors"
                      >
                        حذف
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="filesPagination && filesPagination.total_pages > 1" class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
              <span class="text-sm text-gray-500">
                صفحه {{ filesPagination.current_page }} از {{ filesPagination.total_pages }}
                ({{ filesPagination.total_files }} فایل)
              </span>
              <div class="flex gap-2">
                <button
                  @click="fetchFiles(filesPagination.current_page - 1)"
                  :disabled="!filesPagination.has_prev"
                  class="btn-secondary text-sm disabled:opacity-50"
                >
                  قبلی
                </button>
                <button
                  @click="fetchFiles(filesPagination.current_page + 1)"
                  :disabled="!filesPagination.has_next"
                  class="btn-secondary text-sm disabled:opacity-50"
                >
                  بعدی
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Tab: Users -->
        <div v-if="activeTab === 'users'">
          <!-- Search + Add User -->
          <div class="card mb-4">
            <div class="flex flex-col md:flex-row gap-3 items-start md:items-center justify-between">
              <input
                v-model="userSearch"
                @input="debouncedFetchUsers"
                type="text"
                placeholder="جستجوی نام کاربری..."
                class="input-field flex-1"
              />
              <button @click="showCreateUserModal = true" class="btn-primary whitespace-nowrap">
                افزودن کاربر جدید
              </button>
            </div>
          </div>

          <!-- Users Table -->
          <div class="card overflow-x-auto">
            <div v-if="isLoadingUsers" class="text-center py-12">
              <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-primary-600 border-t-transparent"></div>
              <p class="mt-3 text-gray-500">در حال بارگذاری...</p>
            </div>

            <div v-else-if="users.length === 0" class="text-center py-12 text-gray-500">
              <p>کاربری یافت نشد</p>
            </div>

            <table v-else class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                  <th class="text-right py-3 px-2 font-medium text-gray-500 dark:text-gray-400">شناسه</th>
                  <th class="text-right py-3 px-2 font-medium text-gray-500 dark:text-gray-400">نام کاربری</th>
                  <th class="text-right py-3 px-2 font-medium text-gray-500 dark:text-gray-400">نقش</th>
                  <th class="text-right py-3 px-2 font-medium text-gray-500 dark:text-gray-400">فایل‌ها</th>
                  <th class="text-right py-3 px-2 font-medium text-gray-500 dark:text-gray-400">حجم مصرفی</th>
                  <th class="text-right py-3 px-2 font-medium text-gray-500 dark:text-gray-400">انواع مجاز</th>
                  <th class="text-right py-3 px-2 font-medium text-gray-500 dark:text-gray-400">تاریخ ثبت‌نام</th>
                  <th class="text-right py-3 px-2 font-medium text-gray-500 dark:text-gray-400">عملیات</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="u in users"
                  :key="u.id"
                  class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                >
                  <td class="py-3 px-2 text-gray-500">#{{ u.id }}</td>
                  <td class="py-3 px-2 font-medium">{{ u.username }}</td>
                  <td class="py-3 px-2">
                    <span
                      class="px-2 py-0.5 rounded text-xs font-medium"
                      :class="u.is_admin
                        ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400'
                        : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400'"
                    >
                      {{ u.is_admin ? 'ادمین' : 'کاربر' }}
                    </span>
                  </td>
                  <td class="py-3 px-2">{{ u.file_count }}</td>
                  <td class="py-3 px-2 text-gray-600 dark:text-gray-400">{{ u.total_size_formatted }}</td>
                  <td class="py-3 px-2">
                    <div class="flex flex-wrap gap-1 max-w-xs">
                      <span
                        v-for="ft in (u.allowedFileTypes || '').split(',').filter(Boolean)"
                        :key="ft"
                        class="px-1.5 py-0.5 bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 rounded text-xs"
                      >
                        {{ ft.trim().toUpperCase() }}
                      </span>
                    </div>
                  </td>
                  <td class="py-3 px-2 text-gray-500 text-xs">{{ formatDate(u.created_at) }}</td>
                  <td class="py-3 px-2">
                    <div class="flex gap-1">
                      <button
                        @click="openEditUserModal(u)"
                        class="px-2 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 rounded text-xs hover:bg-blue-200 dark:hover:bg-blue-900/50 transition-colors"
                      >
                        ویرایش
                      </button>
                      <button
                        @click="confirmDeleteUser(u)"
                        :disabled="u.id === user?.id"
                        class="px-2 py-1 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded text-xs hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                      >
                        حذف
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="usersPagination && usersPagination.total_pages > 1" class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
              <span class="text-sm text-gray-500">
                صفحه {{ usersPagination.current_page }} از {{ usersPagination.total_pages }}
                ({{ usersPagination.total_users }} کاربر)
              </span>
              <div class="flex gap-2">
                <button
                  @click="fetchUsers(usersPagination.current_page - 1)"
                  :disabled="!usersPagination.has_prev"
                  class="btn-secondary text-sm disabled:opacity-50"
                >
                  قبلی
                </button>
                <button
                  @click="fetchUsers(usersPagination.current_page + 1)"
                  :disabled="!usersPagination.has_next"
                  class="btn-secondary text-sm disabled:opacity-50"
                >
                  بعدی
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Tab: Delete Requests -->
        <div v-if="activeTab === 'delete-requests'">
          <!-- Filter -->
          <div class="card mb-4">
            <div class="flex flex-wrap gap-3 items-center">
              <span class="text-sm font-medium text-gray-600 dark:text-gray-400">فیلتر وضعیت:</span>
              <button
                v-for="s in deleteRequestStatuses"
                :key="s.value"
                @click="deleteRequestStatusFilter = s.value; fetchDeleteRequests(1)"
                class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors"
                :class="deleteRequestStatusFilter === s.value
                  ? 'bg-primary-600 text-white'
                  : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'"
              >
                {{ s.label }}
                <span v-if="s.value === 'pending' && pendingDeleteCount > 0" class="mr-1 px-1.5 py-0.5 bg-red-500 text-white rounded-full text-xs">{{ pendingDeleteCount }}</span>
              </button>
            </div>
          </div>

          <!-- Delete Requests Table -->
          <div class="card overflow-x-auto">
            <div v-if="isLoadingDeleteRequests" class="text-center py-12">
              <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-primary-600 border-t-transparent"></div>
              <p class="mt-3 text-gray-500">در حال بارگذاری...</p>
            </div>

            <div v-else-if="deleteRequests.length === 0" class="text-center py-12 text-gray-500">
              <svg class="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
              <p>هیچ درخواست حذفی یافت نشد</p>
            </div>

            <table v-else class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                  <th class="text-right py-3 px-2 font-medium text-gray-500 dark:text-gray-400">#</th>
                  <th class="text-right py-3 px-2 font-medium text-gray-500 dark:text-gray-400">نام فایل</th>
                  <th class="text-right py-3 px-2 font-medium text-gray-500 dark:text-gray-400">حجم</th>
                  <th class="text-right py-3 px-2 font-medium text-gray-500 dark:text-gray-400">کاربر</th>
                  <th class="text-right py-3 px-2 font-medium text-gray-500 dark:text-gray-400">دلیل</th>
                  <th class="text-right py-3 px-2 font-medium text-gray-500 dark:text-gray-400">وضعیت</th>
                  <th class="text-right py-3 px-2 font-medium text-gray-500 dark:text-gray-400">تاریخ</th>
                  <th class="text-right py-3 px-2 font-medium text-gray-500 dark:text-gray-400">عملیات</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="dr in deleteRequests"
                  :key="dr.id"
                  class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                >
                  <td class="py-3 px-2 text-gray-500">#{{ dr.id }}</td>
                  <td class="py-3 px-2 font-medium max-w-xs truncate" :title="dr.filename">{{ dr.filename }}</td>
                  <td class="py-3 px-2 text-gray-600 dark:text-gray-400">{{ dr.file_size_formatted }}</td>
                  <td class="py-3 px-2">{{ dr.username }}</td>
                  <td class="py-3 px-2 text-gray-600 dark:text-gray-400 max-w-48 truncate" :title="dr.reason || ''">{{ dr.reason || '—' }}</td>
                  <td class="py-3 px-2">
                    <span
                      class="px-2 py-0.5 rounded text-xs font-medium"
                      :class="{
                        'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400': dr.status === 'pending',
                        'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400': dr.status === 'approved',
                        'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400': dr.status === 'rejected',
                      }"
                    >
                      {{ dr.status === 'pending' ? 'در انتظار' : dr.status === 'approved' ? 'تایید شده' : 'رد شده' }}
                    </span>
                  </td>
                  <td class="py-3 px-2 text-gray-500 text-xs">{{ formatDate(dr.created_at) }}</td>
                  <td class="py-3 px-2">
                    <div v-if="dr.status === 'pending'" class="flex gap-1">
                      <button
                        @click="openApproveRejectModal(dr, 'approve')"
                        class="px-2 py-1 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 rounded text-xs hover:bg-green-200 dark:hover:bg-green-900/50 transition-colors"
                      >
                        تایید
                      </button>
                      <button
                        @click="openApproveRejectModal(dr, 'reject')"
                        class="px-2 py-1 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded text-xs hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors"
                      >
                        رد
                      </button>
                    </div>
                    <span v-else class="text-gray-400 text-xs">
                      {{ dr.admin_note ? dr.admin_note : '—' }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="deleteRequestsPagination && deleteRequestsPagination.total_pages > 1" class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
              <span class="text-sm text-gray-500">
                صفحه {{ deleteRequestsPagination.current_page }} از {{ deleteRequestsPagination.total_pages }}
                ({{ deleteRequestsPagination.total_requests }} درخواست)
              </span>
              <div class="flex gap-2">
                <button
                  @click="fetchDeleteRequests(deleteRequestsPagination.current_page - 1)"
                  :disabled="!deleteRequestsPagination.has_prev"
                  class="btn-secondary text-sm disabled:opacity-50"
                >
                  قبلی
                </button>
                <button
                  @click="fetchDeleteRequests(deleteRequestsPagination.current_page + 1)"
                  :disabled="!deleteRequestsPagination.has_next"
                  class="btn-secondary text-sm disabled:opacity-50"
                >
                  بعدی
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Tab: SSL -->
        <div v-if="activeTab === 'ssl'" class="space-y-6">
          <div class="card">
            <h2 class="text-xl font-bold mb-4">وضعیت گواهی SSL</h2>

            <div v-if="isLoadingSsl" class="text-center py-8">
              <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-primary-600 border-t-transparent"></div>
              <p class="mt-3 text-gray-500">در حال بارگذاری...</p>
            </div>

            <div v-else-if="sslError" class="text-center py-8 text-red-600 dark:text-red-400">
              <p>{{ sslError }}</p>
              <button @click="fetchSslInfo" class="btn-secondary mt-3 text-sm">تلاش مجدد</button>
            </div>

            <div v-else-if="sslInfo" class="space-y-4">
              <!-- Not configured -->
              <div v-if="!sslInfo.configured" class="p-4 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800">
                <div class="flex items-start gap-3">
                  <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                  </svg>
                  <div>
                    <p class="font-medium text-yellow-800 dark:text-yellow-300">گواهی SSL نصب نشده</p>
                    <p class="text-sm text-yellow-700 dark:text-yellow-400 mt-1">
                      <span v-if="sslInfo.has_cert && !sslInfo.has_key">فایل cert.pem موجود است اما key.pem یافت نشد.</span>
                      <span v-else-if="!sslInfo.has_cert && sslInfo.has_key">فایل key.pem موجود است اما cert.pem یافت نشد.</span>
                      <span v-else>هر دو فایل cert.pem و key.pem در پوشه ssl یافت نشدند.</span>
                    </p>
                  </div>
                </div>
              </div>

              <!-- Configured -->
              <template v-else>
                <!-- Expiry status banner -->
                <div
                  class="p-4 rounded-lg border"
                  :class="sslStatusClass"
                >
                  <div class="flex items-center justify-between flex-wrap gap-4">
                    <div class="flex items-center gap-3">
                      <svg class="w-8 h-8 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                      </svg>
                      <div>
                        <p class="text-2xl font-bold">{{ sslDaysRemainingText }}</p>
                        <p class="text-sm opacity-80">تا انقضای گواهی</p>
                      </div>
                    </div>
                    <div class="text-left">
                      <p class="text-sm opacity-80">تاریخ انقضا</p>
                      <p class="font-medium">{{ formatSslDate(sslInfo.expires_at) }}</p>
                    </div>
                  </div>
                </div>

                <!-- Certificate details -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-900/50">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">دامنه اصلی</p>
                    <p class="font-medium">{{ sslInfo.subject || '—' }}</p>
                  </div>
                  <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-900/50">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">صادرکننده</p>
                    <p class="font-medium">{{ sslInfo.issuer || '—' }}</p>
                  </div>
                  <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-900/50">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">تاریخ شروع اعتبار</p>
                    <p class="font-medium">{{ formatSslDate(sslInfo.valid_from) }}</p>
                  </div>
                  <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-900/50">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">تطابق کلید خصوصی</p>
                    <p class="font-medium" :class="sslInfo.key_matches ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                      {{ sslInfo.key_matches ? 'بله ✓' : 'خیر ✗' }}
                    </p>
                  </div>
                </div>

                <!-- SAN domains -->
                <div v-if="sslInfo.domains && sslInfo.domains.length > 0" class="p-4 rounded-lg bg-gray-50 dark:bg-gray-900/50">
                  <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">دامنه‌های پوشش‌داده‌شده</p>
                  <div class="flex flex-wrap gap-2">
                    <span
                      v-for="domain in sslInfo.domains"
                      :key="domain"
                      class="px-2 py-1 bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300 rounded text-sm"
                    >
                      {{ domain }}
                    </span>
                  </div>
                </div>
              </template>
            </div>
          </div>

          <!-- Upload form -->
          <div class="card">
            <h2 class="text-xl font-bold mb-2">آپلود گواهی SSL</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
              فایل‌های گواهی (cert.pem) و کلید خصوصی (key.pem) را آپلود کنید. پس از آپلود، nginx به‌صورت خودکار reload می‌شود.
            </p>

            <div v-if="sslUploadError" class="p-3 mb-4 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded-lg text-sm">
              {{ sslUploadError }}
            </div>

            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium mb-1">فایل گواهی (cert.pem)</label>
                <input
                  ref="certFileInput"
                  type="file"
                  accept=".pem,.crt,.cer"
                  @change="onCertFileChange"
                  class="block w-full text-sm text-gray-600 dark:text-gray-400
                    file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                    file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700
                    dark:file:bg-primary-900/30 dark:file:text-primary-300
                    hover:file:bg-primary-100 dark:hover:file:bg-primary-900/50"
                />
                <p v-if="selectedCertFile" class="text-xs text-gray-500 mt-1">{{ selectedCertFile.name }}</p>
              </div>

              <div>
                <label class="block text-sm font-medium mb-1">فایل کلید خصوصی (key.pem)</label>
                <input
                  ref="keyFileInput"
                  type="file"
                  accept=".pem,.key"
                  @change="onKeyFileChange"
                  class="block w-full text-sm text-gray-600 dark:text-gray-400
                    file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                    file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700
                    dark:file:bg-primary-900/30 dark:file:text-primary-300
                    hover:file:bg-primary-100 dark:hover:file:bg-primary-900/50"
                />
                <p v-if="selectedKeyFile" class="text-xs text-gray-500 mt-1">{{ selectedKeyFile.name }}</p>
              </div>

              <div class="flex flex-wrap gap-3 pt-2">
                <button
                  @click="uploadSsl"
                  :disabled="!selectedCertFile || !selectedKeyFile || isUploadingSsl"
                  class="btn-primary disabled:opacity-50"
                >
                  {{ isUploadingSsl ? 'در حال آپلود...' : 'آپلود و اعمال' }}
                </button>
                <button
                  v-if="sslInfo?.configured"
                  @click="reloadSsl"
                  :disabled="isReloadingSsl"
                  class="btn-secondary disabled:opacity-50"
                >
                  {{ isReloadingSsl ? 'در حال reload...' : 'Reload nginx' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- Approve/Reject Delete Request Modal -->
    <Teleport to="body">
      <div v-if="showApproveRejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showApproveRejectModal = false">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-sm p-6 space-y-4">
          <h3 class="text-xl font-bold" :class="approveRejectAction === 'approve' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
            {{ approveRejectAction === 'approve' ? 'تایید درخواست حذف' : 'رد درخواست حذف' }}
          </h3>
          <p class="text-gray-600 dark:text-gray-400 text-sm">
            <span v-if="approveRejectAction === 'approve'">
              آیا از حذف فایل <strong>{{ approveRejectTarget?.filename }}</strong> مطمئن هستید؟ این عمل غیرقابل بازگشت است.
            </span>
            <span v-else>
              درخواست حذف فایل <strong>{{ approveRejectTarget?.filename }}</strong> رد می‌شود و فایل حفظ خواهد شد.
            </span>
          </p>
          <div>
            <label class="block text-sm font-medium mb-1">یادداشت ادمین (اختیاری)</label>
            <input v-model="approveRejectNote" type="text" class="input-field w-full" placeholder="پیامی برای کاربر..." />
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button @click="showApproveRejectModal = false" class="btn-secondary">انصراف</button>
            <button
              @click="executeApproveReject"
              :disabled="isSubmitting"
              class="px-4 py-2 rounded-lg text-white transition-colors disabled:opacity-50"
              :class="approveRejectAction === 'approve' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'"
            >
              {{ isSubmitting ? 'در حال پردازش...' : (approveRejectAction === 'approve' ? 'تایید و حذف فایل' : 'رد درخواست') }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Create User Modal -->
    <Teleport to="body">
      <div v-if="showCreateUserModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showCreateUserModal = false">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
          <h3 class="text-xl font-bold">افزودن کاربر جدید</h3>

          <div v-if="modalError" class="p-3 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded-lg text-sm">
            {{ modalError }}
          </div>

          <div class="space-y-3">
            <div>
              <label class="block text-sm font-medium mb-1">نام کاربری</label>
              <input v-model="newUser.username" type="text" class="input-field w-full" placeholder="نام کاربری" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">رمز عبور</label>
              <input v-model="newUser.password" type="password" class="input-field w-full" placeholder="رمز عبور" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">انواع فایل مجاز</label>
              <input v-model="newUser.allowedFileTypes" type="text" class="input-field w-full" placeholder="jpg,png,pdf" />
              <p class="text-xs text-gray-500 mt-1">با کاما جدا کنید</p>
            </div>
            <div class="flex items-center gap-2">
              <input v-model="newUser.is_admin" type="checkbox" id="new-user-admin" class="rounded" />
              <label for="new-user-admin" class="text-sm">ادمین</label>
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button @click="showCreateUserModal = false" class="btn-secondary">انصراف</button>
            <button @click="createUser" :disabled="isSubmitting" class="btn-primary disabled:opacity-50">
              {{ isSubmitting ? 'در حال ایجاد...' : 'ایجاد کاربر' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Edit User Modal -->
    <Teleport to="body">
      <div v-if="showEditUserModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showEditUserModal = false">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
          <h3 class="text-xl font-bold">ویرایش کاربر: {{ editUser.username }}</h3>

          <div v-if="modalError" class="p-3 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded-lg text-sm">
            {{ modalError }}
          </div>

          <div class="space-y-3">
            <div>
              <label class="block text-sm font-medium mb-1">نام کاربری</label>
              <input v-model="editUser.username" type="text" class="input-field w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">رمز عبور جدید (اختیاری)</label>
              <input v-model="editUser.password" type="password" class="input-field w-full" placeholder="خالی بگذارید اگر تغییر نمی‌دهید" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">انواع فایل مجاز</label>
              <input v-model="editUser.allowedFileTypes" type="text" class="input-field w-full" />
              <p class="text-xs text-gray-500 mt-1">با کاما جدا کنید</p>
            </div>
            <div class="flex items-center gap-2">
              <input v-model="editUser.is_admin" type="checkbox" :id="'edit-user-admin-' + editUser.id" class="rounded" />
              <label :for="'edit-user-admin-' + editUser.id" class="text-sm">ادمین</label>
            </div>
          </div>

          <div class="flex justify-end gap-2 pt-2">
            <button @click="showEditUserModal = false" class="btn-secondary">انصراف</button>
            <button @click="updateUser" :disabled="isSubmitting" class="btn-primary disabled:opacity-50">
              {{ isSubmitting ? 'در حال ذخیره...' : 'ذخیره تغییرات' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Confirm Delete Modal -->
    <Teleport to="body">
      <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showDeleteModal = false">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-sm p-6 space-y-4">
          <h3 class="text-xl font-bold text-red-600 dark:text-red-400">تأیید حذف</h3>
          <p class="text-gray-600 dark:text-gray-400">{{ deleteMessage }}</p>
          <div class="flex justify-end gap-2 pt-2">
            <button @click="showDeleteModal = false" class="btn-secondary">انصراف</button>
            <button @click="executeDelete" :disabled="isSubmitting" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50">
              {{ isSubmitting ? 'در حال حذف...' : 'حذف' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Toast Notification -->
    <Teleport to="body">
      <Transition name="toast">
        <div v-if="toast.show" class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 px-6 py-3 rounded-xl shadow-lg text-sm font-medium"
          :class="toast.type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'"
        >
          {{ toast.message }}
        </div>
      </Transition>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, reactive, computed } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "../store/auth";
import { useThemeStore } from "../store/theme";
import {
  adminApi,
  type AdminFile,
  type AdminUser,
  type AdminStatsResponse,
  type AdminFilesResponse,
  type AdminUsersResponse,
  type AdminDeleteRequest,
  type AdminDeleteRequestsResponse,
  type AdminSslInfo,
} from "../api/admin";

const router = useRouter();
const authStore = useAuthStore();
const themeStore = useThemeStore();

const user = authStore.user;
const theme = themeStore.theme;

const toggleTheme = () => themeStore.toggleTheme();

const handleLogout = async () => {
  await authStore.logout();
  router.push("/login");
};

// ==================== TABS ====================
const tabs = [
  { id: "stats", label: "آمار" },
  { id: "files", label: "فایل‌ها" },
  { id: "users", label: "کاربران" },
  { id: "delete-requests", label: "درخواست‌های حذف" },
  { id: "ssl", label: "گواهی SSL" },
];
const activeTab = ref<string>("stats");

// ==================== TOAST ====================
const toast = reactive({ show: false, message: "", type: "success" as "success" | "error" });
let toastTimeout: ReturnType<typeof setTimeout>;

function showToast(message: string, type: "success" | "error" = "success") {
  clearTimeout(toastTimeout);
  toast.show = true;
  toast.message = message;
  toast.type = type;
  toastTimeout = setTimeout(() => {
    toast.show = false;
  }, 3000);
}

// ==================== STATS ====================
const adminStats = ref<AdminStatsResponse | null>(null);
const isLoadingStats = ref(false);
const statsError = ref("");

async function fetchStats() {
  isLoadingStats.value = true;
  statsError.value = "";
  const result = await adminApi.getStats();
  if (result.success && result.data) {
    adminStats.value = result.data;
  } else {
    statsError.value = result.error || "خطا در دریافت آمار";
  }
  isLoadingStats.value = false;
}

// ==================== FILES ====================
const files = ref<AdminFile[]>([]);
const filesPagination = ref<AdminFilesResponse["pagination"] | null>(null);
const isLoadingFiles = ref(false);
const fileSearch = ref("");
const fileOwnerFilter = ref("");
const fileTypeFilter = ref("");
const fileHostFilter = ref("");
const fileFromDate = ref("");
const fileToDate = ref("");
const downloadFromDate = ref("");
const downloadToDate = ref("");
const fileSortBy = ref("created_at:desc");

let filesDebounceTimer: ReturnType<typeof setTimeout>;
function debouncedFetchFiles() {
  clearTimeout(filesDebounceTimer);
  filesDebounceTimer = setTimeout(() => fetchFiles(1), 400);
}

async function fetchFiles(page = 1) {
  isLoadingFiles.value = true;
  const [sortField, sortDirection] = fileSortBy.value.split(":");
  const result = await adminApi.getFiles({
    page,
    limit: 30,
    search: fileSearch.value,
    owner_name: fileOwnerFilter.value || undefined,
    host: fileHostFilter.value || undefined,
    type: fileTypeFilter.value,
    from_date: fileFromDate.value || undefined,
    to_date: fileToDate.value || undefined,
    download_from_date: downloadFromDate.value || undefined,
    download_to_date: downloadToDate.value || undefined,
    sort_by: sortField,
    sort_dir: sortDirection,
  });
  if (result.success && result.data) {
    files.value = result.data.files;
    filesPagination.value = result.data.pagination;
  } else {
    showToast(result.error || "خطا در دریافت فایل‌ها", "error");
  }
  isLoadingFiles.value = false;
}

// ==================== USERS ====================
const users = ref<AdminUser[]>([]);
const usersPagination = ref<AdminUsersResponse["pagination"] | null>(null);
const isLoadingUsers = ref(false);
const userSearch = ref("");

let usersDebounceTimer: ReturnType<typeof setTimeout>;
function debouncedFetchUsers() {
  clearTimeout(usersDebounceTimer);
  usersDebounceTimer = setTimeout(() => fetchUsers(1), 400);
}

async function fetchUsers(page = 1) {
  isLoadingUsers.value = true;
  const result = await adminApi.getUsers({
    page,
    limit: 30,
    search: userSearch.value,
  });
  if (result.success && result.data) {
    users.value = result.data.users;
    usersPagination.value = result.data.pagination;
  } else {
    showToast(result.error || "خطا در دریافت کاربران", "error");
  }
  isLoadingUsers.value = false;
}

// ==================== CREATE USER ====================
const showCreateUserModal = ref(false);
const newUser = reactive({
  username: "",
  password: "",
  allowedFileTypes: "jpg,png,pdf",
  is_admin: false,
});
const modalError = ref("");
const isSubmitting = ref(false);

async function createUser() {
  modalError.value = "";
  isSubmitting.value = true;
  const result = await adminApi.createUser({
    username: newUser.username,
    password: newUser.password,
    allowedFileTypes: newUser.allowedFileTypes,
    is_admin: newUser.is_admin,
  });
  isSubmitting.value = false;
  if (result.success) {
    showCreateUserModal.value = false;
    newUser.username = "";
    newUser.password = "";
    newUser.allowedFileTypes = "jpg,png,pdf";
    newUser.is_admin = false;
    showToast("کاربر با موفقیت ایجاد شد");
    fetchUsers();
  } else {
    modalError.value = result.error || "خطا در ایجاد کاربر";
  }
}

// ==================== EDIT USER ====================
const showEditUserModal = ref(false);
const editUser = reactive({
  id: 0,
  username: "",
  password: "",
  allowedFileTypes: "",
  is_admin: false,
});

function openEditUserModal(u: AdminUser) {
  editUser.id = u.id;
  editUser.username = u.username;
  editUser.password = "";
  editUser.allowedFileTypes = u.allowedFileTypes || "";
  editUser.is_admin = u.is_admin;
  modalError.value = "";
  showEditUserModal.value = true;
}

async function updateUser() {
  modalError.value = "";
  isSubmitting.value = true;

  const payload: any = {
    username: editUser.username,
    allowedFileTypes: editUser.allowedFileTypes,
    is_admin: editUser.is_admin,
  };
  if (editUser.password) {
    payload.password = editUser.password;
  }

  const result = await adminApi.updateUser(editUser.id, payload);
  isSubmitting.value = false;
  if (result.success) {
    showEditUserModal.value = false;
    showToast("کاربر با موفقیت ویرایش شد");
    fetchUsers();
  } else {
    modalError.value = result.error || "خطا در ویرایش کاربر";
  }
}

// ==================== DELETE ====================
const showDeleteModal = ref(false);
const deleteMessage = ref("");
let deleteAction: (() => Promise<void>) | null = null;

function confirmDeleteFile(file: AdminFile) {
  deleteMessage.value = `آیا از حذف فایل "${file.filename}" مطمئن هستید؟ این عمل غیرقابل بازگشت است.`;
  deleteAction = async () => {
    const result = await adminApi.deleteFile(file.id);
    if (result.success) {
      showToast("فایل با موفقیت حذف شد");
      fetchFiles(filesPagination.value?.current_page || 1);
    } else {
      showToast(result.error || "خطا در حذف فایل", "error");
    }
  };
  showDeleteModal.value = true;
}

function confirmDeleteUser(u: AdminUser) {
  deleteMessage.value = `آیا از حذف کاربر "${u.username}" مطمئن هستید؟ فایل‌های او (${u.file_count} فایل) حفظ می‌شوند. این عمل غیرقابل بازگشت است.`;
  deleteAction = async () => {
    const result = await adminApi.deleteUser(u.id);
    if (result.success) {
      showToast(result.data?.message || "کاربر با موفقیت حذف شد");
      fetchUsers(usersPagination.value?.current_page || 1);
    } else {
      showToast(result.error || "خطا در حذف کاربر", "error");
    }
  };
  showDeleteModal.value = true;
}

async function executeDelete() {
  if (!deleteAction) return;
  isSubmitting.value = true;
  await deleteAction();
  isSubmitting.value = false;
  showDeleteModal.value = false;
  deleteAction = null;
}

// ==================== HELPERS ====================
async function copyFileUrl(file: AdminFile) {
  try {
    await navigator.clipboard.writeText(file.download_url);
    showToast("آدرس فایل کپی شد");
  } catch {
    showToast("کپی آدرس فایل انجام نشد", "error");
  }
}

function formatDate(dateString: string | undefined) {
  if (!dateString) return "-";
  const date = new Date(dateString);
  return new Intl.DateTimeFormat("fa-IR", {
    year: "numeric",
    month: "long",
    day: "numeric",
  }).format(date);
}

// ==================== DELETE REQUESTS ====================
const deleteRequests = ref<AdminDeleteRequest[]>([]);
const deleteRequestsPagination = ref<AdminDeleteRequestsResponse["pagination"] | null>(null);
const isLoadingDeleteRequests = ref(false);
const deleteRequestStatusFilter = ref<"" | "pending" | "approved" | "rejected">("pending");
const pendingDeleteCount = ref(0);

const deleteRequestStatuses = [
  { value: "pending" as const, label: "در انتظار" },
  { value: "approved" as const, label: "تایید شده" },
  { value: "rejected" as const, label: "رد شده" },
  { value: "" as const, label: "همه" },
];

async function fetchDeleteRequests(page = 1) {
  isLoadingDeleteRequests.value = true;
  const result = await adminApi.getDeleteRequests({
    status: deleteRequestStatusFilter.value || undefined,
    page,
    limit: 30,
  });
  if (result.success && result.data) {
    deleteRequests.value = result.data.requests;
    deleteRequestsPagination.value = result.data.pagination;
  } else {
    showToast(result.error || "خطا در دریافت درخواست‌های حذف", "error");
  }
  isLoadingDeleteRequests.value = false;
}

async function fetchPendingDeleteCount() {
  const result = await adminApi.getDeleteRequests({ status: "pending", limit: 1 });
  if (result.success && result.data) {
    pendingDeleteCount.value = result.data.pagination.total_requests;
  }
}

// Approve/Reject modal
const showApproveRejectModal = ref(false);
const approveRejectAction = ref<"approve" | "reject">("approve");
const approveRejectTarget = ref<AdminDeleteRequest | null>(null);
const approveRejectNote = ref("");

function openApproveRejectModal(dr: AdminDeleteRequest, action: "approve" | "reject") {
  approveRejectTarget.value = dr;
  approveRejectAction.value = action;
  approveRejectNote.value = "";
  showApproveRejectModal.value = true;
}

async function executeApproveReject() {
  if (!approveRejectTarget.value) return;
  isSubmitting.value = true;
  const id = approveRejectTarget.value.id;
  const note = approveRejectNote.value.trim() || undefined;
  let result;
  if (approveRejectAction.value === "approve") {
    result = await adminApi.approveDeleteRequest(id, note);
  } else {
    result = await adminApi.rejectDeleteRequest(id, note);
  }
  isSubmitting.value = false;
  if (result.success) {
    showApproveRejectModal.value = false;
    showToast(approveRejectAction.value === "approve" ? "فایل با موفقیت حذف شد" : "درخواست رد شد");
    fetchDeleteRequests(deleteRequestsPagination.value?.current_page || 1);
    fetchPendingDeleteCount();
  } else {
    showToast(result.error || "خطا در پردازش درخواست", "error");
  }
}

// ==================== SSL ====================
const sslInfo = ref<AdminSslInfo | null>(null);
const isLoadingSsl = ref(false);
const sslError = ref("");
const sslUploadError = ref("");
const isUploadingSsl = ref(false);
const isReloadingSsl = ref(false);
const selectedCertFile = ref<File | null>(null);
const selectedKeyFile = ref<File | null>(null);
const certFileInput = ref<HTMLInputElement | null>(null);
const keyFileInput = ref<HTMLInputElement | null>(null);

const sslStatusClass = computed(() => {
  if (!sslInfo.value?.configured) return "";
  if (sslInfo.value.is_expired) {
    return "bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-800 dark:text-red-300";
  }
  if (sslInfo.value.is_expiring_soon) {
    return "bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800 text-yellow-800 dark:text-yellow-300";
  }
  return "bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-800 dark:text-green-300";
});

const sslDaysRemainingText = computed(() => {
  if (!sslInfo.value?.configured) return "";
  const days = sslInfo.value.days_remaining ?? 0;
  if (sslInfo.value.is_expired) {
    const expiredDays = Math.abs(days);
    return `${expiredDays} روز پیش منقضی شده`;
  }
  if (days === 0) return "امروز منقضی می‌شود";
  return `${days} روز`;
});

async function fetchSslInfo() {
  isLoadingSsl.value = true;
  sslError.value = "";
  const result = await adminApi.getSslInfo();
  if (result.success && result.data) {
    sslInfo.value = result.data;
  } else {
    sslError.value = result.error || "خطا در دریافت اطلاعات SSL";
  }
  isLoadingSsl.value = false;
}

function onCertFileChange(e: Event) {
  const input = e.target as HTMLInputElement;
  selectedCertFile.value = input.files?.[0] ?? null;
  sslUploadError.value = "";
}

function onKeyFileChange(e: Event) {
  const input = e.target as HTMLInputElement;
  selectedKeyFile.value = input.files?.[0] ?? null;
  sslUploadError.value = "";
}

async function uploadSsl() {
  if (!selectedCertFile.value || !selectedKeyFile.value) return;
  sslUploadError.value = "";
  isUploadingSsl.value = true;
  const result = await adminApi.uploadSsl(selectedCertFile.value, selectedKeyFile.value);
  isUploadingSsl.value = false;
  if (result.success && result.data) {
    sslInfo.value = result.data.ssl;
    selectedCertFile.value = null;
    selectedKeyFile.value = null;
    if (certFileInput.value) certFileInput.value.value = "";
    if (keyFileInput.value) keyFileInput.value.value = "";
    const msg = result.data.reloaded
      ? "گواهی SSL با موفقیت آپلود و nginx reload شد"
      : "گواهی SSL آپلود شد. لطفاً proxy را restart کنید.";
    showToast(msg, result.data.reloaded ? "success" : "error");
  } else {
    sslUploadError.value = result.error || "خطا در آپلود گواهی";
  }
}

async function reloadSsl() {
  isReloadingSsl.value = true;
  const result = await adminApi.reloadSsl();
  isReloadingSsl.value = false;
  if (result.success) {
    showToast("nginx با موفقیت reload شد");
  } else {
    showToast(result.error || "خطا در reload nginx", "error");
  }
}

function formatSslDate(dateString: string | undefined) {
  if (!dateString) return "—";
  const date = new Date(dateString);
  return new Intl.DateTimeFormat("fa-IR", {
    year: "numeric",
    month: "long",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(date);
}

// ==================== INIT ====================
onMounted(() => {
  fetchStats();
  fetchFiles();
  fetchUsers();
  fetchDeleteRequests();
  fetchPendingDeleteCount();
  fetchSslInfo();
});
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translate(-50%, 20px);
}
</style>
