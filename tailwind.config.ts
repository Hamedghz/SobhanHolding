import type { Config } from "tailwindcss";

const config: Config = {
  content: [
    "./app/**/*.{js,ts,jsx,tsx,mdx}",
    "./components/**/*.{js,ts,jsx,tsx,mdx}",
    "./data/**/*.{js,ts,jsx,tsx,mdx}",
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: [
          "Vazirmatn",
          "IRANYekan",
          "Tahoma",
          "Arial",
          "sans-serif",
        ],
      },
      colors: {
        brand: {
          primary: "#2563EB",
          hover: "#1D4ED8",
          accent: "#F59E0B",
        },
      },
    },
  },
  plugins: [],
};

export default config;
