import { API_BASE_URL } from "./config";

export interface UploadedFileInfo {
  id: number;
  original_name: string;
  stored_name: string;
  size: number;
  extension: string;
  mime_type: string;
  uploaded_at: string;
  download_url: string;
}

export interface UploadResponse {
  file: UploadedFileInfo;
}

export interface UserFile {
  id: number;
  filename: string;
  original_filename: string;
  file_size: number;
  file_size_formatted: string;
  mime_type: string;
  file_path: string;
  /** Full secure download URL (md5 + expires). Use this instead of building from hash. */
  download_url: string;
  created_at: string;
  updated_at: string;
}

export type UploadError = {
  type:
    | "network"
    | "timeout"
    | "abort"
    | "server"
    | "parse"
    | "offline"
    | "unknown";
  message: string;
  statusCode?: number;
  details?: string;
};

export interface UserFilesResponse {
  files: UserFile[];
  pagination: {
    current_page: number;
    per_page: number;
    total_files: number;
    total_pages: number;
    has_next: boolean;
    has_prev: boolean;
  };
}

export const fileApi = {
  async uploadFile(
    file: File,
    onProgress?: (progress: number, loaded?: number, total?: number) => void,
  ): Promise<{
    success: boolean;
    data?: UploadResponse;
    error?: UploadError;
    xhr?: XMLHttpRequest;
  }> {
    return new Promise((resolve) => {
      // Check if online before attempting upload
      if (!navigator.onLine) {
        resolve({
          success: false,
          error: {
            type: "offline",
            message: "اتصال اینترنت قطع است",
            details:
              "لطفاً اتصال اینترنت خود را بررسی کنید و دوباره تلاش کنید.",
          },
        });
        return;
      }

      const formData = new FormData();
      formData.append("file", file);

      const xhr = new XMLHttpRequest();

      // Set timeout to 5 minutes for large files
      xhr.timeout = 300000;

      if (onProgress) {
        xhr.upload.addEventListener("progress", (e) => {
          if (e.lengthComputable) {
            const progress = Math.round((e.loaded / e.total) * 100);
            onProgress(progress, e.loaded, e.total);
          }
        });
      }

      xhr.addEventListener("load", () => {
        if (xhr.status === 201) {
          try {
            const response: UploadResponse = JSON.parse(xhr.responseText);
            resolve({ success: true, data: response, xhr });
          } catch (e) {
            resolve({
              success: false,
              error: {
                type: "parse",
                message: "خطا در پردازش پاسخ سرور",
                details: "پاسخ دریافتی از سرور قابل پردازش نیست.",
              },
              xhr,
            });
          }
        } else {
          let errorMessage = "خطا در آپلود فایل";
          let errorDetails = "";

          try {
            const response = JSON.parse(xhr.responseText);
            errorMessage = response.error || errorMessage;
          } catch (e) {
            // Can't parse response, use status code
            if (xhr.status === 0) {
              errorMessage = "خطا در برقراری ارتباط با سرور";
              errorDetails = "سرور پاسخگو نیست. لطفاً بعداً دوباره تلاش کنید.";
            } else if (xhr.status === 400) {
              errorMessage = "درخواست نامعتبر";
              errorDetails = "فایل ارسال شده معتبر نیست.";
            } else if (xhr.status === 401) {
              errorMessage = "نیاز به احراز هویت";
              errorDetails = "لطفاً دوباره وارد شوید.";
            } else if (xhr.status === 413) {
              errorMessage = "حجم فایل بیش از حد مجاز";
              errorDetails = "فایل انتخابی بیش از حد مجاز است.";
            } else if (xhr.status === 415) {
              errorMessage = "نوع فایل مجاز نیست";
              errorDetails = "فرمت فایل پشتیبانی نمی‌شود.";
            } else if (xhr.status === 500) {
              errorMessage = "خطای داخلی سرور";
              errorDetails =
                "مشکلی در سرور پیش آمده است. لطفاً بعداً تلاش کنید.";
            } else if (xhr.status === 502) {
              errorMessage = "سرور در دسترس نیست";
              errorDetails = "ارتباط با سرور برقرار نشد.";
            } else if (xhr.status === 503) {
              errorMessage = "سرویس موقتاً در دسترس نیست";
              errorDetails = "سرور در حال حاضر مشغول است.";
            } else if (xhr.status === 504) {
              errorMessage = "زمان انتظار تمام شد";
              errorDetails = "سرور به موقع پاسخ نداد.";
            }
          }

          resolve({
            success: false,
            error: {
              type: "server",
              message: errorMessage,
              statusCode: xhr.status,
              details: errorDetails,
            },
            xhr,
          });
        }
      });

      xhr.addEventListener("error", () => {
        if (!navigator.onLine) {
          resolve({
            success: false,
            error: {
              type: "offline",
              message: "اتصال اینترنت قطع شد",
              details: "در حین آپلود، اتصال اینترنت شما قطع شد.",
            },
            xhr,
          });
        } else {
          resolve({
            success: false,
            error: {
              type: "network",
              message: "خطای شبکه",
              details:
                "خطا در برقراری ارتباط با سرور. ممکن است سرور در دسترس نباشد یا فایروال مانع ارتباط شده باشد.",
            },
            xhr,
          });
        }
      });

      xhr.addEventListener("timeout", () => {
        resolve({
          success: false,
          error: {
            type: "timeout",
            message: "زمان آپلود به پایان رسید",
            details:
              "آپلود فایل بیش از حد طولانی شد. لطفاً اتصال اینترنت خود را بررسی کنید یا فایل کوچکتری انتخاب کنید.",
          },
          xhr,
        });
      });

      xhr.addEventListener("abort", () => {
        resolve({
          success: false,
          error: {
            type: "abort",
            message: "آپلود لغو شد",
            details: "عملیات آپلود توسط کاربر یا مرورگر لغو شد.",
          },
          xhr,
        });
      });

      xhr.open("POST", `${API_BASE_URL}/files/upload`);
      xhr.withCredentials = true;
      xhr.send(formData);
    });
  },

  async getUserFiles(
    page: number = 1,
    limit: number = 30,
    search?: string,
    fromDate?: string,
    toDate?: string,
  ): Promise<{
    success: boolean;
    data?: UserFilesResponse;
    error?: string;
  }> {
    try {
      const params = new URLSearchParams({
        page: page.toString(),
        limit: limit.toString(),
      });

      if (search && search.trim()) {
        params.append("search", search.trim());
      }
      if (fromDate && fromDate.trim()) {
        params.append("from_date", fromDate.trim());
      }
      if (toDate && toDate.trim()) {
        params.append("to_date", toDate.trim());
      }

      const response = await fetch(
        `${API_BASE_URL}/files/uploads?${params.toString()}`,
        {
          method: "GET",
          credentials: "include",
          headers: {
            "Content-Type": "application/json",
          },
        },
      );

      if (response.ok) {
        const data: UserFilesResponse = await response.json();
        return { success: true, data };
      } else {
        const error = await response.text();
        return { success: false, error };
      }
    } catch (error) {
      return { success: false, error: "Network error" };
    }
  },
};
