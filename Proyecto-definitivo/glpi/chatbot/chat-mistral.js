(function () {
  "use strict";

  const CONFIG = {
    endpoint     : "/chat-api/chat",
    apiKey       : "ollama",
    model        : "gemma3:12b",
    systemPrompt : `Eres un agente de IA de soporte técnico de Inquiba. Tu función es ayudar a los usuarios con dudas relacionadas con el sistema GLPI y los procedimientos de soporte IT de la empresa.

PUEDES ayudar con:
- Consultar información de la base de conocimientos de GLPI
- Explicar cómo abrir, seguir o cerrar tickets de soporte
- Orientar sobre procedimientos y guías de la empresa
- Resolver dudas generales sobre herramientas IT aprobadas por Inquiba
- Indicar a qué departamento o persona dirigirse según el tipo de incidencia
- Informar sobre los formularios disponibles en el catálogo de servicios

NO PUEDES ni debes:
- Dar información sobre configuraciones de sistemas, servidores o redes
- Revelar datos de otros usuarios, tickets ajenos o información confidencial
- Proporcionar credenciales, contraseñas ni accesos de ningún tipo
- Responder preguntas que no tengan relación con el soporte IT de Inquiba
- Dar soporte de productos o servicios externos no gestionados por Inquiba

Si el usuario pregunta algo fuera de tu ámbito, respóndele amablemente indicando que no puedes ayudarle con eso y sugiérele que contacte con el equipo de soporte directamente.

Responde siempre en el idioma del usuario, de forma clara, concisa y profesional.`,
    title          : "Asistente GLPI",
    subtitle       : "Powered by Gemma",
    placeholder    : "Escribe tu consulta sobre GLPI...",
    welcomeMessage : "¡Hola! Soy tu asistente de soporte de Inquiba. ¿En qué puedo ayudarte hoy?",
  };

  let isOpen = false, isStreaming = false, unread = 0, timerInterval = null;
  const conversationHistory = [];

  const STYLES = `
    #glpi-chat-btn {
      position:fixed;bottom:28px;right:28px;width:54px;height:54px;border-radius:50%;
      background:#af3b22;border:none;cursor:pointer;box-shadow:0 4px 14px rgba(175,59,34,.45);
      display:flex;align-items:center;justify-content:center;z-index:9998;
      transition:transform .2s,box-shadow .2s;
    }
    #glpi-chat-btn:hover{transform:scale(1.07);box-shadow:0 6px 20px rgba(175,59,34,.55);}
    #glpi-chat-badge{position:absolute;top:-3px;right:-3px;width:18px;height:18px;background:#e74c3c;border-radius:50%;font-size:10px;color:#fff;display:flex;align-items:center;justify-content:center;font-family:sans-serif;font-weight:600;opacity:0;transition:opacity .2s;}
    #glpi-chat-window{position:fixed;bottom:94px;right:28px;width:360px;max-width:calc(100vw - 40px);height:500px;max-height:calc(100vh - 120px);background:#fff;border-radius:14px;box-shadow:0 8px 32px rgba(0,0,0,.18);display:flex;flex-direction:column;z-index:9999;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;transform:scale(.92) translateY(12px);opacity:0;pointer-events:none;transition:transform .22s cubic-bezier(.34,1.56,.64,1),opacity .18s ease;}
    #glpi-chat-window.open{transform:scale(1) translateY(0);opacity:1;pointer-events:all;}
    #glpi-chat-header{background:#af3b22;padding:14px 16px;display:flex;align-items:center;gap:10px;flex-shrink:0;}
    #glpi-chat-header-icon{width:36px;height:36px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    #glpi-chat-header-text{flex:1;min-width:0;}
    #glpi-chat-header-title{color:#fff;font-size:14px;font-weight:600;margin:0;line-height:1.3;}
    #glpi-chat-header-sub{color:rgba(255,255,255,.75);font-size:11px;margin:0;line-height:1.2;}
    #glpi-chat-close{background:transparent;border:none;cursor:pointer;color:rgba(255,255,255,.85);display:flex;padding:4px;border-radius:6px;transition:background .15s;}
    #glpi-chat-close:hover{background:rgba(255,255,255,.15);}
    #glpi-chat-messages{flex:1;overflow-y:auto;padding:16px 12px;display:flex;flex-direction:column;gap:10px;scroll-behavior:smooth;background:#f6f8fa;}
    #glpi-chat-messages::-webkit-scrollbar{width:4px;}
    #glpi-chat-messages::-webkit-scrollbar-thumb{background:#c8cfd8;border-radius:4px;}
    .gc-msg{display:flex;flex-direction:column;max-width:82%;gap:3px;}
    .gc-msg.user{align-self:flex-end;align-items:flex-end;}
    .gc-msg.bot{align-self:flex-start;align-items:flex-start;}
    .gc-bubble{padding:9px 13px;border-radius:14px;font-size:13px;line-height:1.5;word-break:break-word;}
    .gc-bubble ul{margin:6px 0 6px 16px;padding:0;}
    .gc-bubble li{margin-bottom:3px;}
    .gc-msg.user .gc-bubble{background:#af3b22;color:#fff;border-bottom-right-radius:4px;}
    .gc-msg.bot .gc-bubble{background:#fff;color:#2d3748;border:1px solid #e2e8f0;border-bottom-left-radius:4px;}
    .gc-time{font-size:10px;color:#9badb9;padding:0 4px;}
    #glpi-chat-typing{display:none;align-self:flex-start;background:#fff;border:1px solid #e2e8f0;border-radius:14px;border-bottom-left-radius:4px;padding:10px 14px;gap:5px;align-items:center;}
    #glpi-chat-typing.visible{display:flex;}
    #glpi-chat-typing span{width:7px;height:7px;background:#9badb9;border-radius:50%;animation:gc-bounce 1.2s ease-in-out infinite;}
    #glpi-chat-typing span:nth-child(2){animation-delay:.2s;}
    #glpi-chat-typing span:nth-child(3){animation-delay:.4s;}
    @keyframes gc-bounce{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-6px)}}
    .gc-timer{font-size:11px;color:#9badb9;margin-left:6px;}
    #glpi-chat-footer{padding:10px 12px;border-top:1px solid #e8edf2;background:#fff;display:flex;gap:8px;align-items:flex-end;flex-shrink:0;}
    #glpi-chat-input{flex:1;border:1px solid #d1d9e0;border-radius:10px;padding:9px 12px;font-size:13px;font-family:inherit;resize:none;outline:none;min-height:38px;max-height:100px;overflow-y:auto;line-height:1.45;color:#2d3748;background:#f9fafb;transition:border-color .15s,background .15s;}
    #glpi-chat-input:focus{border-color:#af3b22;background:#fff;}
    #glpi-chat-input::placeholder{color:#a0aec0;}
    #glpi-chat-send{width:38px;height:38px;background:#af3b22;border:none;border-radius:10px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .15s,transform .1s;}
    #glpi-chat-send:hover:not(:disabled){background:#8f2f1a;}
    #glpi-chat-send:active:not(:disabled){transform:scale(.94);}
    #glpi-chat-send:disabled{background:#a0aec0;cursor:not-allowed;}
    #glpi-chat-error{display:none;font-size:11px;color:#e74c3c;padding:0 12px 8px;text-align:center;}
    #glpi-chat-error.visible{display:block;}
  `;

  function now() { return new Date().toLocaleTimeString([],{hour:"2-digit",minute:"2-digit"}); }
  function autoResize(el) { el.style.height="auto"; el.style.height=Math.min(el.scrollHeight,100)+"px"; }
  function scrollBottom() { const m=document.getElementById("glpi-chat-messages"); if(m) m.scrollTop=m.scrollHeight; }

  function parseMarkdown(text) {
    return text
      .replace(/\*\*(.+?)\*\*/g, "<strong>$1</strong>")
      .replace(/`(.+?)`/g, "<code style='background:#f0f0f0;padding:1px 5px;border-radius:4px;font-family:monospace;font-size:12px'>$1</code>")
      .replace(/^\s*[-*]\s+(.+)/gm, "<li>$1</li>")
      .replace(/(<li>.*<\/li>)/gs, "<ul>$1</ul>")
      .replace(/\n/g, "<br>");
  }

  function appendMessage(role, text) {
    const msgs = document.getElementById("glpi-chat-messages");
    if (!msgs) return null;
    const wrap = document.createElement("div"); wrap.className = "gc-msg "+role;
    const bubble = document.createElement("div"); bubble.className = "gc-bubble";
    if (role === "bot") {
      bubble.innerHTML = parseMarkdown(text);
    } else {
      bubble.textContent = text;
    }
    const time = document.createElement("span"); time.className = "gc-time"; time.textContent = now();
    wrap.appendChild(bubble); wrap.appendChild(time); msgs.appendChild(wrap);
    scrollBottom(); return bubble;
  }

  function startWaitTimer() {
    const el = document.getElementById("glpi-chat-typing"); if (!el) return;
    let secs = 0, span = el.querySelector(".gc-timer");
    if (!span) { span = document.createElement("span"); span.className="gc-timer"; el.appendChild(span); }
    span.textContent = "";
    timerInterval = setInterval(() => { secs++; span.textContent = secs >= 5 ? secs+"s" : ""; }, 1000);
  }

  function stopWaitTimer() {
    if (timerInterval) { clearInterval(timerInterval); timerInterval = null; }
    const el = document.getElementById("glpi-chat-typing");
    const s = el && el.querySelector(".gc-timer"); if (s) s.textContent = "";
  }

  async function sendMessage(userText) {
    const sendBtn  = document.getElementById("glpi-chat-send");
    const typingEl = document.getElementById("glpi-chat-typing");
    const errorEl  = document.getElementById("glpi-chat-error");
    const inputEl  = document.getElementById("glpi-chat-input");

    isStreaming = true;
    if (sendBtn) sendBtn.disabled = true;
    if (inputEl) inputEl.disabled = true;
    if (errorEl) errorEl.classList.remove("visible");

    conversationHistory.push({ role: "user", content: userText });
    if (typingEl) typingEl.classList.add("visible");
    startWaitTimer(); scrollBottom();

    let botBubble = null, fullText = "", firstChunk = true;

    try {
      const response = await fetch(CONFIG.endpoint, {
        method : "POST",
        headers: { "Content-Type": "application/json", "Authorization": "Bearer " + CONFIG.apiKey },
        body   : JSON.stringify({
          model    : CONFIG.model,
          stream   : true,
          messages : [{ role: "system", content: CONFIG.systemPrompt }, ...conversationHistory],
        }),
      });

      if (!response.ok) {
        const err = await response.json().catch(() => ({}));
        throw new Error("HTTP " + response.status + ": " + (err.error?.message || response.statusText));
      }

      const reader = response.body.getReader();
      const decoder = new TextDecoder();

      while (true) {
        const { done, value } = await reader.read(); if (done) break;
        const lines = decoder.decode(value, { stream: true }).split("\n").filter(l => l.startsWith("data: "));
        for (const line of lines) {
          const data = line.slice(6).trim(); if (data === "[DONE]") break;
          try {
            const content = JSON.parse(data).choices?.[0]?.delta?.content;
            if (content) {
              if (firstChunk) {
                firstChunk = false; stopWaitTimer();
                if (typingEl) typingEl.classList.remove("visible");
                botBubble = appendMessage("bot", "");
              }
              fullText += content;
              if (botBubble) botBubble.innerHTML = parseMarkdown(fullText);
              scrollBottom();
            }
          } catch (_) {}
        }
      }

      if (!fullText) fullText = "No recibí respuesta. Verifica la configuración de la API.";
      if (botBubble) botBubble.innerHTML = parseMarkdown(fullText);
      conversationHistory.push({ role: "assistant", content: fullText });

    } catch (err) {
      stopWaitTimer();
      if (typingEl) typingEl.classList.remove("visible");
      if (!botBubble) botBubble = appendMessage("bot", "");
      if (botBubble) botBubble.textContent = "Error: " + err.message;
      if (errorEl) { errorEl.textContent = "Fallo de conexión con el asistente IA."; errorEl.classList.add("visible"); }
    } finally {
      isStreaming = false;
      if (sendBtn) sendBtn.disabled = false;
      if (inputEl) { inputEl.disabled = false; inputEl.focus(); }
    }
  }

  function handleSend() {
    const inputEl = document.getElementById("glpi-chat-input");
    if (!inputEl || isStreaming) return;
    const text = inputEl.value.trim(); if (!text) return;
    inputEl.value = ""; autoResize(inputEl);
    appendMessage("user", text); sendMessage(text);
  }

  function toggleChat() {
    isOpen = !isOpen;
    const win = document.getElementById("glpi-chat-window");
    const badge = document.getElementById("glpi-chat-badge");
    if (!win) return;
    if (isOpen) {
      win.classList.add("open"); unread = 0;
      if (badge) badge.style.opacity = "0";
      setTimeout(() => { const i=document.getElementById("glpi-chat-input"); if(i) i.focus(); }, 220);
    } else { win.classList.remove("open"); }
  }

  function init() {
    if (document.getElementById("glpi-chat-btn")) return;
    const style = document.createElement("style");
    style.textContent = STYLES; document.head.appendChild(style);

    const btn = document.createElement("button");
    btn.id = "glpi-chat-btn"; btn.setAttribute("aria-label","Abrir asistente GLPI");
    btn.innerHTML = `<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg><span id="glpi-chat-badge">0</span>`;
    btn.addEventListener("click", toggleChat);

    const win = document.createElement("div");
    win.id = "glpi-chat-window"; win.setAttribute("role","dialog"); win.setAttribute("aria-label",CONFIG.title);
    win.innerHTML = `
      <div id="glpi-chat-header">
        <div id="glpi-chat-header-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
        <div id="glpi-chat-header-text"><p id="glpi-chat-header-title">${CONFIG.title}</p><p id="glpi-chat-header-sub">${CONFIG.subtitle}</p></div>
        <button id="glpi-chat-close" aria-label="Cerrar chat"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
      </div>
      <div id="glpi-chat-messages" role="log" aria-live="polite">
        <div id="glpi-chat-typing"><span></span><span></span><span></span></div>
      </div>
      <div id="glpi-chat-error" role="alert"></div>
      <div id="glpi-chat-footer">
        <textarea id="glpi-chat-input" rows="1" placeholder="${CONFIG.placeholder}" aria-label="Mensaje para el asistente"></textarea>
        <button id="glpi-chat-send" aria-label="Enviar mensaje"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg></button>
      </div>`;

    document.body.appendChild(btn); document.body.appendChild(win);
    appendMessage("bot", CONFIG.welcomeMessage);
    document.getElementById("glpi-chat-close").addEventListener("click", toggleChat);
    document.getElementById("glpi-chat-send").addEventListener("click", handleSend);
    document.getElementById("glpi-chat-input").addEventListener("keydown", e => { if (e.key==="Enter"&&!e.shiftKey){e.preventDefault();handleSend();} });
    document.getElementById("glpi-chat-input").addEventListener("input", e => autoResize(e.target));
    document.addEventListener("keydown", e => { if (e.key==="Escape"&&isOpen) toggleChat(); });
  }

  if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", init); } else { init(); }
})();
