import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "شرکت پخش سبحان",
  description: "سامانه داخلی شرکت پخش سبحان برای مدیریت کاربران، KPI، نظرسنجی و فایل‌ها.",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="fa" dir="rtl">
      <body>{children}</body>
    </html>
  );
}
