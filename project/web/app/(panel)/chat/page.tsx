import { ChatPanel } from "@/components/ChatPanel";

export default function ChatPage() {
  return (
    <>
      <div className="page-head">
        <h1>Чат</h1>
        <p>Ассистент с доступом к инструментам на сервере. DeepSeek / Groq / Ollama через API.</p>
      </div>
      <ChatPanel />
    </>
  );
}
