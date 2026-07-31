"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

const LINKS = [
  { href: "/chat", label: "Чат" },
  { href: "/files", label: "Файлы" },
  { href: "/sites", label: "Сайты" },
];

export function Sidebar() {
  const pathname = usePathname();

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
      <p className="muted" style={{ marginTop: "auto", fontSize: "0.85rem" }}>
        Файлы и сайты на VPS — без FTP-клиента.
      </p>
    </aside>
  );
}
