import { Sidebar } from "@/components/Sidebar";

export default function PanelLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="app-shell">
      <Sidebar />
      <div className="main">{children}</div>
    </div>
  );
}
