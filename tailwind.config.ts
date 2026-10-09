import type { Config } from "tailwindcss";

const config: Config = {
  content: ["./src/**/*.{ts,tsx}"],
  darkMode: "media",
  theme: {
    extend: {
      fontFamily: {
        sans: [
          "-apple-system", "BlinkMacSystemFont", "SF Pro Text", "Inter", "system-ui",
          "Segoe UI", "Roboto", "sans-serif",
        ],
        display: ["SF Pro Display", "-apple-system", "BlinkMacSystemFont", "Inter", "system-ui", "sans-serif"],
      },
      colors: {
        accent: { DEFAULT: "#0b4ea2", dark: "#6aa9ff" },
        brand: { DEFAULT: "#f58220", soft: "#fff3e6" },
        navy: { DEFAULT: "#0a1f44", 800: "#10306b" },
      },
      boxShadow: {
        card: "0 1px 2px rgba(15,23,42,.04), 0 8px 24px -8px rgba(15,23,42,.10)",
        lift: "0 2px 4px rgba(15,23,42,.05), 0 18px 40px -12px rgba(15,23,42,.18)",
      },
      borderRadius: { "4xl": "2rem" },
      keyframes: {
        pulseRing: {
          "0%": { boxShadow: "0 0 0 0 currentColor" },
          "70%": { boxShadow: "0 0 0 6px transparent" },
          "100%": { boxShadow: "0 0 0 0 transparent" },
        },
      },
      animation: { pulseRing: "pulseRing 2s ease-out infinite" },
    },
  },
  plugins: [],
};
export default config;
