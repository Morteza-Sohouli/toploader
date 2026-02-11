// Error message translations
export const translateError = (error: string): string => {
  const translations: Record<string, string> = {
    // Authentication errors
    "Username/password missing": "نام کاربری یا رمز عبور وارد نشده است",
    "Invalid credentials": "نام کاربری یا رمز عبور اشتباه است",
    "User already exists": "این نام کاربری قبلاً ثبت شده است",
    "Not authenticated": "ابتدا وارد حساب کاربری خود شوید",
    "User not found": "کاربر یافت نشد",

    // File upload errors
    "No file uploaded": "هیچ فایلی آپلود نشده است",
    "Invalid file type": "نوع فایل مجاز نیست",
    "File too large": "حجم فایل بیش از حد مجاز است",
    "File save failed": "ذخیره فایل با خطا مواجه شد",
    "MIME type mismatch": "نوع MIME فایل با پسوند آن مطابقت ندارد",

    // Generic errors
    "Bad Request": "درخواست نامعتبر",
    Unauthorized: "دسترسی غیرمجاز",
    Forbidden: "دسترسی ممنوع",
    "Not Found": "پیدا نشد",
    "Internal Server Error": "خطای سرور داخلی",
    "Service Unavailable": "سرویس در دسترس نیست",

    // Network errors
    "Network Error": "خطای شبکه",
    "Connection failed": "اتصال برقرار نشد",
    Timeout: "زمان اتصال به پایان رسید",
    "خطا در برقراری ارتباط با سرور": "خطا در برقراری ارتباط با سرور",

    // Custom Persian messages (already translated)
    "ورود ناموفق بود": "ورود ناموفق بود",
    "خطا در ورود به سیستم": "خطا در ورود به سیستم",
    "ثبت‌نام ناموفق بود": "ثبت‌نام ناموفق بود",
    "خطا در ثبت‌نام": "خطا در ثبت‌نام",
    "خطا در آپلود فایل": "خطا در آپلود فایل",
  };

  return translations[error] || error; // Return Persian translation or original if not found
};
