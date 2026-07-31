import { Suspense } from "react";
import { ChatPanel } from "@/components/ChatPanel";

export default function ChatPage() {
  return (
    <>
      <div className="page-head">
        <h1>Чат</h1>
        <p>
          Ассистент на сервере (DeepSeek). Открой из карточки сайта — тогда правки идут в его файлы.
        </p>
      </div>
      <Suspense fallback={<div className="panel empty">Загрузка…</div>}>
        <ChatPanel />
      </Suspense>
    </>
  );
}
