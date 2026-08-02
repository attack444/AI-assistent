"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { checkAuth, clearToken, getStatus, getToken } from "@/lib/api";

export function AuthGate({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const [ready, setReady] = useState(false);
  const [apiDown, setApiDown] = useState("");

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
          router.replace("/login/");
          return;
        }
        const check = await checkAuth();
        if (!check.ok) {
          clearToken();
          router.replace("/login/");
          return;
        }
        if (!cancelled) {
          setApiDown("");
          setReady(true);
        }
      } catch (e) {
        // Сеть/500 ≠ «не авторизован» — не выкидываем на login
        if (!cancelled) {
          setApiDown((e as Error).message || "API недоступен");
          setReady(true);
        }
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [router]);

  if (!ready) {
    return <div className="empty">Проверяю доступ…</div>;
  }
  if (apiDown) {
    return (
      <div className="empty" style={{ color: "#c44" }}>
        API недоступен: {apiDown}. Обнови страницу или проверь контейнер app.
      </div>
    );
  }
  return <>{children}</>;
}
