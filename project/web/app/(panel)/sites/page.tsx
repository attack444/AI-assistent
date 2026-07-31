import { SitesPanel } from "@/components/SitesPanel";

export default function SitesPage() {
  return (
    <>
      <div className="page-head">
        <h1>Сайты</h1>
        <p>
          Два рабочих контура: <strong>5mb2</strong> (продакшен WP) и <strong>ai</strong> (витрина + среда).
          У карточки — «Файлы» / «Чат». HTTPS отложили. Корень:{" "}
          <span className="mono">/var/ai-helper/sites/</span>
        </p>
      </div>
      <SitesPanel />
    </>
  );
}
