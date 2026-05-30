

const express = require("express");
const fetch   = require("node-fetch");
const app     = express();
app.use(express.json());

const GLPI_URL    = process.env.GLPI_URL;
const APP_TOKEN   = process.env.APP_TOKEN;
const USER_TOKEN  = process.env.USER_TOKEN;
const OLLAMA_URL  = process.env.OLLAMA_URL;
const OLLAMA_KEY  = process.env.OLLAMA_KEY;
const OLLAMA_MODEL= process.env.OLLAMA_MODEL || "gemma3:12b";

async function getSession() {
  const r = await fetch(`${GLPI_URL}/initSession`, {
    headers: { "Authorization": `user_token ${USER_TOKEN}`, "App-Token": APP_TOKEN }
  });
  const d = await r.json();
  if (!d.session_token) throw new Error("GLPI session error: " + JSON.stringify(d));
  return d.session_token;
}

async function killSession(st) {
  await fetch(`${GLPI_URL}/killSession`, {
    headers: { "Session-Token": st, "App-Token": APP_TOKEN }
  }).catch(() => {});
}

function stripHtml(html) {
  return (html || "")
    .replace(/<[^>]+>/g, " ")
    .replace(/&nbsp;/g, " ")
    .replace(/&amp;/g, "&")
    .replace(/&lt;/g, "<")
    .replace(/&gt;/g, ">")
    .replace(/\s+/g, " ")
    .trim()
    .slice(0, 800);
}

async function searchKnowledge(query, st) {
  const headers = { "Session-Token": st, "App-Token": APP_TOKEN };
  const byName = await fetch(`${GLPI_URL}/KnowbaseItem?searchText[name]=${encodeURIComponent(query)}&range=0-5`, { headers });
  const byContent = await fetch(`${GLPI_URL}/KnowbaseItem?searchText[answer]=${encodeURIComponent(query)}&range=0-3`, { headers });
  const results = [];
  if (byName.ok) { const d = await byName.json(); if (Array.isArray(d)) results.push(...d); }
  if (byContent.ok) { const d = await byContent.json(); if (Array.isArray(d)) d.forEach(i => { if (!results.find(r => r.id === i.id)) results.push(i); }); }
  if (results.length === 0) {
    const all = await fetch(`${GLPI_URL}/KnowbaseItem?range=0-20`, { headers });
    if (all.ok) { const d = await all.json(); if (Array.isArray(d)) results.push(...d); }
  }
  return results.slice(0, 5);
}

async function getArticle(id, st) {
  const r = await fetch(`${GLPI_URL}/KnowbaseItem/${id}`, { headers: { "Session-Token": st, "App-Token": APP_TOKEN } });
  if (!r.ok) return null;
  return r.json();
}

async function getForms(st) {
  const r = await fetch(`${GLPI_URL}/Glpi%5CForm%5CForm?range=0-100`, {
    headers: { "Session-Token": st, "App-Token": APP_TOKEN }
  });
  if (!r.ok) return [];
  const data = await r.json();
  if (!Array.isArray(data)) return [];
  return data
    .filter(f => f.is_active === 1 && f.is_deleted === 0 && f.is_draft === 0)
    .map(f => ({ id: f.id, name: f.name, description: f.description || "" }));
}

app.post("/chat", async (req, res) => {
  const { messages } = req.body;
  if (!messages) return res.status(400).json({ error: "No messages" });
  const lastUser = [...messages].reverse().find(m => m.role === "user");
  if (!lastUser) return res.status(400).json({ error: "No user message" });

  const msgLower = lastUser.content.toLowerCase();
  const wantsForms = /formulari|form|solicitud|incidencia|abrir|crear|nuevo|catálogo|servicio|disponible|qué puedo|que puedo/.test(msgLower);

  let knowledgeCtx = "";
  let formsCtx = "";
  let st = null;

  try {
    st = await getSession();

    // Buscar en base de conocimientos
    const articles = await searchKnowledge(lastUser.content, st);
    if (articles.length > 0) {
      knowledgeCtx = "\n\nArtículos de la base de conocimientos:\n";
      for (const art of articles) {
        const full = await getArticle(art.id, st);
        if (full) knowledgeCtx += `\n---\nTítulo: "${full.name}"\nContenido: ${stripHtml(full.answer)}\n`;
      }
    }

    // Cargar formularios si es relevante
    if (wantsForms) {
      const forms = await getForms(st);
      if (forms.length > 0) {
        formsCtx = "\n\nFormularios disponibles para los usuarios en el catálogo de servicios:\n";
        forms.forEach(f => {
          formsCtx += `- **${f.name}**${f.description ? `: ${f.description}` : ""}\n`;
        });
      }
    }
  } catch(e) {
    console.error("GLPI error:", e.message);
  } finally {
    if (st) killSession(st);
  }

  const extraCtx = knowledgeCtx + formsCtx;
  const enriched = messages.map(m => {
    if (m.role !== "system") return m;
    return {
      ...m,
      content: m.content + (extraCtx
        ? extraCtx + "\n\nIMPORTANTE: Basa tus respuestas en la información anterior cuando sea relevante."
        : "")
    };
  });

  let ollamaRes;
  try {
    ollamaRes = await fetch(`${OLLAMA_URL}/v1/chat/completions`, {
      method : "POST",
      headers: { "Content-Type": "application/json", "Authorization": `Bearer ${OLLAMA_KEY}` },
      body   : JSON.stringify({ model: OLLAMA_MODEL, stream: true, messages: enriched })
    });
  } catch(e) {
    return res.status(502).json({ error: e.message });
  }

  if (!ollamaRes.ok) {
    const txt = await ollamaRes.text();
    return res.status(ollamaRes.status).send(txt);
  }

  res.setHeader("Content-Type", "text/event-stream");
  res.setHeader("Cache-Control", "no-cache");
  res.setHeader("Connection", "keep-alive");
  ollamaRes.body.pipe(res);
});

app.listen(3000, () => console.log("✅ Chatbot API en puerto 3000"));

