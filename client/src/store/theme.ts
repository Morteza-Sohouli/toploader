import { ref } from "vue";

type Theme = "light" | "dark";

const theme = ref<Theme>("light");
const systemTheme = ref<Theme>("light");

const getSystemTheme = (): Theme => {
  return window.matchMedia("(prefers-color-scheme: dark)").matches
    ? "dark"
    : "light";
};

const applyTheme = (newTheme: Theme) => {
  const root = document.documentElement;

  if (newTheme === "dark") {
    root.classList.add("dark");
  } else {
    root.classList.remove("dark");
  }

  localStorage.setItem("theme", newTheme);
};

export const useThemeStore = () => {
  const initTheme = () => {
    // Check localStorage first
    const savedTheme = localStorage.getItem("theme") as Theme | null;

    // Get system preference
    systemTheme.value = getSystemTheme();

    // Use saved theme or fall back to light theme (not system)
    theme.value = savedTheme || "light";
    applyTheme(theme.value);

    // Watch for system theme changes
    const mediaQuery = window.matchMedia("(prefers-color-scheme: dark)");
    mediaQuery.addEventListener("change", (e) => {
      systemTheme.value = e.matches ? "dark" : "light";
      // Only auto-switch if user hasn't manually set a preference
      if (!localStorage.getItem("theme")) {
        theme.value = systemTheme.value;
        applyTheme(theme.value);
      }
    });
  };

  const toggleTheme = () => {
    theme.value = theme.value === "light" ? "dark" : "light";
    applyTheme(theme.value);
  };

  const setTheme = (newTheme: Theme) => {
    theme.value = newTheme;
    applyTheme(newTheme);
  };

  return {
    theme,
    systemTheme,
    initTheme,
    toggleTheme,
    setTheme,
  };
};
