import { Suspense } from "react";
import { FileManager } from "@/components/FileManager";

export default function FilesPage() {
  return (
    <>
      <div className="page-head">
        <h1>Файлы</h1>
        <p>
          Редактор на сервере. Из «Сайты» открой нужную папку — правь код и проверяй сразу.
        </p>
      </div>
      <Suspense fallback={<div className="panel empty">Загрузка…</div>}>
        <FileManager />
      </Suspense>
    </>
  );
}
