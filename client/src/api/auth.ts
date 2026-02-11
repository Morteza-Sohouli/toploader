import { API_BASE_URL } from "./config";

export interface User {
  id: number;
  username: string;
  allowedFileTypes: string;
  is_admin: boolean;
  created_at: string;
  updated_at: string;
}

export const authApi = {
  async checkAuth(): Promise<User | null> {
    try {
      const response = await fetch(`${API_BASE_URL}/users/me`, {
        credentials: "include",
      });

      if (response.ok) {
        return await response.json();
      } else {
        return null;
      }
    } catch (error) {
      console.error("Auth check failed:", error);
      return null;
    }
  },

  async login(username: string, password: string): Promise<{ success: boolean; error?: string }> {
    try {
      const response = await fetch(`${API_BASE_URL}/users/login`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        credentials: "include",
        body: JSON.stringify({ username, password }),
      });

      const data = await response.json();

      if (!response.ok) {
        return { success: false, error: data.error || "ورود ناموفق بود" };
      }

      return { success: true };
    } catch (error: any) {
      return {
        success: false,
        error: error.message || "خطا در ورود به سیستم",
      };
    }
  },

  async register(
    username: string,
    password: string,
    allowedFileTypes: string = "jpg,png,pdf",
  ): Promise<{ success: boolean; error?: string }> {
    try {
      const response = await fetch(`${API_BASE_URL}/users/register`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ username, password, allowedFileTypes }),
      });

      const data = await response.json();

      if (!response.ok) {
        return { success: false, error: data.error || "ثبت‌نام ناموفق بود" };
      }

      return { success: true };
    } catch (error: any) {
      return {
        success: false,
        error: error.message || "خطا در ثبت‌نام",
      };
    }
  },

  async logout(): Promise<void> {
    try {
      await fetch(`${API_BASE_URL}/users/logout`, {
        method: "POST",
        credentials: "include",
      });
    } catch (error) {
      console.error("Logout failed:", error);
    }
  },
};