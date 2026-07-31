"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { clearToken } from "@/lib/api";

const LINKS = [
  { href: "/chat", label: "Чат" },
  { href: "/files", label: "Файлы" },
  { href: "/sites", label: "Сайты" },
];

export function Sidebar() {
  const pathname = usePathname();
  const router = useRouter();

  return (
    <aside className="sidebar">
      <Link href="/" className="brand">
        AI Helper
        <span>панель сервера</span>
      </Link>
      <nav className="nav">
        {LINKS.map((link) => (
          <Link
            key={link.href}
            href={link.href}
            className={pathname.startsWith(link.href) ? "active" : undefined}
          >
            {link.label}
          </Link>
        ))}
      </nav>
      <button
        className="btn ghost small"
        type="button"
        style={{ alignSelf: "flex-start" }}
        onClick={() => {
          clearToken();
          router.push("/login");
        }}
      >
        Выйти
      </button>
      <p className="muted" style={{ marginTop: "auto", fontSize: "0.85rem" }}>
        5mb2 + ai на одном VPS. Редактор и чат — без FTP.
      </p>
    </aside>
  );
}
