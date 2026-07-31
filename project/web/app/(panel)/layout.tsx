"use client";

import { Sidebar } from "@/components/Sidebar";
import { AuthGate } from "@/components/AuthGate";

export default function PanelLayout({ children }: { children: React.ReactNode }) {
  return (
    <AuthGate>
      <div className="app-shell">
        <Sidebar />
        <div className="main">{children}</div>
      </div>
    </AuthGate>
  );
}
