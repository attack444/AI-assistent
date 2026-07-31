import { SitesPanel } from "@/components/SitesPanel";

export default function SitesPage() {
  return (
    <>
      <div className="page-head">
        <h1>Сайты</h1>
        <p>
          Создай сайт на VPS и загрузи ZIP с текущего хостинга. Статика отдаётся через Nginx по пути{" "}
          <span className="mono">/sites/имя/</span>.
        </p>
      </div>
      <SitesPanel />
    </>
  );
}
