import { FileManager } from "@/components/FileManager";

export default function FilesPage() {
  return (
    <>
      <div className="page-head">
        <h1>Файлы</h1>
        <p>Файловый менеджер сервера: папки сайтов и разрешённые workspace. Редактируй прямо в браузере.</p>
      </div>
      <FileManager />
    </>
  );
}
