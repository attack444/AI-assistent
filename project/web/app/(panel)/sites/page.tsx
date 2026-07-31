import { SitesPanel } from "@/components/SitesPanel";

export default function SitesPage() {
  return (
    <>
      <div className="page-head">
        <h1>Сайты</h1>
        <p>
          Хостинг на VPS: большие ZIP (WordPress) грузятся чанками. Файлы лежат в{" "}
          <span className="mono">/var/ai-helper/sites/имя/</span>. Если пусто — «Найти файлы».
        </p>
      </div>
      <SitesPanel />
    </>
  );
}
