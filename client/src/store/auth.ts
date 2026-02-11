import { reactive, computed } from "vue";
import { translateError } from "../utils/translations";
import { authApi, type User } from "../api";

interface AuthState {
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
}

const state = reactive<AuthState>({
  user: null,
  isAuthenticated: false,
  isLoading: false,
});

export const useAuthStore = () => {
  const user = computed(() => state.user);
  const isAuthenticated = computed(() => state.isAuthenticated);
  const isLoading = computed(() => state.isLoading);
  const allowedFileTypes = computed(
    () => state.user?.allowedFileTypes?.split(",") || [],
  );
  const isAdmin = computed(() => state.user?.is_admin ?? false);

  const checkAuth = async () => {
    try {
      state.isLoading = true;
      const userData = await authApi.checkAuth();
      if (userData) {
        state.user = userData;
        state.isAuthenticated = true;
      } else {
        state.user = null;
        state.isAuthenticated = false;
      }
    } catch (error) {
      console.error("Auth check failed:", error);
      state.user = null;
      state.isAuthenticated = false;
    } finally {
      state.isLoading = false;
    }
  };

  const login = async (username: string, password: string) => {
    try {
      state.isLoading = true;
      const result = await authApi.login(username, password);

      if (result.success) {
        await checkAuth();
        return { success: true };
      } else {
        throw new Error(translateError(result.error || "ورود ناموفق بود"));
      }
    } catch (error: any) {
      return {
        success: false,
        error: error.message || "خطا در ورود به سیستم",
      };
    } finally {
      state.isLoading = false;
    }
  };

  const register = async (
    username: string,
    password: string,
    allowedFileTypes: string = "jpg,png,pdf",
  ) => {
    try {
      state.isLoading = true;
      const result = await authApi.register(
        username,
        password,
        allowedFileTypes,
      );

      if (!result.success) {
        throw new Error(translateError(result.error || "ثبت‌نام ناموفق بود"));
      }

      return { success: true };
    } catch (error: any) {
      return {
        success: false,
        error: error.message || "خطا در ثبت‌نام",
      };
    } finally {
      state.isLoading = false;
    }
  };

  const logout = async () => {
    try {
      await authApi.logout();
    } catch (error) {
      console.error("Logout failed:", error);
    } finally {
      state.user = null;
      state.isAuthenticated = false;
    }
  };

  return {
    user,
    isAuthenticated,
    isLoading,
    isAdmin,
    allowedFileTypes,
    checkAuth,
    login,
    register,
    logout,
  };
};
