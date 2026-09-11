import { API_BASE_URL } from "./config";

// ==================== TYPES ====================

export interface AdminFile {
  id: number;
  filename: string;
  type: string;
  size: number;
  size_formatted: string;
  owner_id: number;
  owner_name: string;
  download_count: number;
  /** Canonical file URL without authorization query values. */
  download_url: string;
  path: string;
  host: string | null;
  created_at: string;
  updated_at: string;
}

export interface AdminFilesResponse {
  files: AdminFile[];
  pagination: {
    current_page: number;
    per_page: number;
    total_files: number;
    total_pages: number;
    has_next: boolean;
    has_prev: boolean;
  };
}

export interface AdminUser {
  id: number;
  username: string;
  allowedFileTypes: string;
  is_admin: boolean;
  file_count: number;
  total_size: number;
  total_size_formatted: string;
  created_at: string;
  updated_at: string;
}

export interface AdminUsersResponse {
  users: AdminUser[];
  pagination: {
    current_page: number;
    per_page: number;
    total_users: number;
    total_pages: number;
    has_next: boolean;
    has_prev: boolean;
  };
}

export interface AdminDeleteRequest {
  id: number;
  file_id: number;
  filename: string;
  file_size: number;
  file_size_formatted: string;
  user_id: number;
  username: string;
  reason: string | null;
  status: "pending" | "approved" | "rejected";
  admin_note: string | null;
  created_at: string;
  updated_at: string;
}

export interface AdminDeleteRequestsResponse {
  requests: AdminDeleteRequest[];
  pagination: {
    current_page: number;
    per_page: number;
    total_requests: number;
    total_pages: number;
    has_next: boolean;
    has_prev: boolean;
  };
}

export interface AdminSslInfo {
  configured: boolean;
  has_cert: boolean;
  has_key: boolean;
  subject?: string | null;
  issuer?: string;
  domains?: string[];
  valid_from?: string;
  expires_at?: string;
  days_remaining?: number;
  is_expired?: boolean;
  is_expiring_soon?: boolean;
  key_matches?: boolean;
}

export interface AdminSslUploadResponse {
  success: boolean;
  reloaded: boolean;
  message: string;
  ssl: AdminSslInfo;
}

export interface AdminStatsResponse {
  files: {
    total_count: number;
    total_size: number;
    total_size_formatted: string;
    by_type: {
      type: string;
      count: number;
      total_size: number;
      total_size_formatted: string;
    }[];
    uploads_last_7_days: number;
    uploads_per_day: {
      date: string;
      count: number;
      total_size: number;
      total_size_formatted: string;
    }[];
  };
  users: {
    total_count: number;
    admin_count: number;
    top_by_files: {
      user_id: number;
      username: string;
      file_count: number;
      total_size: number;
      total_size_formatted: string;
    }[];
  };
}

// ==================== API ====================

async function request<T>(
  url: string,
  options: RequestInit = {},
): Promise<{ success: boolean; data?: T; error?: string }> {
  try {
    const response = await fetch(`${API_BASE_URL}${url}`, {
      credentials: "include",
      headers: {
        "Content-Type": "application/json",
        ...options.headers,
      },
      ...options,
    });

    const data = await response.json();

    if (!response.ok) {
      return { success: false, error: data.error || "خطای سرور" };
    }

    return { success: true, data };
  } catch (error: any) {
    return { success: false, error: error.message || "خطای شبکه" };
  }
}

export const adminApi = {
  // ==================== FILES ====================

  async getFiles(params: {
    page?: number;
    limit?: number;
    search?: string;
    owner_name?: string;
    host?: string;
    type?: string;
    from_date?: string;
    to_date?: string;
    download_from_date?: string;
    download_to_date?: string;
    sort_by?: string;
    sort_dir?: string;
  } = {}): Promise<{ success: boolean; data?: AdminFilesResponse; error?: string }> {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set("page", params.page.toString());
    if (params.limit) searchParams.set("limit", params.limit.toString());
    if (params.search?.trim()) searchParams.set("search", params.search.trim());
    if (params.owner_name?.trim()) searchParams.set("owner_name", params.owner_name.trim());
    if (params.host?.trim()) searchParams.set("host", params.host.trim());
    if (params.type?.trim()) searchParams.set("type", params.type.trim());
    if (params.from_date?.trim()) searchParams.set("from_date", params.from_date.trim());
    if (params.to_date?.trim()) searchParams.set("to_date", params.to_date.trim());
    if (params.download_from_date?.trim()) searchParams.set("download_from_date", params.download_from_date.trim());
    if (params.download_to_date?.trim()) searchParams.set("download_to_date", params.download_to_date.trim());
    if (params.sort_by) searchParams.set("sort_by", params.sort_by);
    if (params.sort_dir) searchParams.set("sort_dir", params.sort_dir);

    return request<AdminFilesResponse>(`/admin/files?${searchParams.toString()}`);
  },

  async deleteFile(id: number): Promise<{ success: boolean; data?: { message: string }; error?: string }> {
    return request(`/admin/files/${id}`, { method: "DELETE" });
  },

  // ==================== USERS ====================

  async getUsers(params: {
    page?: number;
    limit?: number;
    search?: string;
  } = {}): Promise<{ success: boolean; data?: AdminUsersResponse; error?: string }> {
    const searchParams = new URLSearchParams();
    if (params.page) searchParams.set("page", params.page.toString());
    if (params.limit) searchParams.set("limit", params.limit.toString());
    if (params.search?.trim()) searchParams.set("search", params.search.trim());

    return request<AdminUsersResponse>(`/admin/users?${searchParams.toString()}`);
  },

  async createUser(data: {
    username: string;
    password: string;
    allowedFileTypes?: string;
    is_admin?: boolean;
  }): Promise<{ success: boolean; data?: { message: string; user: any }; error?: string }> {
    return request(`/admin/users`, {
      method: "POST",
      body: JSON.stringify(data),
    });
  },

  async updateUser(
    id: number,
    data: {
      username?: string;
      password?: string;
      allowedFileTypes?: string;
      is_admin?: boolean;
    },
  ): Promise<{ success: boolean; data?: { message: string; user: any }; error?: string }> {
    return request(`/admin/users/${id}`, {
      method: "PUT",
      body: JSON.stringify(data),
    });
  },

  async deleteUser(id: number): Promise<{ success: boolean; data?: { message: string; orphaned_files: number }; error?: string }> {
    return request(`/admin/users/${id}`, { method: "DELETE" });
  },

  // ==================== STATS ====================

  async getStats(): Promise<{ success: boolean; data?: AdminStatsResponse; error?: string }> {
    return request<AdminStatsResponse>(`/admin/stats`);
  },

  // ==================== DELETE REQUESTS ====================

  async getDeleteRequests(params: {
    status?: "pending" | "approved" | "rejected";
    page?: number;
    limit?: number;
  } = {}): Promise<{ success: boolean; data?: AdminDeleteRequestsResponse; error?: string }> {
    const searchParams = new URLSearchParams();
    if (params.status) searchParams.set("status", params.status);
    if (params.page) searchParams.set("page", params.page.toString());
    if (params.limit) searchParams.set("limit", params.limit.toString());
    return request<AdminDeleteRequestsResponse>(`/admin/delete-requests?${searchParams.toString()}`);
  },

  async approveDeleteRequest(
    id: number,
    adminNote?: string,
  ): Promise<{ success: boolean; data?: { message: string }; error?: string }> {
    return request(`/admin/delete-requests/${id}/approve`, {
      method: "POST",
      body: JSON.stringify({ admin_note: adminNote || null }),
    });
  },

  async rejectDeleteRequest(
    id: number,
    adminNote?: string,
  ): Promise<{ success: boolean; data?: { message: string }; error?: string }> {
    return request(`/admin/delete-requests/${id}/reject`, {
      method: "POST",
      body: JSON.stringify({ admin_note: adminNote || null }),
    });
  },

  // ==================== SSL ====================

  async getSslInfo(): Promise<{ success: boolean; data?: AdminSslInfo; error?: string }> {
    return request<AdminSslInfo>(`/admin/ssl`);
  },

  async uploadSsl(
    cert: File,
    key: File,
  ): Promise<{ success: boolean; data?: AdminSslUploadResponse; error?: string }> {
    try {
      const formData = new FormData();
      formData.append("cert", cert);
      formData.append("key", key);

      const response = await fetch(`${API_BASE_URL}/admin/ssl`, {
        method: "POST",
        credentials: "include",
        body: formData,
      });

      const data = await response.json();

      if (!response.ok) {
        return { success: false, error: data.error || "خطای سرور" };
      }

      return { success: true, data };
    } catch (error: any) {
      return { success: false, error: error.message || "خطای شبکه" };
    }
  },

  async reloadSsl(): Promise<{ success: boolean; data?: { message: string; reloaded: boolean }; error?: string }> {
    return request(`/admin/ssl/reload`, { method: "POST" });
  },
};
