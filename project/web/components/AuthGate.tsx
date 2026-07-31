"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { checkAuth, clearToken, getStatus, getToken } from "@/lib/api";

export function AuthGate({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const [ready, setReady] = useState(false);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const status = await getStatus();
        if (!status.auth_required) {
          if (!cancelled) setReady(true);
          return;
        }
        const token = getToken();
        if (!token) {
          router.replace("/login");
          return;
        }
        const check = await checkAuth();
        if (!check.ok) {
          clearToken();
          router.replace("/login");
          return;
        }
        if (!cancelled) setReady(true);
      } catch {
        router.replace("/login");
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [router]);

  if (!ready) {
    return <div className="empty">Проверяю доступ…</div>;
  }
  return <>{children}</>;
}
