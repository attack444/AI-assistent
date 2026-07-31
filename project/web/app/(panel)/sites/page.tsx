import { SitesPanel } from "@/components/SitesPanel";

export default function SitesPage() {
  return (
    <>
      <div className="page-head">
        <h1>Сайты</h1>
        <p>
          Перенеси сайт со старого хостинга: скачай ZIP → мастер ниже → открой{" "}
          <span className="mono">/sites/имя/</span>. Потом можно привязать домен.
        </p>
      </div>
      <SitesPanel />
    </>
  );
}
