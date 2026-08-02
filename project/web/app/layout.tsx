import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "NeoBrain — панель сервера",
  description: "Чат, файлы и сайты на VPS · NeoBrain",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="ru">
      <head>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="" />
        <link
          href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&family=IBM+Plex+Mono:wght@400;500&family=Sora:wght@700;800&display=swap"
          rel="stylesheet"
        />
      </head>
      <body>{children}</body>
    </html>
  );
}
