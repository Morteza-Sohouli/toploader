import { API_BASE_URL } from "./config";

export interface FilesByType {
  type: string;
  count: number;
  total_size: number;
  total_size_formatted: string;
}

export interface StatsResponse {
  file_count: number;
  total_size: number;
  total_size_formatted: string;
  files_by_type: FilesByType[];
  uploads_last_7_days: number;
  total_downloads: number;
  downloads_last_7_days: number;
}

export const statsApi = {
  async getStats(): Promise<{ success: boolean; data?: StatsResponse; error?: string }> {
    try {
      const response = await fetch(`${API_BASE_URL}/stats`, {
        method: "GET",
        credentials: "include",
        headers: {
          "Content-Type": "application/json",
        },
      });

      if (response.ok) {
        const data: StatsResponse = await response.json();
        return { success: true, data };
      } else {
        const error = await response.text();
        return { success: false, error };
      }
    } catch (error) {
      return { success: false, error: "خطا در دریافت آمار" };
    }
  },
};
