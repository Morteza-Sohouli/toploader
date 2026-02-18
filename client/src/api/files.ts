import { API_BASE_URL } from "./config";
import * as tus from "tus-js-client";

export interface UploadedFileInfo {
  id?: number;
  file_id?: number;
  original_name: string;
  stored_name: string;
  size: number;
  extension: string;
  mime_type?: string;
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

export interface PendingUpload {
  id: string;
  filename: string;
  size: number;
  tusUrl: string | null;
  token: string;
  createdAt: number;
  fingerprint: string;
}

const TUS_ENDPOINT = API_BASE_URL.replace("/api", "") + "/tus/";
const PENDING_UPLOADS_KEY = "tus_pending_uploads";
const TTL_MS = 24 * 60 * 60 * 1000; // 24 hours

function generateFingerprint(file: File): string {
  return `tus-${file.name}-${file.size}-${file.lastModified}`;
}

function loadPendingUploads(): PendingUpload[] {
  try {
    const raw = localStorage.getItem(PENDING_UPLOADS_KEY);
    if (!raw) return [];
    const items: PendingUpload[] = JSON.parse(raw);
    const now = Date.now();
    return items.filter((item) => now - item.createdAt < TTL_MS);
  } catch {
    return [];
  }
}

function savePendingUploads(items: PendingUpload[]): void {
  const now = Date.now();
  const valid = items.filter((item) => now - item.createdAt < TTL_MS);
  localStorage.setItem(PENDING_UPLOADS_KEY, JSON.stringify(valid));
}

function addPendingUpload(entry: PendingUpload): void {
  const items = loadPendingUploads();
  const existing = items.findIndex((i) => i.id === entry.id);
  if (existing >= 0) {
    items[existing] = entry;
  } else {
    items.push(entry);
  }
  savePendingUploads(items);
}

function updatePendingUploadUrl(id: string, tusUrl: string): void {
  const items = loadPendingUploads();
  const item = items.find((i) => i.id === id);
  if (item) {
    item.tusUrl = tusUrl;
    savePendingUploads(items);
  }
}

function removePendingUpload(id: string): void {
  const items = loadPendingUploads().filter((i) => i.id !== id);
  savePendingUploads(items);
}

async function getUploadToken(): Promise<string> {
  const res = await fetch(`${API_BASE_URL}/files/upload-token`, {
    method: "POST",
    credentials: "include",
  });
  if (!res.ok) {
    throw new Error("Failed to get upload token");
  }
  const data = await res.json();
  return data.token;
}

async function getTusResult(
  uploadId: string,
): Promise<UploadedFileInfo | null> {
  const res = await fetch(`${API_BASE_URL}/files/tus-result/${uploadId}`, {
    method: "GET",
    credentials: "include",
  });
  if (!res.ok) return null;
  const data = await res.json();
  return data.file ?? null;
}

export interface TusUploadHandle {
  upload: tus.Upload;
  pendingId: string;
  abort: () => void;
}

export const fileApi = {
  async startTusUpload(
    file: File,
    onProgress?: (progress: number, loaded: number, total: number) => void,
    onSuccess?: (fileInfo: UploadedFileInfo) => void,
    onError?: (error: UploadError) => void,
  ): Promise<TusUploadHandle> {
    const token = await getUploadToken();
    const pendingId = `${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
    const fingerprint = generateFingerprint(file);

    addPendingUpload({
      id: pendingId,
      filename: file.name,
      size: file.size,
      tusUrl: null,
      token,
      createdAt: Date.now(),
      fingerprint,
    });

    const upload = new tus.Upload(file, {
      endpoint: TUS_ENDPOINT,
      chunkSize: 100 * 1024 * 1024, // 100MB -- HDD friendly
      retryDelays: [0, 3000, 5000, 10000, 20000],
      metadata: {
        filename: file.name,
        filetype: file.type || "application/octet-stream",
        token: token,
      },
      storeFingerprintForResuming: true,
      removeFingerprintOnSuccess: true,
      fingerprint: (_file, _options) => Promise.resolve(fingerprint),
      withCredentials: true,

      onProgress: (bytesUploaded: number, bytesTotal: number) => {
        const pct = Math.round((bytesUploaded / bytesTotal) * 100);
        onProgress?.(pct, bytesUploaded, bytesTotal);
      },

      onSuccess: async () => {
        removePendingUpload(pendingId);

        const tusUrl = upload.url;
        if (tusUrl) {
          const uploadId = tusUrl.split("/").pop() || "";
          try {
            const fileInfo = await getTusResult(uploadId);
            if (fileInfo) {
              onSuccess?.(fileInfo);
              return;
            }
          } catch {
            // Fall through
          }
        }
        onSuccess?.({
          original_name: file.name,
          stored_name: file.name,
          size: file.size,
          extension: file.name.split(".").pop() || "",
          uploaded_at: new Date().toISOString(),
          download_url: "",
        });
      },

      onError: (err: tus.DetailedError) => {
        if (err.message?.includes("tus: upload was aborted")) {
          onError?.({
            type: "abort",
            message: "آپلود لغو شد",
            details: "عملیات آپلود توسط کاربر لغو شد.",
          });
          return;
        }

        if (!navigator.onLine) {
          onError?.({
            type: "offline",
            message: "اتصال اینترنت قطع شد",
            details: "در حین آپلود، اتصال اینترنت شما قطع شد.",
          });
          return;
        }

        const statusCode = err.originalResponse?.getStatus?.();
        let errorType: UploadError["type"] = "network";
        let message = "خطای شبکه";
        let details = err.message || "";

        if (statusCode) {
          errorType = "server";
          if (statusCode === 403) {
            message = "توکن آپلود نامعتبر یا منقضی شده";
            details = "لطفاً صفحه را رفرش کنید و دوباره تلاش کنید.";
          } else if (statusCode === 400) {
            message = "درخواست نامعتبر";
            try {
              const body = err.originalResponse?.getBody?.();
              if (body) {
                const parsed = JSON.parse(body);
                details = parsed.error || details;
              }
            } catch {
              // ignore
            }
          } else if (statusCode === 413) {
            message = "حجم فایل بیش از حد مجاز";
            details = "فایل انتخابی بیش از حد مجاز است.";
          } else if (statusCode >= 500) {
            message = "خطای سرور";
            details = "مشکلی در سرور پیش آمده است. لطفاً بعداً تلاش کنید.";
          }
        }

        onError?.({ type: errorType, message, statusCode, details });
      },

      onAfterResponse: (_req: tus.HttpRequest, res: tus.HttpResponse) => {
        const url = upload.url;
        if (url) {
          updatePendingUploadUrl(pendingId, url);
        }
        // Hook: check for rejection body from pre-create
        const status = res.getStatus();
        if (status >= 400 && status < 500) {
          const body = res.getBody();
          try {
            const parsed = JSON.parse(body);
            if (parsed.error) {
              onError?.({
                type: "server",
                message: parsed.error,
                statusCode: status,
              });
            }
          } catch {
            // ignore
          }
        }
      },
    });

    const handle: TusUploadHandle = {
      upload,
      pendingId,
      abort: () => {
        upload.abort(true);
      },
    };

    upload.findPreviousUploads().then((previousUploads) => {
      if (previousUploads.length > 0) {
        upload.resumeFromPreviousUpload(previousUploads[0]);
      }
      upload.start();
    });

    return handle;
  },

  resumeTusUpload(
    file: File,
    pendingUpload: PendingUpload,
    onProgress?: (progress: number, loaded: number, total: number) => void,
    onSuccess?: (fileInfo: UploadedFileInfo) => void,
    onError?: (error: UploadError) => void,
  ): TusUploadHandle {
    const upload = new tus.Upload(file, {
      endpoint: TUS_ENDPOINT,
      chunkSize: 100 * 1024 * 1024,
      retryDelays: [0, 3000, 5000, 10000, 20000],
      metadata: {
        filename: file.name,
        filetype: file.type || "application/octet-stream",
        token: pendingUpload.token,
      },
      storeFingerprintForResuming: true,
      removeFingerprintOnSuccess: true,
      fingerprint: (_file, _options) =>
        Promise.resolve(pendingUpload.fingerprint),
      uploadUrl: pendingUpload.tusUrl || undefined,
      withCredentials: true,

      onProgress: (bytesUploaded: number, bytesTotal: number) => {
        const pct = Math.round((bytesUploaded / bytesTotal) * 100);
        onProgress?.(pct, bytesUploaded, bytesTotal);
      },

      onSuccess: async () => {
        removePendingUpload(pendingUpload.id);

        const tusUrl = upload.url;
        if (tusUrl) {
          const uploadId = tusUrl.split("/").pop() || "";
          try {
            const fileInfo = await getTusResult(uploadId);
            if (fileInfo) {
              onSuccess?.(fileInfo);
              return;
            }
          } catch {
            // Fall through
          }
        }
        onSuccess?.({
          original_name: file.name,
          stored_name: file.name,
          size: file.size,
          extension: file.name.split(".").pop() || "",
          uploaded_at: new Date().toISOString(),
          download_url: "",
        });
      },

      onError: (err: tus.DetailedError) => {
        if (!navigator.onLine) {
          onError?.({
            type: "offline",
            message: "اتصال اینترنت قطع شد",
            details: "در حین آپلود، اتصال اینترنت شما قطع شد.",
          });
          return;
        }
        onError?.({
          type: "network",
          message: "خطا در ادامه آپلود",
          details: err.message || "",
        });
      },
    });

    const handle: TusUploadHandle = {
      upload,
      pendingId: pendingUpload.id,
      abort: () => {
        upload.abort(true);
      },
    };

    upload.findPreviousUploads().then((previousUploads) => {
      if (previousUploads.length > 0) {
        upload.resumeFromPreviousUpload(previousUploads[0]);
      }
      upload.start();
    });

    return handle;
  },

  getPendingUploads(): PendingUpload[] {
    return loadPendingUploads();
  },

  removePendingUpload(id: string): void {
    removePendingUpload(id);
  },

  cleanExpiredPendingUploads(): void {
    const items = loadPendingUploads(); // already filters expired
    savePendingUploads(items);
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
