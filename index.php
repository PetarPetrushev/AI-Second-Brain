<?php
require_once __DIR__ . '/config.php';
init_auth_session();
$is_authenticated = is_authenticated();
$current_username = $_SESSION['username'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Your private Second Brain — capture thoughts, explore memories, and synthesise insights with AI.">
<title>Second Brain</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
/* ── Design Tokens ─────────────────────────────────────────────────────────── */
:root {
  --bg:           #0c0c0f;
  --surface:      #13131a;
  --surface2:     #1a1a24;
  --surface3:     #22222f;
  --border:       #2a2a3a;
  --border2:      #353548;
  --text:         #e8e8f0;
  --text2:        #9898b0;
  --text3:        #60607a;
  --accent:       #7c6af7;
  --accent-dim:   #4a3fa8;
  --accent-glow:  rgba(124,106,247,0.18);
  --green:        #34d399;
  --green-dim:    rgba(52,211,153,0.15);
  --red:          #f87171;
  --red-dim:      rgba(248,113,113,0.15);
  --amber:        #fbbf24;
  --amber-dim:    rgba(251,191,36,0.15);
  --blue:         #60a5fa;
  --blue-dim:     rgba(96,165,250,0.15);
  --radius:       10px;
  --radius-lg:    16px;
  --shadow:       0 4px 24px rgba(0,0,0,0.5);
  --transition:   all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
[data-theme="light"] {
  --bg:           #f5f5f8;
  --surface:      #ffffff;
  --surface2:     #f0f0f5;
  --surface3:     #e8e8f0;
  --border:       #dddde8;
  --border2:      #cacad8;
  --text:         #18181e;
  --text2:        #525268;
  --text3:        #8888a0;
  --accent-glow:  rgba(124,106,247,0.12);
  --shadow:       0 4px 24px rgba(0,0,0,0.1);
}

/* ── Reset & Base ──────────────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 16px; scroll-behavior: smooth; }
body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  line-height: 1.6;
  -webkit-font-smoothing: antialiased;
}
a { color: var(--accent); text-decoration: none; }
button { cursor: pointer; border: none; background: none; font-family: inherit; }
input, textarea, select {
  font-family: inherit;
  background: var(--surface2);
  border: 1px solid var(--border);
  color: var(--text);
  border-radius: var(--radius);
  padding: 10px 14px;
  font-size: 14px;
  transition: var(--transition);
  outline: none;
  width: 100%;
}
input:focus, textarea:focus, select:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-glow);
}
textarea { resize: vertical; min-height: 120px; }
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 3px; }

/* ── Layout ────────────────────────────────────────────────────────────────── */
#app { display: flex; flex-direction: column; min-height: 100vh; }

header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 20px;
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  position: sticky;
  top: 0;
  z-index: 100;
  backdrop-filter: blur(12px);
}
.logo {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 17px;
  font-weight: 700;
  color: var(--text);
  letter-spacing: -0.3px;
}
.logo-icon {
  width: 34px; height: 34px;
  background: linear-gradient(135deg, var(--accent), var(--accent-dim));
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 18px;
  box-shadow: 0 0 20px var(--accent-glow);
}
.header-actions { display: flex; align-items: center; gap: 10px; }
#theme-toggle {
  width: 36px; height: 36px;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  background: var(--surface2);
  border: 1px solid var(--border);
  font-size: 17px;
  transition: var(--transition);
  color: var(--text2);
}
#theme-toggle:hover { background: var(--surface3); color: var(--text); }

/* ── Navigation Tabs ───────────────────────────────────────────────────────── */
nav {
  display: flex;
  gap: 2px;
  padding: 10px 20px 0;
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  overflow-x: auto;
  scrollbar-width: none;
}
nav::-webkit-scrollbar { display: none; }
.tab-btn {
  padding: 9px 16px;
  border-radius: 8px 8px 0 0;
  font-size: 13px;
  font-weight: 500;
  color: var(--text2);
  transition: var(--transition);
  white-space: nowrap;
  border-bottom: 2px solid transparent;
  display: flex; align-items: center; gap: 6px;
}
.tab-btn:hover { color: var(--text); background: var(--surface2); }
.tab-btn.active { color: var(--accent); border-bottom-color: var(--accent); background: var(--surface2); }

/* ── Main Content ──────────────────────────────────────────────────────────── */
main { flex: 1; max-width: 900px; width: 100%; margin: 0 auto; padding: 28px 20px; }
.tab-panel { display: none !important; }
.tab-panel.active { display: block !important; }

/* ── Cards & Surfaces ──────────────────────────────────────────────────────── */
.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 22px;
  transition: var(--transition);
}
.card:hover { border-color: var(--border2); }

/* ── Buttons ───────────────────────────────────────────────────────────────── */
.btn {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 10px 18px;
  border-radius: var(--radius);
  font-size: 14px;
  font-weight: 500;
  transition: var(--transition);
  border: 1px solid transparent;
}
.btn-primary {
  background: var(--accent);
  color: #fff;
  box-shadow: 0 0 20px var(--accent-glow);
}
.btn-primary:hover { background: #8f7ef9; transform: translateY(-1px); box-shadow: 0 0 30px var(--accent-glow); }
.btn-primary:active { transform: translateY(0); }
.btn-secondary {
  background: var(--surface2);
  color: var(--text);
  border-color: var(--border);
}
.btn-secondary:hover { background: var(--surface3); border-color: var(--border2); }
.btn-danger {
  background: var(--red-dim);
  color: var(--red);
  border-color: rgba(248,113,113,0.3);
}
.btn-danger:hover { background: rgba(248,113,113,0.25); }
.btn-sm { padding: 6px 12px; font-size: 12px; }
.btn-icon {
  width: 36px; height: 36px; padding: 0;
  display: inline-flex; align-items: center; justify-content: center;
  border-radius: var(--radius);
  font-size: 16px;
}

/* ── Status Pills ──────────────────────────────────────────────────────────── */
.status-pill {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .05em;
  transition: var(--transition);
}
.pill-idle   { background: var(--surface2); color: var(--text3); }
.pill-recording { background: rgba(248,113,113,0.18); color: var(--red); animation: pulse-pill 1.5s ease-in-out infinite; }
.pill-processing { background: var(--amber-dim); color: var(--amber); }
.pill-saving { background: var(--blue-dim); color: var(--blue); }
.pill-saved  { background: var(--green-dim); color: var(--green); }
@keyframes pulse-pill { 0%,100%{opacity:1} 50%{opacity:.6} }

/* ── Capture Tab ───────────────────────────────────────────────────────────── */
.capture-wrap {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 24px;
}
.capture-header {
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}
.capture-header h1 { font-size: 22px; font-weight: 600; letter-spacing: -0.4px; }
.capture-header p { font-size: 14px; color: var(--text2); }

/* Voice button */
.voice-ring {
  position: relative;
  width: 120px; height: 120px;
  margin: 10px 0;
}
.voice-ring::before, .voice-ring::after {
  content: '';
  position: absolute;
  inset: -10px;
  border-radius: 50%;
  border: 2px solid transparent;
  transition: var(--transition);
}
.voice-ring.recording::before {
  border-color: rgba(248,113,113,0.4);
  animation: ring-expand 1.2s ease-in-out infinite;
}
.voice-ring.recording::after {
  border-color: rgba(248,113,113,0.2);
  animation: ring-expand 1.2s ease-in-out 0.4s infinite;
}
@keyframes ring-expand {
  0% { transform: scale(0.9); opacity: 1; }
  100% { transform: scale(1.3); opacity: 0; }
}
#voice-btn {
  width: 120px; height: 120px;
  border-radius: 50%;
  background: var(--surface2);
  border: 2px solid var(--border);
  font-size: 44px;
  display: flex; align-items: center; justify-content: center;
  transition: var(--transition);
  position: relative;
  z-index: 1;
}
#voice-btn:hover { background: var(--surface3); border-color: var(--border2); transform: scale(1.04); }
#voice-btn.recording {
  background: rgba(248,113,113,0.1);
  border-color: var(--red);
  box-shadow: 0 0 40px rgba(248,113,113,0.25);
}
.voice-label { font-size: 12px; color: var(--text3); font-weight: 500; }

/* Thought input */
.thought-input-wrap { width: 100%; max-width: 700px; }
#thought-input {
  min-height: 140px;
  padding: 16px;
  font-size: 15px;
  line-height: 1.7;
  border-radius: var(--radius-lg);
  resize: none;
  background: var(--surface);
  border: 1px solid var(--border);
}
#thought-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }
.char-count { text-align: right; font-size: 11px; color: var(--text3); margin-top: 4px; }

.capture-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  max-width: 700px;
}
.capture-shortcut { font-size: 11px; color: var(--text3); }

/* ── Visual Map Tab ────────────────────────────────────────────────────────── */
.map-controls {
  display: flex;
  gap: 12px;
  margin-bottom: 16px;
  flex-wrap: wrap;
  align-items: center;
  background: var(--surface);
  padding: 12px 16px;
  border-radius: var(--radius-lg);
  border: 1px solid var(--border);
}
.map-search-group {
  display: flex;
  gap: 8px;
  flex: 1;
  min-width: 260px;
}
.map-search-group input { flex: 2; }
.map-search-group select { flex: 1; min-width: 120px; }
.map-slider-group {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 12px;
  color: var(--text2);
  white-space: nowrap;
}
.map-slider-group input[type="range"] {
  width: 100px;
  accent-color: var(--accent);
}
.map-btn-group {
  display: flex;
  align-items: center;
  gap: 8px;
}
.map-container {
  position: relative;
  width: 100%;
  height: 620px;
  background: radial-gradient(circle at 50% 50%, #161622 0%, #0d0d12 100%);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  box-shadow: inset 0 0 40px rgba(0,0,0,0.5);
}
[data-theme="light"] .map-container {
  background: radial-gradient(circle at 50% 50%, #f0f0f8 0%, #e2e2ec 100%);
}
#map-canvas {
  width: 100%;
  height: 100%;
  display: block;
  cursor: grab;
}
#map-canvas:active {
  cursor: grabbing;
}
.map-legend {
  position: absolute;
  bottom: 12px;
  left: 12px;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  max-width: 70%;
  pointer-events: none;
}
.map-legend-item {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 8px;
  background: rgba(19, 19, 26, 0.82);
  backdrop-filter: blur(6px);
  border: 1px solid var(--border);
  border-radius: 12px;
  font-size: 11px;
  color: var(--text2);
  pointer-events: auto;
  cursor: pointer;
  transition: var(--transition);
}
.map-legend-item:hover {
  background: var(--surface2);
  color: var(--text);
  border-color: var(--accent);
}
.map-legend-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
}
.map-node-card {
  position: absolute;
  top: 16px;
  right: 16px;
  width: 300px;
  background: rgba(19, 19, 26, 0.92);
  backdrop-filter: blur(12px);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 16px;
  box-shadow: var(--shadow);
  z-index: 10;
  display: flex;
  flex-direction: column;
  gap: 8px;
  animation: map-card-in 0.2s ease-out;
}
[data-theme="light"] .map-node-card {
  background: rgba(255, 255, 255, 0.92);
}
@keyframes map-card-in {
  from { opacity: 0; transform: translateY(-8px); }
  to   { opacity: 1; transform: translateY(0); }
}
.map-card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 8px;
}
.map-card-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--text);
  line-height: 1.35;
}
.map-card-close {
  background: transparent;
  border: none;
  color: var(--text3);
  font-size: 14px;
  cursor: pointer;
  padding: 2px 4px;
  border-radius: 4px;
}
.map-card-close:hover { color: var(--text); background: var(--surface2); }
.map-card-date {
  font-size: 11px;
  color: var(--text3);
}
.map-card-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}
.map-card-preview {
  font-size: 12px;
  color: var(--text2);
  line-height: 1.5;
  max-height: 120px;
  overflow-y: auto;
  white-space: pre-wrap;
  word-break: break-word;
}
.map-accessible-list-wrap {
  position: absolute;
  bottom: 12px;
  right: 12px;
  max-width: 320px;
  background: rgba(19, 19, 26, 0.88);
  backdrop-filter: blur(8px);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  z-index: 5;
}
[data-theme="light"] .map-accessible-list-wrap {
  background: rgba(255, 255, 255, 0.88);
}
.map-acc-item {
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 4px;
  padding: 4px 8px;
  color: var(--text);
  cursor: pointer;
  text-align: left;
  transition: var(--transition);
}
.map-acc-item:hover {
  border-color: var(--accent);
  background: var(--surface3);
}

/* ── Timeline / Feed Tab ───────────────────────────────────────────────────── */
.feed-controls {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}
.feed-controls input, .feed-controls select { flex: 1; min-width: 120px; }
.sub-tabs { display: flex; gap: 4px; margin-bottom: 20px; }
.sub-tab {
  padding: 6px 14px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 500;
  color: var(--text2);
  border: 1px solid var(--border);
  background: var(--surface2);
  transition: var(--transition);
}
.sub-tab.active { background: var(--accent); color: #fff; border-color: var(--accent); }
.sub-tab:hover:not(.active) { background: var(--surface3); }

.entry-list { display: flex; flex-direction: column; gap: 10px; }
.entry-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 16px 18px;
  cursor: pointer;
  transition: var(--transition);
}
.entry-card:hover { border-color: var(--border2); background: var(--surface2); transform: translateY(-1px); }
.entry-card-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 6px; }
.entry-title { font-size: 14px; font-weight: 600; color: var(--text); line-height: 1.4; }
.entry-date { font-size: 11px; color: var(--text3); white-space: nowrap; flex-shrink: 0; }
.entry-preview { font-size: 13px; color: var(--text2); line-height: 1.6; margin-bottom: 10px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.tag-list { display: flex; flex-wrap: wrap; gap: 4px; }
.tag {
  display: inline-flex; align-items: center;
  padding: 2px 8px;
  border-radius: 20px;
  background: var(--accent-glow);
  color: var(--accent);
  font-size: 10px;
  font-weight: 600;
  letter-spacing: .04em;
  border: 1px solid rgba(124,106,247,0.3);
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
  color: var(--text3);
}
.empty-state .icon { font-size: 48px; margin-bottom: 12px; }
.empty-state h3 { font-size: 16px; color: var(--text2); margin-bottom: 6px; }
.empty-state p { font-size: 13px; }

.load-more { text-align: center; margin-top: 20px; }

/* Digest cards */
.digest-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 18px;
  cursor: pointer;
  transition: var(--transition);
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.digest-card:hover { border-color: var(--border2); background: var(--surface2); }
.digest-view-container {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  margin-top: 16px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}
.digest-view-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  background: var(--surface2);
  border-bottom: 1px solid var(--border);
  gap: 12px;
}
.digest-view-meta {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
.digest-view-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}
.digest-iframe {
  width: 100%;
  min-height: 650px;
  border: none;
  display: block;
  background: transparent;
  transition: height 0.2s ease;
}

/* ── Chat Tab ──────────────────────────────────────────────────────────────── */
.chat-layout { display: flex; flex-direction: column; height: calc(100vh - 230px); min-height: 500px; }
.chat-options {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
  padding: 12px 0 16px;
  border-bottom: 1px solid var(--border);
  margin-bottom: 16px;
}
.toggle-row { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--text2); cursor: pointer; }
.toggle-row input[type="checkbox"] { width: auto; accent-color: var(--accent); }
#chat-messages {
  flex: 1;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 4px 2px;
}
.chat-msg {
  display: flex;
  flex-direction: column;
  gap: 4px;
  max-width: 85%;
}
.chat-msg.user { align-self: flex-end; align-items: flex-end; }
.chat-msg.assistant { align-self: flex-start; align-items: flex-start; }
.msg-bubble {
  padding: 12px 16px;
  border-radius: 14px;
  font-size: 14px;
  line-height: 1.65;
  word-break: break-word;
}
.chat-msg.user .msg-bubble {
  background: var(--accent);
  color: #fff;
  border-bottom-right-radius: 4px;
  white-space: pre-wrap;
}
.chat-msg.assistant .msg-bubble {
  background: var(--surface2);
  color: var(--text);
  border-bottom-left-radius: 4px;
  border: 1px solid var(--border);
}
.msg-meta { font-size: 10px; color: var(--text3); }

/* Markdown typography inside assistant messages and entry modal */
.msg-bubble h1, .entry-detail h1 { font-size: 17px; font-weight: 700; margin: 12px 0 6px; color: var(--text); border-bottom: 1px solid var(--border); padding-bottom: 4px; }
.msg-bubble h2, .entry-detail h2 { font-size: 15px; font-weight: 600; margin: 10px 0 5px; color: var(--text); }
.msg-bubble h3, .entry-detail h3 { font-size: 14px; font-weight: 600; margin: 8px 0 4px; color: var(--text); }
.msg-bubble h4, .entry-detail h4 { font-size: 13px; font-weight: 600; margin: 6px 0 3px; color: var(--text); }
.msg-bubble p, .entry-detail p { margin: 0 0 8px; line-height: 1.65; }
.msg-bubble p:last-child, .entry-detail p:last-child { margin-bottom: 0; }
.msg-bubble ul, .msg-bubble ol, .entry-detail ul, .entry-detail ol { margin: 4px 0 10px; padding-left: 20px; }
.msg-bubble li, .entry-detail li { margin-bottom: 3px; line-height: 1.6; }
.msg-bubble blockquote, .entry-detail blockquote {
  margin: 8px 0;
  padding: 8px 14px;
  background: var(--surface3);
  border-left: 3px solid var(--accent);
  border-radius: 4px;
  font-style: italic;
  color: var(--text2);
}
.msg-bubble hr, .entry-detail hr {
  border: none;
  border-top: 1px solid var(--border);
  margin: 12px 0;
}
.msg-bubble code, .entry-detail code {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 12px;
  background: rgba(120, 120, 160, 0.15);
  border: 1px solid rgba(120, 120, 160, 0.2);
  padding: 1px 5px;
  border-radius: 4px;
  color: var(--accent);
}
.code-block-wrap {
  border: 1px solid var(--border);
  border-radius: 8px;
  overflow: hidden;
  margin: 10px 0;
  background: #14141c;
}
[data-theme="light"] .code-block-wrap {
  background: #1e1e28;
}
.code-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 5px 12px;
  background: rgba(255,255,255,0.06);
  font-size: 11px;
  color: #a1a1aa;
  border-bottom: 1px solid rgba(255,255,255,0.08);
}
.code-lang { text-transform: lowercase; font-weight: 500; font-family: monospace; }
.copy-btn {
  background: transparent;
  border: none;
  color: var(--accent);
  cursor: pointer;
  font-size: 11px;
  padding: 2px 6px;
  border-radius: 4px;
  transition: var(--transition);
}
.copy-btn:hover { background: rgba(255,255,255,0.1); color: #fff; }
.code-block-wrap pre {
  margin: 0;
  padding: 12px 14px;
  overflow-x: auto;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 12.5px;
  line-height: 1.55;
  color: #f4f4f5;
}
.code-block-wrap pre code {
  background: transparent;
  border: none;
  padding: 0;
  color: inherit;
}
.msg-bubble table, .entry-detail table {
  border-collapse: collapse;
  width: 100%;
  margin: 10px 0;
  font-size: 13px;
}
.msg-bubble th, .msg-bubble td, .entry-detail th, .entry-detail td {
  border: 1px solid var(--border);
  padding: 6px 10px;
  text-align: left;
}
.msg-bubble th, .entry-detail th {
  background: var(--surface3);
  font-weight: 600;
}
.msg-bubble a, .entry-detail a {
  color: var(--accent);
  text-decoration: underline;
  text-underline-offset: 2px;
}

/* Tool call badge */
.tool-badge {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 5px 10px;
  background: var(--amber-dim);
  border: 1px solid rgba(251,191,36,0.25);
  border-radius: 6px;
  font-size: 11px;
  color: var(--amber);
  margin: 2px 0;
  cursor: pointer;
  transition: var(--transition);
  max-width: 100%;
  text-align: left;
}
.tool-badge:hover { background: rgba(251,191,36,0.2); }
.tool-badge.expanded { border-radius: 6px 6px 0 0; }
.tool-detail {
  background: var(--surface3);
  border: 1px solid rgba(251,191,36,0.2);
  border-top: none;
  border-radius: 0 0 6px 6px;
  padding: 8px 10px;
  font-size: 11px;
  color: var(--text2);
  max-width: 100%;
  word-break: break-all;
  display: none;
}
.tool-badge.expanded + .tool-detail { display: block; }

/* Sources in chat */
.chat-sources-wrap {
  margin-top: 6px;
  display: flex;
  flex-direction: column;
  gap: 4px;
  width: 100%;
}
.sources-toggle-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 6px;
  font-size: 11px;
  font-weight: 500;
  color: var(--text2);
  cursor: pointer;
  width: fit-content;
  transition: var(--transition);
}
.sources-toggle-btn:hover {
  background: var(--surface3);
  color: var(--text);
  border-color: var(--border2);
}
.chat-sources-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-top: 4px;
  padding: 8px 10px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
}
.chat-source-item {
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 8px 10px;
  border-radius: 6px;
  background: var(--surface2);
  cursor: pointer;
  transition: var(--transition);
  border: 1px solid transparent;
}
.chat-source-item:hover {
  border-color: var(--accent);
  background: var(--surface3);
  transform: translateY(-1px);
}
.chat-source-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}
.chat-source-title {
  font-size: 12px;
  font-weight: 600;
  color: var(--text);
}
.chat-source-date {
  font-size: 10px;
  color: var(--text3);
  white-space: nowrap;
}
.chat-source-preview {
  font-size: 11px;
  color: var(--text2);
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.source-badge {
  display: inline-block;
  padding: 1px 6px;
  border-radius: 10px;
  font-size: 9px;
  font-weight: 600;
  text-transform: uppercase;
}
.source-badge.today { background: var(--green-dim); color: var(--green); }
.source-badge.past  { background: var(--accent-glow); color: var(--accent); }

.chat-input-row {
  display: flex;
  gap: 10px;
  margin-top: 14px;
  align-items: flex-end;
}
#chat-input {
  flex: 1;
  min-height: 48px;
  max-height: 160px;
  padding: 12px 14px;
  resize: none;
  font-size: 14px;
  border-radius: var(--radius);
}
#chat-send {
  height: 48px;
  padding: 0 18px;
  flex-shrink: 0;
  align-self: flex-end;
}
/* Thinking card & Reasoning styles */
.thinking-card {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 10px 16px;
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 12px;
  font-size: 13px;
  color: var(--text2);
  animation: shimmer-pulse 2s ease-in-out infinite;
  align-self: flex-start;
  margin: 4px 0;
}
.thinking-card .spin-icon {
  font-size: 15px;
  animation: spin 1.4s linear infinite;
  display: inline-block;
}
@keyframes spin { 100% { transform: rotate(360deg); } }
@keyframes shimmer-pulse {
  0%, 100% { border-color: var(--border); }
  50% { border-color: var(--accent); background: var(--surface3); }
}

.reasoning-box {
  margin-bottom: 8px;
  border-radius: 8px;
  background: rgba(20, 20, 30, 0.7);
  border: 1px solid var(--border);
  overflow: hidden;
  width: 100%;
}
[data-theme="light"] .reasoning-box {
  background: rgba(240, 240, 248, 0.85);
}
.reasoning-toggle {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 7px 12px;
  background: transparent;
  cursor: pointer;
  width: 100%;
  font-size: 12px;
  font-weight: 600;
  color: var(--accent);
  transition: var(--transition);
  border: none;
  text-align: left;
}
.chat-wrapper {
  display: flex;
  gap: 16px;
  height: calc(100vh - 190px);
  min-height: 520px;
  position: relative;
}
.chat-sidebar {
  width: 270px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
  overflow: hidden;
  transition: var(--transition);
}
.chat-sidebar.collapsed {
  display: none;
}
.chat-sidebar-header {
  padding: 12px 14px;
  border-bottom: 1px solid var(--border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.chat-sidebar-header h3 {
  font-size: 13px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--text2);
}
.chat-sidebar-search {
  padding: 8px 12px;
  border-bottom: 1px solid var(--border);
}
.chat-sidebar-search input {
  width: 100%;
  padding: 6px 10px;
  font-size: 12px;
  border-radius: var(--radius);
}
.chat-session-list {
  flex: 1;
  overflow-y: auto;
  padding: 6px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.chat-session-item {
  padding: 8px 10px;
  border-radius: var(--radius);
  border: 1px solid transparent;
  cursor: pointer;
  transition: var(--transition);
  display: flex;
  flex-direction: column;
  gap: 3px;
  background: transparent;
  text-align: left;
  position: relative;
}
.chat-session-item:hover {
  background: var(--surface2);
  border-color: var(--border);
}
.chat-session-item.active {
  background: var(--surface2);
  border-color: var(--accent);
}
.chat-session-title {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  padding-right: 20px;
}
.chat-session-meta {
  display: flex;
  justify-content: space-between;
  font-size: 10px;
  color: var(--text3);
}
.chat-session-preview {
  font-size: 11px;
  color: var(--text2);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.chat-session-actions {
  display: none;
  gap: 2px;
  position: absolute;
  right: 6px;
  top: 6px;
  background: var(--surface);
  padding: 2px 4px;
  border-radius: 4px;
  border: 1px solid var(--border);
  box-shadow: var(--shadow);
}
.chat-session-item:hover .chat-session-actions {
  display: flex;
}
.session-act-btn {
  background: transparent;
  border: none;
  font-size: 11px;
  cursor: pointer;
  padding: 2px 4px;
  border-radius: 3px;
  color: var(--text2);
}
.session-act-btn:hover {
  background: var(--surface3);
  color: var(--text);
}

.chat-layout {
  flex: 1;
  display: flex;
  flex-direction: column;
  height: 100%;
  min-width: 0;
}
.chat-topbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
  gap: 10px;
  flex-wrap: wrap;
}
.chat-title-group {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
}
.chat-active-title {
  font-size: 15px;
  font-weight: 600;
  color: var(--text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.chat-top-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}
.btn-icon {
  background: transparent;
  border: none;
  font-size: 13px;
  cursor: pointer;
  padding: 3px 6px;
  border-radius: 4px;
  color: var(--text2);
  transition: var(--transition);
}
.btn-icon:hover {
  background: var(--surface2);
  color: var(--text);
}
.msg-meta-row {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 3px;
}
.edit-turn-btn {
  background: transparent;
  border: none;
  font-size: 11px;
  cursor: pointer;
  opacity: 0.6;
  color: var(--text2);
  padding: 0 4px;
  border-radius: 3px;
  transition: var(--transition);
}
.edit-turn-btn:hover {
  opacity: 1;
  background: var(--surface3);
}

.chat-options {
  display: flex;
  gap: 16px;
  align-items: center;
  padding: 8px 12px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  margin-bottom: 10px;
  flex-wrap: wrap;
  font-size: 12px;
}
.chat-options .toggle-row { font-size: 12px; color: var(--text2); }
#chat-messages {
  flex: 1;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 16px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  min-height: 280px;
}
.chat-msg {
  display: flex;
  flex-direction: column;
  gap: 4px;
  max-width: 85%;
}
.reasoning-toggle:hover {
  background: var(--surface3);
}
.reasoning-content {
  padding: 10px 14px;
  font-size: 12px;
  line-height: 1.65;
  color: var(--text2);
  border-top: 1px solid var(--border);
  white-space: pre-wrap;
  word-break: break-word;
  max-height: 280px;
  overflow-y: auto;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

.typing-indicator {
  display: flex; align-items: center; gap: 5px;
  padding: 12px 16px;
  background: var(--surface2);
  border-radius: 14px 14px 14px 4px;
  border: 1px solid var(--border);
  align-self: flex-start;
}
.typing-dot {
  width: 6px; height: 6px;
  border-radius: 50%;
  background: var(--text3);
  animation: typing-bounce 1.2s ease-in-out infinite;
}
.typing-dot:nth-child(2) { animation-delay: 0.2s; }
.typing-dot:nth-child(3) { animation-delay: 0.4s; }
@keyframes typing-bounce { 0%,80%,100%{transform:translateY(0)} 40%{transform:translateY(-6px)} }

/* ── Memories Tab ──────────────────────────────────────────────────────────── */
.memories-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.memory-list { display: flex; flex-direction: column; gap: 10px; }
.memory-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 16px 18px;
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 14px;
  transition: var(--transition);
}
.memory-card:hover { border-color: var(--border2); }
.memory-body { flex: 1; }
.memory-text { font-size: 14px; line-height: 1.6; color: var(--text); margin-bottom: 4px; }
.memory-meta { font-size: 11px; color: var(--text3); }
.memory-actions { display: flex; gap: 6px; flex-shrink: 0; }

/* Memory modal */
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.7);
  backdrop-filter: blur(4px);
  z-index: 200;
  display: flex; align-items: center; justify-content: center;
  padding: 20px;
  opacity: 0;
  transition: opacity 0.2s;
  pointer-events: none;
}
.modal-overlay.open { opacity: 1; pointer-events: all; }
.modal {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 28px;
  width: 100%;
  max-width: 520px;
  max-height: 90vh;
  overflow-y: auto;
  transform: scale(0.95);
  transition: transform 0.2s;
}
.modal-overlay.open .modal { transform: scale(1); }
.modal h2 { font-size: 17px; font-weight: 600; margin-bottom: 18px; }
.modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 18px; }
.form-group { margin-bottom: 14px; }
.form-group label { display: block; font-size: 12px; font-weight: 600; color: var(--text2); text-transform: uppercase; letter-spacing: .04em; margin-bottom: 5px; }

/* Entry Detail Modal */
#entry-modal .modal { max-width: 760px; }
.entry-detail {
  font-size: 14px;
  line-height: 1.8;
  color: var(--text);
  white-space: pre-wrap;
  word-break: break-word;
  max-height: 65vh;
  overflow-y: auto;
  padding-right: 8px;
}

/* ── Settings Tab ──────────────────────────────────────────────────────────── */
.settings-section {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 22px;
  margin-bottom: 18px;
}
.settings-section h2 { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--text2); margin-bottom: 18px; padding-bottom: 10px; border-bottom: 1px solid var(--border); }
.settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
@media (max-width: 600px) { .settings-grid { grid-template-columns: 1fr; } }
.smtp-test-result {
  margin-top: 12px;
  padding: 12px 14px;
  border-radius: var(--radius);
  font-size: 12px;
  line-height: 1.7;
  display: none;
}
.smtp-test-result.success { background: var(--green-dim); color: var(--green); border: 1px solid rgba(52,211,153,0.25); display: block; }
.smtp-test-result.error { background: var(--red-dim); color: var(--red); border: 1px solid rgba(248,113,113,0.25); display: block; }

/* ── Toast Notifications ───────────────────────────────────────────────────── */
#toast-container { position: fixed; bottom: 24px; right: 24px; z-index: 999; display: flex; flex-direction: column; gap: 8px; }
.toast {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 12px 16px;
  font-size: 13px;
  color: var(--text);
  box-shadow: var(--shadow);
  animation: toast-in 0.25s cubic-bezier(0.4,0,0.2,1);
  min-width: 220px;
  max-width: 340px;
  display: flex; align-items: center; gap: 8px;
}
.toast.success { border-left: 3px solid var(--green); }
.toast.error   { border-left: 3px solid var(--red);   }
.toast.info    { border-left: 3px solid var(--accent); }
@keyframes toast-in { from{opacity:0;transform:translateX(20px)} to{opacity:1;transform:translateX(0)} }

/* ── Responsive ────────────────────────────────────────────────────────────── */
@media (max-width: 640px) {
  main { padding: 16px 12px; }
  .chat-layout { height: calc(100vh - 200px); }
  .chat-msg { max-width: 95%; }
  header { padding: 10px 14px; }
  nav { padding: 8px 12px 0; }
}

/* ── Misc utilities ────────────────────────────────────────────────────────── */
.flex { display: flex; }
.gap-2 { gap: 8px; }
.mt-1 { margin-top: 4px; }
.mt-2 { margin-top: 10px; }
.mt-3 { margin-top: 18px; }
.text-sm { font-size: 13px; }
.text-xs { font-size: 11px; }
.text-muted { color: var(--text2); }
.text-dim { color: var(--text3); }
.separator { border: none; border-top: 1px solid var(--border); margin: 20px 0; }
.spin { animation: spin 1s linear infinite; display: inline-block; }
@keyframes spin { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }

/* ── Auth / Login Screen ─────────────────────────────────────────────────── */
.auth-wrapper {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  background: radial-gradient(ellipse at 50% 20%, rgba(124,106,247,0.18) 0%, rgba(12,12,15,1) 75%);
}
.auth-card {
  width: 100%;
  max-width: 420px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 38px 32px;
  box-shadow: 0 16px 48px rgba(0,0,0,0.6);
  position: relative;
  overflow: hidden;
  backdrop-filter: blur(12px);
}
.auth-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--accent), #a78bfa, var(--accent));
}
.auth-header { text-align: center; margin-bottom: 28px; }
.auth-logo {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 58px;
  height: 58px;
  border-radius: 16px;
  background: var(--surface2);
  border: 1px solid var(--border2);
  font-size: 28px;
  margin-bottom: 16px;
  box-shadow: 0 0 24px var(--accent-glow);
}
.auth-title { font-size: 22px; font-weight: 700; color: #fff; letter-spacing: -0.02em; margin-bottom: 6px; }
.auth-desc { font-size: 13px; color: var(--text2); line-height: 1.5; }
.auth-error {
  background: var(--red-dim);
  border: 1px solid rgba(248,113,113,0.3);
  color: var(--red);
  padding: 10px 14px;
  border-radius: var(--radius);
  font-size: 13px;
  margin-bottom: 18px;
  display: none;
  align-items: center;
  gap: 8px;
}
.auth-session-badge {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  font-size: 12px;
  color: var(--text3);
  margin-top: 20px;
}
.user-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--text2);
  background: var(--surface2);
  border: 1px solid var(--border);
  padding: 4px 10px;
  border-radius: 999px;
}
@media (max-width: 500px) {
  .hide-mobile { display: none; }
}

/* Auto dark mode */
@media (prefers-color-scheme: light) {
  html:not([data-theme="dark"]) {
    --bg: #f5f5f8; --surface: #ffffff; --surface2: #f0f0f5; --surface3: #e8e8f0;
    --border: #dddde8; --border2: #cacad8;
    --text: #18181e; --text2: #525268; --text3: #8888a0;
    --accent-glow: rgba(124,106,247,0.12);
    --shadow: 0 4px 24px rgba(0,0,0,0.1);
  }
}
</style>
</head>
<body>
<?php if (!$is_authenticated): ?>
<div class="auth-wrapper">
  <div class="auth-card">
    <div class="auth-header">
      <div class="auth-logo">🧠</div>
      <h1 class="auth-title">Second Brain</h1>
      <p class="auth-desc">Sign in to access your personal thought logs and AI engine.</p>
    </div>

    <div class="auth-error" id="login-error"></div>

    <form id="login-form">
      <div class="form-group" style="margin-bottom:16px;">
        <label for="login-username">Username</label>
        <input type="text" id="login-username" placeholder="Enter username" autocomplete="username" required autofocus>
      </div>

      <div class="form-group" style="margin-bottom:22px;">
        <label for="login-password">Password</label>
        <input type="password" id="login-password" placeholder="Enter password" autocomplete="current-password" required>
      </div>

      <button type="submit" class="btn btn-primary" id="login-btn" style="width:100%; justify-content:center; padding:12px; font-weight:600; font-size:14px;">
        <span>Sign In ➔</span>
      </button>
    </form>

    <div class="auth-session-badge">
      <span>🔒</span> 7-Day Encrypted Session Active
    </div>
  </div>
</div>

<script>
const API = 'api.php';
document.getElementById('login-form')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const user = document.getElementById('login-username').value.trim();
  const pass = document.getElementById('login-password').value;
  const btn = document.getElementById('login-btn');
  const errBox = document.getElementById('login-error');
  
  errBox.style.display = 'none';
  btn.disabled = true;
  btn.innerHTML = '<span class="spin">⏳</span> Authenticating…';

  try {
    const res = await fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'login', username: user, password: pass }),
    });
    const json = await res.json();
    if (json.ok) {
      window.location.reload();
    } else {
      errBox.textContent = json.error || 'Invalid username or password.';
      errBox.style.display = 'flex';
      btn.disabled = false;
      btn.innerHTML = '<span>Sign In ➔</span>';
    }
  } catch (err) {
    errBox.textContent = err.message || 'Connection error.';
    errBox.style.display = 'flex';
    btn.disabled = false;
    btn.innerHTML = '<span>Sign In ➔</span>';
  }
});
</script>
</body>
</html>
<?php exit; endif; ?>

<div id="app">

<!-- ── Header ─────────────────────────────────────────────────────────────── -->
<header>
  <div class="logo">
    <div class="logo-icon">🧠</div>
    Second Brain
  </div>
  <div class="header-actions">
    <span class="user-badge"><span>👤</span> <?= htmlspecialchars($current_username) ?></span>
    <button id="theme-toggle" title="Toggle theme">🌙</button>
    <button id="logout-btn" class="btn btn-sm btn-secondary" title="Log out" style="gap:6px;padding:6px 12px;font-size:12px"><span>🚪</span> <span class="hide-mobile">Log Out</span></button>
  </div>
</header>

<!-- ── Navigation ─────────────────────────────────────────────────────────── -->
<nav>
  <button class="tab-btn active" data-tab="capture">✍️ Capture</button>
  <button class="tab-btn" data-tab="feed">📋 Feed</button>
  <button class="tab-btn" data-tab="map">🗺️ Thought Map</button>
  <button class="tab-btn" data-tab="chat">💬 Chat</button>
  <button class="tab-btn" data-tab="memories">⚡ Memories</button>
  <button class="tab-btn" data-tab="settings">⚙️ Settings</button>
</nav>

<!-- ── Main ───────────────────────────────────────────────────────────────── -->
<main>

  <!-- ══ Capture ══════════════════════════════════════════════════════════ -->
  <div class="tab-panel active" id="capture-panel" role="tabpanel">
    <div class="capture-wrap">

      <div class="capture-header">
        <h1>Capture a Thought</h1>
        <p>Speak or type — your ideas will be indexed and searchable.</p>
      </div>

      <div class="voice-ring" id="voice-ring">
        <button id="voice-btn" title="Start / stop voice recording">🎙️</button>
      </div>
      <div class="voice-label" id="voice-label">Tap to record</div>

      <span class="status-pill pill-idle" id="status-pill">Idle</span>

      <div class="thought-input-wrap">
        <textarea id="thought-input" placeholder="What's on your mind? Start typing or use the voice button above…" autofocus></textarea>
        <div class="char-count"><span id="char-count">0</span> characters</div>
      </div>

      <div class="capture-actions thought-input-wrap">
        <span class="capture-shortcut">Ctrl+Enter to save</span>
        <button class="btn btn-primary" id="save-btn">💾 Save Thought</button>
      </div>

    </div>
  </div>

  <!-- ══ Map ══════════════════════════════════════════════════════════════ -->
  <div class="tab-panel" id="map-panel" role="tabpanel">
    <div class="map-controls">
      <div class="map-search-group">
        <input type="text" id="map-search" placeholder="🔍 Search thoughts on map…">
        <select id="map-tag-filter">
          <option value="">All Tags</option>
        </select>
      </div>

      <div class="map-slider-group">
        <label for="map-sim-slider">Min Link Similarity: <span id="map-sim-val">0.35</span></label>
        <input type="range" id="map-sim-slider" min="0.20" max="0.85" step="0.05" value="0.35">
      </div>

      <div class="map-btn-group">
        <select id="map-color-mode">
          <option value="group">Color by Group</option>
          <option value="tag">Color by Tag</option>
          <option value="date">Color by Date</option>
        </select>
        <div class="sub-tabs" style="margin-bottom:0">
          <button class="sub-tab active" id="btn-view-map">🗺️ Map</button>
          <button class="sub-tab" id="btn-view-tree">🌳 Tree</button>
        </div>
        <button class="btn btn-secondary btn-sm" id="map-reset-btn" title="Reset View">🎯 Reset</button>
        <button class="btn btn-secondary btn-sm" id="map-pause-btn" title="Pause Physics">⏸️ Freeze</button>
      </div>
    </div>

    <!-- Tree View Container -->
    <div id="map-tree-container" class="card" style="display:none; margin-bottom:16px;">
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; border-bottom:1px solid var(--border); padding-bottom:10px;">
        <h3 style="font-size:15px; font-weight:700; color:var(--text)">🌳 Hierarchical Thought Tree</h3>
        <span class="text-xs text-dim" id="tree-summary-text">0 clusters</span>
      </div>
      <div id="tree-view-root" style="display:flex; flex-direction:column; gap:8px;"></div>
    </div>

    <div class="map-container" id="map-container">
      <canvas id="map-canvas" tabindex="0" aria-label="Interactive thought graph canvas"></canvas>
      <div class="map-legend" id="map-legend"></div>
      <div class="map-node-card" id="map-node-card" style="display:none">
        <div class="map-card-header">
          <span class="map-card-title" id="map-card-title">Title</span>
          <button class="map-card-close" id="map-card-close">✕</button>
        </div>
        <div class="map-card-date" id="map-card-date">Date</div>
        <div class="map-card-tags" id="map-card-tags"></div>
        <div class="map-card-preview" id="map-card-preview">Preview</div>
        <button class="btn btn-primary btn-sm mt-2" id="map-card-open">Open Thought ↗️</button>
      </div>

      <!-- Accessible screen-reader & keyboard outline list -->
      <div class="map-accessible-list-wrap" id="map-accessible-wrap">
        <details>
          <summary style="font-size:12px; color:var(--text2); cursor:pointer; padding:6px 10px;">📋 Accessible Thought List (<span id="map-list-count">0</span>)</summary>
          <ul id="map-accessible-list" style="max-height:180px; overflow-y:auto; padding:8px 16px; margin:0; font-size:12px; display:flex; flex-direction:column; gap:4px;"></ul>
        </details>
      </div>
    </div>
  </div>

  <!-- ══ Feed ═════════════════════════════════════════════════════════════ -->
  <div class="tab-panel" id="feed-panel" role="tabpanel">
    <div class="sub-tabs">
      <button class="sub-tab active" data-subtab="thoughts">Thoughts</button>
      <button class="sub-tab" data-subtab="digests">Daily Digests</button>
    </div>

    <div id="thoughts-subtab">
      <div class="feed-controls">
        <input type="text" id="feed-search" placeholder="🔍 Search thoughts…">
        <input type="date" id="feed-date" placeholder="Filter by date">
        <input type="text" id="feed-tag" placeholder="Filter by tag">
      </div>
      <div class="entry-list" id="entry-list">
        <div class="empty-state"><div class="icon">📭</div><h3>No thoughts yet</h3><p>Capture your first thought above.</p></div>
      </div>
      <div class="load-more" id="load-more-wrap" style="display:none">
        <button class="btn btn-secondary" id="load-more-btn">Load more</button>
      </div>
    </div>

    <div id="digests-subtab" style="display:none">
      <div class="entry-list" id="digest-list">
        <div class="empty-state"><div class="icon">📊</div><h3>No digests yet</h3><p>Run the nightly cron to generate your first digest.</p></div>
      </div>
      <div id="digest-view-wrap" style="display:none"></div>
    </div>
  </div>

  <!-- ══ Chat ═════════════════════════════════════════════════════════════ -->
  <div class="tab-panel" id="chat-panel" role="tabpanel">
    <div class="chat-wrapper">
      <!-- Past Chats Sidebar Drawer -->
      <div class="chat-sidebar" id="chat-sidebar">
        <div class="chat-sidebar-header">
          <h3>📜 Past Chats</h3>
          <button class="btn btn-sm btn-primary" id="sidebar-new-chat-btn">➕ New</button>
        </div>
        <div class="chat-sidebar-search">
          <input type="text" id="chat-search-input" placeholder="Search conversations…">
        </div>
        <div class="chat-session-list" id="chat-session-list">
          <div class="empty-state" style="padding:20px 10px"><p class="text-xs text-dim">No past chats yet</p></div>
        </div>
      </div>

      <!-- Active Chat Main Area -->
      <div class="chat-layout">
        <div class="chat-topbar">
          <div class="chat-title-group">
            <button class="btn btn-sm btn-secondary" id="toggle-sidebar-btn" title="Toggle Past Chats">📜 History</button>
            <div class="chat-active-title" id="chat-active-title">New Conversation</div>
            <button class="btn-icon" id="rename-active-chat-btn" title="Rename conversation">✏️</button>
          </div>
          <div class="chat-top-actions">
            <button class="btn btn-sm btn-secondary" id="clone-active-chat-btn" title="Clone conversation">📋 Clone</button>
            <button class="btn btn-sm btn-primary" id="new-chat-btn">➕ New Chat</button>
          </div>
        </div>

        <div class="chat-options">
          <label class="toggle-row" for="opt-save-turns">
            <input type="checkbox" id="opt-save-turns" checked>
            Save turns
          </label>
          <label class="toggle-row" for="opt-allow-save">
            <input type="checkbox" id="opt-allow-save">
            Allow AI save
          </label>
          <label class="toggle-row" for="opt-use-memories">
            <input type="checkbox" id="opt-use-memories" checked>
            Core memories
          </label>
          <button class="btn btn-sm btn-secondary" id="clear-chat-btn">Clear screen</button>
        </div>
        <div id="chat-messages"></div>
        <div class="chat-input-row">
          <textarea id="chat-input" placeholder="Ask anything about your thoughts…" rows="1"></textarea>
          <button class="btn btn-primary" id="chat-send">Send ↑</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ══ Memories ══════════════════════════════════════════════════════════ -->
  <div class="tab-panel" id="memories-panel" role="tabpanel">
    <div class="memories-header">
      <div>
        <h2 style="font-size:18px;font-weight:600;letter-spacing:-0.3px">Core Memories</h2>
        <p class="text-sm text-muted mt-1">Persistent facts and context injected into every AI interaction.</p>
      </div>
      <button class="btn btn-primary" id="add-memory-btn">+ Add Memory</button>
    </div>
    <div class="memory-list" id="memory-list">
      <div class="empty-state"><div class="icon">🧩</div><h3>No memories saved</h3><p>Add persistent facts, goals, or context the AI should always know.</p></div>
    </div>
  </div>

  <!-- ══ Settings ══════════════════════════════════════════════════════════ -->
  <div class="tab-panel" id="settings-panel" role="tabpanel">

    <div class="settings-section">
      <h2>🤖 OpenRouter AI</h2>
      <div class="settings-grid">
        <div class="form-group" style="grid-column:1/-1">
          <label>API Key</label>
          <input type="password" id="cfg-api-key" placeholder="sk-or-v1-…">
        </div>
        <div class="form-group">
          <label>Chat Model</label>
          <input type="text" id="cfg-chat-model" placeholder="anthropic/claude-3.5-sonnet">
        </div>
        <div class="form-group">
          <label>Fast Model</label>
          <input type="text" id="cfg-fast-model" placeholder="openai/gpt-4o-mini">
        </div>
        <div class="form-group">
          <label>Embed Model</label>
          <input type="text" id="cfg-embed-model" placeholder="openai/text-embedding-3-small">
        </div>
      </div>
    </div>

    <div class="settings-section">
      <h2>📧 Email (SMTP)</h2>
      <div class="settings-grid">
        <div class="form-group">
          <label>SMTP Host</label>
          <input type="text" id="cfg-smtp-host" placeholder="smtp.gmail.com">
        </div>
        <div class="form-group">
          <label>SMTP Port</label>
          <input type="number" id="cfg-smtp-port" placeholder="465">
        </div>
        <div class="form-group">
          <label>Username</label>
          <input type="text" id="cfg-smtp-user" placeholder="you@gmail.com">
        </div>
        <div class="form-group">
          <label>Password / App Password</label>
          <input type="password" id="cfg-smtp-pass" placeholder="••••••••">
        </div>
        <div class="form-group">
          <label>From Address</label>
          <input type="email" id="cfg-smtp-from" placeholder="you@gmail.com">
        </div>
        <div class="form-group">
          <label>From Name</label>
          <input type="text" id="cfg-smtp-from-name" placeholder="Second Brain">
        </div>
        <div class="form-group" style="grid-column:1/-1">
          <label>Send Digests To</label>
          <input type="email" id="cfg-smtp-to" placeholder="you@gmail.com">
        </div>
      </div>
      <div class="flex gap-2 mt-2">
        <button class="btn btn-secondary btn-sm" id="test-smtp-btn">🔌 Test Connection</button>
      </div>
      <div id="smtp-test-result" class="smtp-test-result"></div>
    </div>

    <div class="settings-section">
      <h2>🔐 Security</h2>
      <div class="form-group">
        <label>Cron Secret Key</label>
        <input type="text" id="cfg-cron-key" placeholder="Random secret string">
      </div>
    </div>

    <div class="settings-section">
      <h2>⚙️ Actions</h2>
      <div class="flex gap-2">
        <button class="btn btn-primary" id="save-settings-btn">💾 Save Settings</button>
        <button class="btn btn-secondary" id="trigger-cron-btn">🔁 Run Synthesis Now</button>
      </div>
      <p class="text-xs text-dim mt-2">CLI cron: <code>php <?= htmlspecialchars(__DIR__) ?>/cron.php</code></p>
    </div>

  </div>

</main>
</div>

<!-- ── Modals ──────────────────────────────────────────────────────────────── -->
<div class="modal-overlay" id="memory-modal">
  <div class="modal" role="dialog" aria-modal="true" aria-label="Memory editor">
    <h2 id="memory-modal-title">Add Memory</h2>
    <div class="form-group">
      <label>Memory Text</label>
      <textarea id="memory-text" placeholder="e.g. I am building a SaaS app focused on async communication…" rows="4"></textarea>
    </div>
    <div class="form-group">
      <label>Category</label>
      <input type="text" id="memory-category" placeholder="general, goal, rule, context…">
    </div>
    <div class="modal-actions">
      <button class="btn btn-secondary" id="memory-cancel-btn">Cancel</button>
      <button class="btn btn-primary" id="memory-save-btn">Save Memory</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="entry-modal">
  <div class="modal" role="dialog" aria-modal="true" aria-label="Entry detail" style="max-width:640px">
    <h2 id="entry-modal-title">Thought Entry</h2>
    <div class="tag-list" id="entry-modal-tags" style="margin-bottom:12px"></div>
    <p class="text-xs text-dim" id="entry-modal-date" style="margin-bottom:16px"></p>
    <hr class="separator" style="margin-top:0">
    <div class="entry-detail" id="entry-modal-content">Loading…</div>
    <div class="modal-actions">
      <button class="btn btn-secondary btn-sm" id="entry-edit-btn">✏️ Edit</button>
      <button class="btn btn-danger btn-sm" id="entry-delete-btn">🗑 Delete</button>
      <button class="btn btn-secondary" id="entry-modal-close">Close</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="edit-entry-modal">
  <div class="modal" role="dialog" aria-modal="true" aria-label="Edit thought" style="max-width:680px">
    <h2>Edit Thought Entry</h2>
    <div class="form-group">
      <label>Title</label>
      <input type="text" id="edit-entry-title" placeholder="Thought title…">
    </div>
    <div class="form-group">
      <label>Tags (comma separated)</label>
      <input type="text" id="edit-entry-tags" placeholder="e.g. strategy, ideas, project">
    </div>
    <div class="form-group">
      <label>Content</label>
      <textarea id="edit-entry-content" rows="8"></textarea>
    </div>
    <div class="modal-actions">
      <button class="btn btn-secondary" id="edit-entry-cancel">Cancel</button>
      <button class="btn btn-primary" id="edit-entry-save">Save Changes</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="edit-turn-modal">
  <div class="modal" role="dialog" aria-modal="true" aria-label="Edit message" style="max-width:540px">
    <h2>Edit Message</h2>
    <div class="form-group">
      <label>Message Content</label>
      <textarea id="edit-turn-text" rows="6"></textarea>
    </div>
    <div class="modal-actions">
      <button class="btn btn-secondary" id="edit-turn-cancel">Cancel</button>
      <button class="btn btn-primary" id="edit-turn-save">Save Changes</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="rename-chat-modal">
  <div class="modal" role="dialog" aria-modal="true" aria-label="Rename conversation" style="max-width:440px">
    <h2>Rename Conversation</h2>
    <div class="form-group">
      <label>Conversation Title</label>
      <input type="text" id="rename-chat-input" placeholder="Enter title…">
    </div>
    <div class="modal-actions">
      <button class="btn btn-secondary" id="rename-chat-cancel">Cancel</button>
      <button class="btn btn-primary" id="rename-chat-save">Save Title</button>
    </div>
  </div>
</div>

<div id="toast-container"></div>

<script>
const API = 'api.php';
let chatHistory = [];
let feedOffset = 0;
const FEED_LIMIT = 20;
let currentSessionId = null;
let currentSessionTitle = 'New Conversation';
let allChatSessions = [];
let editingTurnIdx = null;

/* ── Markdown Parser ────────────────────────────────────────────────────── */
function renderMarkdown(md) {
  if (!md) return '';

  const codeBlocks = [];

  // 1. Extract fenced code blocks
  let out = md.replace(/```([a-zA-Z0-9_-]*)\n([\s\S]*?)```/g, (match, lang, code) => {
    const idx = codeBlocks.length;
    codeBlocks.push({ lang: (lang || 'text').trim(), code });
    return `__CODE_BLOCK_${idx}__`;
  });

  // 2. Escape HTML entities
  out = out
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

  // 3. Tables
  out = out.replace(/((?:\|[^\n]+\|\r?\n)+)/g, (match) => {
    const lines = match.trim().split(/\r?\n/).map(l => l.trim()).filter(l => l.startsWith('|') && l.endsWith('|'));
    if (lines.length < 2) return match;

    const isSep = (l) => /^\|(?:\s*:?-+:?\s*\|)+$/.test(l);
    if (!isSep(lines[1])) return match;

    const parseRow = (line) => line.slice(1, -1).split('|').map(p => p.trim());
    const headers = parseRow(lines[0]);
    const bodyRows = lines.slice(2).map(parseRow);

    let tbl = '<table><thead><tr>' + headers.map(h => `<th>${h}</th>`).join('') + '</tr></thead><tbody>';
    bodyRows.forEach(row => {
      tbl += '<tr>' + row.map(c => `<td>${c}</td>`).join('') + '</tr>';
    });
    tbl += '</tbody></table>';
    return tbl;
  });

  // 4. Blockquotes
  out = out.replace(/(?:^|\n)&gt;\s?([^\n]+(?:\n&gt;\s?[^\n]+)*)/g, (match, content) => {
    const clean = content.replace(/^&gt;\s?/gm, '');
    return `\n<blockquote>${clean}</blockquote>\n`;
  });

  // 5. Headings
  out = out
    .replace(/^#### (.*?)$/gm, '<h4>$1</h4>')
    .replace(/^### (.*?)$/gm, '<h3>$1</h3>')
    .replace(/^## (.*?)$/gm, '<h2>$1</h2>')
    .replace(/^# (.*?)$/gm, '<h1>$1</h1>');

  // 6. Horizontal Rules
  out = out.replace(/^(?:---|\*\*\*|___)$/gm, '<hr>');

  // 7. Lists
  out = out.replace(/(?:^|\n)((?:[\*\-\+] .*(?:\n|$))+)/g, (match, list) => {
    const items = list.trim().split(/\n/).map(i => i.replace(/^[\*\-\+]\s+/, '')).map(i => `<li>${i}</li>`).join('');
    return `\n<ul>${items}</ul>\n`;
  });
  out = out.replace(/(?:^|\n)((?:\d+\. .*(?:\n|$))+)/g, (match, list) => {
    const items = list.trim().split(/\n/).map(i => i.replace(/^\d+\.\s+/, '')).map(i => `<li>${i}</li>`).join('');
    return `\n<ol>${items}</ol>\n`;
  });

  // 8. Inline formats
  out = out.replace(/`([^`\n]+)`/g, '<code>$1</code>');
  out = out.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
  out = out.replace(/__([^_]+)__/g, '<strong>$1</strong>');
  out = out.replace(/\*([^*]+)\*/g, '<em>$1</em>');
  out = out.replace(/_([^_]+)_/g, '<em>$1</em>');
  out = out.replace(/~~([^~]+)~~/g, '<del>$1</del>');
  out = out.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');

  // 9. Paragraphs
  out = out
    .split(/\n\n+/)
    .map(p => {
      p = p.trim();
      if (!p) return '';
      if (/^<(?:h[1-6]|ul|ol|blockquote|table|hr|div)/.test(p)) return p;
      return `<p>${p.replace(/\n/g, '<br>')}</p>`;
    })
    .join('\n');

  // 10. Re-inject code blocks
  out = out.replace(/__CODE_BLOCK_(\d+)__/g, (match, idx) => {
    const b = codeBlocks[parseInt(idx, 10)];
    if (!b) return '';
    const escapedCode = b.code
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
    const rawAttr = encodeURIComponent(b.code);
    return `<div class="code-block-wrap"><div class="code-header"><span class="code-lang">${esc(b.lang)}</span><button class="copy-btn" data-code="${rawAttr}" onclick="copyCode(this)">📋 Copy</button></div><pre><code>${escapedCode}</code></pre></div>`;
  });

  return out;
}

function copyCode(btn) {
  const code = decodeURIComponent(btn.dataset.code || '');
  navigator.clipboard.writeText(code).then(() => {
    btn.textContent = '✓ Copied!';
    setTimeout(() => { btn.textContent = '📋 Copy'; }, 2000);
  });
}

/* ── API helper ─────────────────────────────────────────────────────────── */
async function api(action, data = {}) {
  const r = await fetch(API, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action, ...data }),
  });
  if (r.status === 401 && action !== 'login') {
    toast('Session expired or unauthorized. Redirecting to login…', 'error');
    setTimeout(() => window.location.reload(), 1200);
    throw new Error('Unauthorized. Please log in.');
  }
  const json = await r.json();
  if (!json.ok) throw new Error(json.error || 'Unknown error');
  return json.data;
}

/* ── Toast ──────────────────────────────────────────────────────────────── */
function toast(msg, type = 'info') {
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  const icons = { success: '✅', error: '❌', info: 'ℹ️' };
  el.innerHTML = `<span>${icons[type] || ''}</span><span>${msg}</span>`;
  document.getElementById('toast-container').appendChild(el);
  setTimeout(() => el.remove(), 4000);
}

/* ── Theme toggle ───────────────────────────────────────────────────────── */
const html = document.documentElement;
const themeBtn = document.getElementById('theme-toggle');
function setTheme(t) {
  html.setAttribute('data-theme', t);
  themeBtn.textContent = t === 'dark' ? '☀️' : '🌙';
  localStorage.setItem('sb-theme', t);
}
const saved = localStorage.getItem('sb-theme') || (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
setTheme(saved);
themeBtn.addEventListener('click', () => setTheme(html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark'));

/* ── Tab navigation ─────────────────────────────────────────────────────── */
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(btn.dataset.tab + '-panel').classList.add('active');
    if (btn.dataset.tab === 'feed') loadFeed(true);
    if (btn.dataset.tab === 'map') loadThoughtMap();
    if (btn.dataset.tab === 'memories') loadMemories();
    if (btn.dataset.tab === 'settings') loadSettings();
  });
});

/* ── Sub-tab navigation ─────────────────────────────────────────────────── */
document.querySelectorAll('.sub-tab').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.sub-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('thoughts-subtab').style.display = btn.dataset.subtab === 'thoughts' ? '' : 'none';
    document.getElementById('digests-subtab').style.display  = btn.dataset.subtab === 'digests'  ? '' : 'none';
    if (btn.dataset.subtab === 'digests') loadDigests();
  });
});

/* ═══════════════════════════════════════════════════════════════════════════
   CAPTURE TAB
   ═══════════════════════════════════════════════════════════════════════════ */
const thoughtInput = document.getElementById('thought-input');
const charCount    = document.getElementById('char-count');
const statusPill   = document.getElementById('status-pill');
const voiceBtn     = document.getElementById('voice-btn');
const voiceRing    = document.getElementById('voice-ring');
const voiceLabel   = document.getElementById('voice-label');
const saveBtn      = document.getElementById('save-btn');

thoughtInput.addEventListener('input', () => {
  charCount.textContent = thoughtInput.value.length;
  thoughtInput.style.height = 'auto';
  thoughtInput.style.height = thoughtInput.scrollHeight + 'px';
});

function setPill(state, text) {
  statusPill.className = `status-pill pill-${state}`;
  statusPill.textContent = text;
}

/* Voice recognition */
let recognition = null;
let isRecording = false;
const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

if (SpeechRecognition) {
  recognition = new SpeechRecognition();
  recognition.continuous = true;
  recognition.interimResults = true;
  recognition.lang = 'en-US';

  let baseText = '';

  recognition.onresult = (e) => {
    let interim = '';
    let final_ = '';
    for (let i = e.resultIndex; i < e.results.length; i++) {
      const t = e.results[i][0].transcript;
      if (e.results[i].isFinal) final_ += t;
      else interim += t;
    }
    if (final_) baseText += final_ + ' ';
    thoughtInput.value = baseText + interim;
    thoughtInput.dispatchEvent(new Event('input'));
  };

  recognition.onerror = (e) => {
    isRecording = false;
    voiceBtn.classList.remove('recording');
    voiceRing.classList.remove('recording');
    voiceLabel.textContent = 'Tap to record';
    setPill('idle', 'Idle');
    if (e.error !== 'aborted') toast('Voice error: ' + e.error, 'error');
  };

  recognition.onend = () => {
    if (isRecording) {
      try { recognition.start(); } catch (_) {}
    }
  };

  voiceBtn.addEventListener('click', () => {
    if (!isRecording) {
      baseText = thoughtInput.value ? thoughtInput.value.trimEnd() + ' ' : '';
      recognition.start();
      isRecording = true;
      voiceBtn.textContent = '⏹️';
      voiceBtn.classList.add('recording');
      voiceRing.classList.add('recording');
      voiceLabel.textContent = 'Recording — tap to stop';
      setPill('recording', 'Recording');
    } else {
      recognition.stop();
      isRecording = false;
      voiceBtn.textContent = '🎙️';
      voiceBtn.classList.remove('recording');
      voiceRing.classList.remove('recording');
      voiceLabel.textContent = 'Tap to record';
      setPill('idle', 'Idle');
    }
  });
} else {
  voiceBtn.title = 'Speech recognition not supported in this browser';
  voiceBtn.style.opacity = '0.4';
  voiceLabel.textContent = 'Voice not supported';
}

/* Save thought */
async function saveThought() {
  const content = thoughtInput.value.trim();
  if (!content) { toast('Please enter a thought first.', 'error'); return; }

  // Immediately clear the input UI so user can continue writing without delay
  thoughtInput.value = '';
  thoughtInput.style.height = '';
  charCount.textContent = '0';
  setPill('saving', 'Saved ✓');
  toast('Thought captured!', 'success');

  try {
    const res = await api('capture_thought', { content });
    if (res && res.entry && res.entry.title) {
      setPill('saved', 'Indexed ✓');
    }
    setTimeout(() => setPill('idle', 'Idle'), 2000);
  } catch (e) {
    setPill('idle', 'Idle');
    toast(e.message, 'error');
  }
}

saveBtn.addEventListener('click', saveThought);
thoughtInput.addEventListener('keydown', e => {
  if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); saveThought(); }
});

/* ═══════════════════════════════════════════════════════════════════════════
   FEED TAB
   ═══════════════════════════════════════════════════════════════════════════ */
const entryList = document.getElementById('entry-list');
let feedEntries = [];

async function loadFeed(reset = false) {
  if (reset) { feedOffset = 0; feedEntries = []; }

  const search = document.getElementById('feed-search').value.trim();
  const date   = document.getElementById('feed-date').value;
  const tag    = document.getElementById('feed-tag').value.trim();

  try {
    const data = await api('get_entries', { search, date, tag, limit: FEED_LIMIT, offset: feedOffset });
    if (reset) feedEntries = data.entries;
    else feedEntries = feedEntries.concat(data.entries);

    renderFeed(feedEntries);
    const loadMoreWrap = document.getElementById('load-more-wrap');
    loadMoreWrap.style.display = feedEntries.length < data.total ? '' : 'none';
  } catch (e) {
    toast(e.message, 'error');
  }
}

function renderFeed(entries) {
  if (!entries.length) {
    entryList.innerHTML = '<div class="empty-state"><div class="icon">📭</div><h3>No thoughts found</h3><p>Try a different search or date filter.</p></div>';
    return;
  }
  entryList.innerHTML = entries.map(e => `
    <div class="entry-card" data-id="${e.id}" tabindex="0" role="button" aria-label="${e.title}">
      <div class="entry-card-header">
        <span class="entry-title">${esc(e.title)}</span>
        <span class="entry-date">${formatDate(e.created_at || e.date)}</span>
      </div>
      <div class="entry-preview">${esc(e.preview)}</div>
      <div class="tag-list">${(e.tags || []).map(t => `<span class="tag">${esc(t)}</span>`).join('')}</div>
    </div>
  `).join('');

  entryList.querySelectorAll('.entry-card').forEach(card => {
    card.addEventListener('click', () => openEntry(card.dataset.id));
    card.addEventListener('keydown', e => { if (e.key === 'Enter') openEntry(card.dataset.id); });
  });
}

document.getElementById('feed-search').addEventListener('input', debounce(() => loadFeed(true), 350));
document.getElementById('feed-date').addEventListener('change', () => loadFeed(true));
document.getElementById('feed-tag').addEventListener('input', debounce(() => loadFeed(true), 350));
document.getElementById('load-more-btn').addEventListener('click', () => { feedOffset += FEED_LIMIT; loadFeed(); });

/* Entry detail modal & editing */
let viewingEntryId = null;
let currentViewingEntry = null;

async function openEntry(id) {
  viewingEntryId = id;
  currentViewingEntry = null;
  const modal = document.getElementById('entry-modal');
  modal.classList.add('open');
  document.getElementById('entry-modal-content').textContent = 'Loading…';
  document.getElementById('entry-modal-tags').innerHTML = '';
  document.getElementById('entry-modal-date').textContent = '';

  try {
    const entry = await api('get_entry', { entry_id: id });
    currentViewingEntry = entry;
    document.getElementById('entry-modal-title').textContent = entry.title || 'Entry';
    document.getElementById('entry-modal-date').textContent  = formatDate(entry.date);
    document.getElementById('entry-modal-tags').innerHTML = (entry.tags || []).map(t => `<span class="tag">${esc(t)}</span>`).join('');
    document.getElementById('entry-modal-content').innerHTML = renderMarkdown(entry.content || entry.preview || '');
  } catch (e) {
    document.getElementById('entry-modal-content').textContent = 'Error loading entry: ' + e.message;
  }
}

document.getElementById('entry-modal-close').addEventListener('click', () => {
  document.getElementById('entry-modal').classList.remove('open');
});
document.getElementById('entry-modal').addEventListener('click', e => {
  if (e.target === e.currentTarget) e.currentTarget.classList.remove('open');
});

document.getElementById('entry-edit-btn').addEventListener('click', () => {
  if (!currentViewingEntry) return;
  document.getElementById('edit-entry-title').value   = currentViewingEntry.title || '';
  document.getElementById('edit-entry-tags').value    = (currentViewingEntry.tags || []).join(', ');
  document.getElementById('edit-entry-content').value = currentViewingEntry.content || '';
  document.getElementById('entry-modal').classList.remove('open');
  document.getElementById('edit-entry-modal').classList.add('open');
});

document.getElementById('edit-entry-cancel').addEventListener('click', () => {
  document.getElementById('edit-entry-modal').classList.remove('open');
});

document.getElementById('edit-entry-modal').addEventListener('click', e => {
  if (e.target === e.currentTarget) e.currentTarget.classList.remove('open');
});

document.getElementById('edit-entry-save').addEventListener('click', async () => {
  if (!viewingEntryId) return;
  const title   = document.getElementById('edit-entry-title').value.trim();
  const tagsStr = document.getElementById('edit-entry-tags').value.trim();
  const content = document.getElementById('edit-entry-content').value.trim();

  if (!content) {
    toast('Thought content cannot be empty.', 'error');
    return;
  }

  const tags = tagsStr ? tagsStr.split(',').map(t => t.trim()).filter(Boolean) : [];

  try {
    const updated = await api('update_entry', {
      entry_id: viewingEntryId,
      title,
      tags,
      content,
    });
    document.getElementById('edit-entry-modal').classList.remove('open');
    toast('Thought updated!', 'success');
    await openEntry(viewingEntryId);
    loadFeed(true);
  } catch (e) {
    toast(e.message, 'error');
  }
});

document.getElementById('entry-delete-btn').addEventListener('click', async () => {
  if (!viewingEntryId) return;
  if (!confirm('Delete this thought? This cannot be undone.')) return;
  try {
    await api('delete_entry', { entry_id: viewingEntryId });
    document.getElementById('entry-modal').classList.remove('open');
    toast('Thought deleted.', 'success');
    loadFeed(true);
  } catch (e) { toast(e.message, 'error'); }
});

/* Digests */
async function loadDigests() {
  const list = document.getElementById('digest-list');
  try {
    const digests = await api('get_digests');
    if (!digests.length) {
      list.innerHTML = '<div class="empty-state"><div class="icon">📊</div><h3>No digests yet</h3><p>Run the nightly cron to generate your first digest.</p></div>';
      return;
    }
    list.innerHTML = digests.map(d => `
      <div class="digest-card" data-date="${d.date}" tabindex="0" role="button">
        <div>
          <strong style="font-size:14px">${esc(d.title || 'Digest')}</strong>
          <div class="text-xs text-dim mt-1">${formatDate(d.date)}</div>
        </div>
        <span style="font-size:20px">📄</span>
      </div>
    `).join('');
    list.querySelectorAll('.digest-card').forEach(card => {
      card.addEventListener('click', () => openDigest(card.dataset.date));
    });
  } catch (e) { toast(e.message, 'error'); }
}

function buildDigestIframeHtml(d) {
  let rawHtml = d.html || '<p>No content in this digest.</p>';
  const hasFullDoc = /<html[\s>]/i.test(rawHtml);

  const baseThemeCss = `
    :root {
      --bg: #0d0f17;
      --surface: #141724;
      --surface2: #1b2032;
      --border: #262b42;
      --text: #e2e8f0;
      --text2: #94a3b8;
      --accent: #6366f1;
      --accent-glow: rgba(99, 102, 241, 0.2);
    }
    * { box-sizing: border-box; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
      margin: 0;
      padding: 28px;
      background: var(--bg);
      color: var(--text);
      line-height: 1.7;
      font-size: 15px;
    }
    h1 { font-size: 22px; font-weight: 700; color: #fff; margin: 0 0 16px; border-bottom: 2px solid var(--accent); padding-bottom: 8px; }
    h2 { font-size: 15px; font-weight: 700; color: #a5b4fc; text-transform: uppercase; letter-spacing: .06em; margin: 28px 0 12px; padding-bottom: 6px; border-bottom: 1px solid var(--border); }
    h2:first-child { margin-top: 0; }
    h3 { font-size: 15px; font-weight: 600; color: #fff; margin: 20px 0 8px; }
    p { margin: 0 0 14px; color: var(--text); }
    ul, ol { margin: 0 0 16px; padding-left: 24px; }
    li { margin-bottom: 6px; color: var(--text); }
    strong { color: #fff; }
    em { color: #cbd5e1; }
    blockquote { border-left: 3px solid var(--accent); margin: 16px 0; padding: 8px 16px; background: var(--surface); border-radius: 0 8px 8px 0; font-style: italic; color: var(--text2); }
    hr { border: none; border-top: 1px solid var(--border); margin: 24px 0; }
    .sources-container { display: flex; flex-direction: column; gap: 10px; margin-top: 12px; }
    .source-card { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 14px; transition: border-color 0.2s; }
    .source-card:hover { border-color: var(--accent); }
    .source-header { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 6px; }
    .source-title { font-weight: 600; font-size: 14px; color: #fff; }
    .source-badge { font-size: 11px; padding: 2px 8px; border-radius: 999px; font-weight: 600; }
    .source-badge.today { background: rgba(34, 197, 94, 0.2); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.3); }
    .source-badge.past { background: rgba(99, 102, 241, 0.2); color: #a5b4fc; border: 1px solid rgba(99, 102, 241, 0.3); }
    .source-date { font-size: 12px; color: var(--text2); }
    .source-snippet { font-size: 13px; color: var(--text2); line-height: 1.5; margin-bottom: 8px; white-space: pre-line; }
    .source-meta { font-size: 11px; color: var(--text2); display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
    .tag { background: var(--surface2); border: 1px solid var(--border); padding: 1px 6px; border-radius: 4px; color: #94a3b8; }
    .source-id { opacity: 0.6; font-family: monospace; }
  `;

  const resizeScript = `
    <script>
      function notifyHeight() {
        const h = Math.max(document.body.scrollHeight, document.documentElement.scrollHeight, document.body.offsetHeight);
        window.parent.postMessage({ type: 'digest-resize', height: h }, '*');
      }
      window.addEventListener('load', notifyHeight);
      window.addEventListener('resize', notifyHeight);
      if (window.MutationObserver) {
        new MutationObserver(notifyHeight).observe(document.documentElement, { childList: true, subtree: true, attributes: true });
      }
      setTimeout(notifyHeight, 300);
      setTimeout(notifyHeight, 1000);
    <\\/script>
  `;

  if (hasFullDoc) {
    if (rawHtml.includes('</body>')) {
      return rawHtml.replace('</body>', resizeScript + '</body>');
    } else {
      return rawHtml + resizeScript;
    }
  }

  return `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>${esc(d.title || 'Daily Digest')}</title>
  <style>${baseThemeCss}</style>
</head>
<body>
  <h1>${esc(d.title || 'Daily Digest')}</h1>
  ${rawHtml}
  ${resizeScript}
</body>
</html>`;
}

window.addEventListener('message', (e) => {
  if (e.data && e.data.type === 'digest-resize' && typeof e.data.height === 'number') {
    const iframe = document.getElementById('digest-iframe');
    if (iframe) {
      iframe.style.height = (e.data.height + 40) + 'px';
    }
  }
});

async function openDigest(date) {
  const wrap = document.getElementById('digest-view-wrap');
  wrap.style.display = '';
  wrap.innerHTML = `
    <div class="digest-view-container">
      <div class="digest-view-header">
        <div class="digest-view-meta">
          <strong style="font-size:15px">Loading digest…</strong>
        </div>
      </div>
      <div style="padding: 24px; color: var(--text2); font-size: 14px;">Fetching report…</div>
    </div>
  `;

  try {
    const d = await api('get_digest', { date });
    const iframeDoc = buildDigestIframeHtml(d);
    const sourcesCount = d.sources ? d.sources.length : (d.source_count || 0);

    wrap.innerHTML = `
      <div class="digest-view-container">
        <div class="digest-view-header">
          <div class="digest-view-meta">
            <strong style="font-size:16px; color:#fff">${esc(d.title || 'Daily Digest')}</strong>
            <span class="badge" style="background:rgba(99,102,241,0.2);color:#a5b4fc;border:1px solid rgba(99,102,241,0.3)">${formatDate(d.date || date)}</span>
            ${d.entry_count ? `<span class="text-xs text-dim">📝 ${d.entry_count} thought(s)</span>` : ''}
            ${sourcesCount ? `<span class="text-xs text-dim">🔗 ${sourcesCount} source(s)</span>` : ''}
          </div>
          <div class="digest-view-actions">
            <button class="btn btn-sm btn-secondary" onclick="document.getElementById('digest-view-wrap').style.display='none'">✕ Close</button>
          </div>
        </div>
        <iframe id="digest-iframe" class="digest-iframe" sandbox="allow-same-origin allow-scripts"></iframe>
      </div>
    `;

    const iframe = document.getElementById('digest-iframe');
    if (iframe) {
      iframe.srcdoc = iframeDoc;
    }
  } catch (e) {
    wrap.innerHTML = `
      <div class="digest-view-container">
        <div class="digest-view-header">
          <strong style="color:var(--danger)">Error loading digest</strong>
          <button class="btn btn-sm btn-secondary" onclick="document.getElementById('digest-view-wrap').style.display='none'">✕ Close</button>
        </div>
        <div style="padding:24px;color:var(--text2)">${esc(e.message)}</div>
      </div>
    `;
  }
}

/* ═══════════════════════════════════════════════════════════════════════════
   CHAT TAB & SESSIONS
   ═══════════════════════════════════════════════════════════════════════════ */
const chatMessages       = document.getElementById('chat-messages');
const chatInput          = document.getElementById('chat-input');
const chatSend           = document.getElementById('chat-send');
const chatSidebar        = document.getElementById('chat-sidebar');
const chatSessionList    = document.getElementById('chat-session-list');
const chatActiveTitle    = document.getElementById('chat-active-title');
const chatSearchInput    = document.getElementById('chat-search-input');

function appendChatMsg(role, content, toolCalls = [], sources = [], turnIndex = null, reasoning = null) {
  const wrap = document.createElement('div');
  wrap.className = `chat-msg ${role}`;
  if (turnIndex !== null) wrap.dataset.turnIndex = turnIndex;

  // Reasoning accordion
  if (reasoning && reasoning.trim()) {
    const reasoningBox = document.createElement('div');
    reasoningBox.className = 'reasoning-box';

    const words = reasoning.trim().split(/\s+/).length;
    const reasoningToggle = document.createElement('button');
    reasoningToggle.className = 'reasoning-toggle';
    reasoningToggle.innerHTML = `<span>💭 <strong>AI Thought Process (${words} words)</strong></span> <span class="arrow">▼</span>`;

    const reasoningContent = document.createElement('div');
    reasoningContent.className = 'reasoning-content';
    reasoningContent.style.display = 'none';
    reasoningContent.textContent = reasoning;

    reasoningToggle.addEventListener('click', () => {
      const isOpen = reasoningContent.style.display !== 'none';
      reasoningContent.style.display = isOpen ? 'none' : 'block';
      const arrow = reasoningToggle.querySelector('.arrow');
      if (arrow) arrow.textContent = isOpen ? '▼' : '▲';
    });

    reasoningBox.appendChild(reasoningToggle);
    reasoningBox.appendChild(reasoningContent);
    wrap.appendChild(reasoningBox);
  }

  // Tool calls
  if (toolCalls.length) {
    const tcWrap = document.createElement('div');
    tcWrap.style.cssText = 'display:flex;flex-direction:column;gap:4px;margin-bottom:6px;max-width:100%';
    toolCalls.forEach(tc => {
      const badge = document.createElement('button');
      badge.className = 'tool-badge';
      badge.innerHTML = `🔧 <strong>${esc(tc.tool)}</strong>: ${esc(JSON.stringify(tc.args)).slice(0, 80)}`;
      const detail = document.createElement('div');
      detail.className = 'tool-detail';
      detail.textContent = JSON.stringify(tc.result, null, 2);
      badge.addEventListener('click', () => { badge.classList.toggle('expanded'); });
      tcWrap.appendChild(badge);
      tcWrap.appendChild(detail);
    });
    wrap.appendChild(tcWrap);
  }

  const bubble = document.createElement('div');
  bubble.className = 'msg-bubble';
  if (role === 'assistant') {
    bubble.innerHTML = renderMarkdown(content);
  } else {
    bubble.textContent = content;
  }
  wrap.appendChild(bubble);

  // Sources section
  if (sources && sources.length > 0) {
    const srcWrap = document.createElement('div');
    srcWrap.className = 'chat-sources-wrap';

    const toggleBtn = document.createElement('button');
    toggleBtn.className = 'sources-toggle-btn';
    toggleBtn.innerHTML = `📚 <strong>${sources.length} source${sources.length > 1 ? 's' : ''} referenced</strong> <span style="font-size:10px;opacity:0.7">▼</span>`;

    const srcList = document.createElement('div');
    srcList.className = 'chat-sources-list';
    srcList.style.display = 'none';

    sources.forEach(s => {
      const item = document.createElement('div');
      item.className = 'chat-source-item';
      item.setAttribute('title', 'Click to read full thought entry');

      const tagsHtml = (s.tags || []).map(t => `<span class="tag">${esc(t)}</span>`).join(' ');

      item.innerHTML = `
        <div class="chat-source-header">
          <span class="chat-source-title">📄 ${esc(s.title || 'Untitled Thought')}</span>
          <span class="chat-source-date">${formatDate(s.date)}</span>
        </div>
        ${s.preview ? `<div class="chat-source-preview">${esc(s.preview)}</div>` : ''}
        ${tagsHtml ? `<div style="margin-top:2px">${tagsHtml}</div>` : ''}
      `;

      item.addEventListener('click', () => openEntry(s.id));
      srcList.appendChild(item);
    });

    toggleBtn.addEventListener('click', () => {
      const isOpen = srcList.style.display !== 'none';
      srcList.style.display = isOpen ? 'none' : 'flex';
      toggleBtn.querySelector('span').textContent = isOpen ? '▼' : '▲';
    });

    srcWrap.appendChild(toggleBtn);
    srcWrap.appendChild(srcList);
    wrap.appendChild(srcWrap);
  }

  // Meta row with edit button
  const metaRow = document.createElement('div');
  metaRow.className = 'msg-meta-row';

  const meta = document.createElement('div');
  meta.className = 'msg-meta';
  meta.textContent = new Date().toLocaleTimeString();
  metaRow.appendChild(meta);

  if (turnIndex !== null) {
    const editBtn = document.createElement('button');
    editBtn.className = 'edit-turn-btn';
    editBtn.title = 'Edit this message';
    editBtn.textContent = '✏️ Edit';
    editBtn.addEventListener('click', () => openEditTurnModal(turnIndex, content));
    metaRow.appendChild(editBtn);
  }

  wrap.appendChild(metaRow);
  chatMessages.appendChild(wrap);
  chatMessages.scrollTop = chatMessages.scrollHeight;
}

function showTyping(statusText = 'AI is thinking…') {
  hideTyping();
  const el = document.createElement('div');
  el.id = 'thinking-indicator';
  el.className = 'thinking-card';
  el.innerHTML = `<span class="spin-icon">🧠</span> <span id="thinking-status-text" class="thinking-status-text">${esc(statusText)}</span>`;
  chatMessages.appendChild(el);
  chatMessages.scrollTop = chatMessages.scrollHeight;
}
function updateTyping(statusText) {
  const el = document.getElementById('thinking-status-text');
  if (el) el.textContent = statusText;
}
function hideTyping() {
  document.getElementById('thinking-indicator')?.remove();
}

/* ── Chat Sessions Management ────────────────────────────────────────────── */
async function loadChatSessions() {
  try {
    allChatSessions = await api('get_chat_sessions');
    renderChatSessions(allChatSessions);
  } catch (e) {
    console.error('Failed to load chat sessions:', e);
  }
}

function renderChatSessions(sessions) {
  const q = (chatSearchInput.value || '').toLowerCase().trim();
  const filtered = q ? sessions.filter(s => (s.title || '').toLowerCase().includes(q) || (s.preview || '').toLowerCase().includes(q)) : sessions;

  if (!filtered.length) {
    chatSessionList.innerHTML = '<div class="empty-state" style="padding:20px 10px"><p class="text-xs text-dim">No conversations found</p></div>';
    return;
  }

  chatSessionList.innerHTML = filtered.map(s => `
    <div class="chat-session-item ${s.id === currentSessionId ? 'active' : ''}" data-id="${s.id}">
      <div class="chat-session-title">${esc(s.title || 'Untitled Conversation')}</div>
      <div class="chat-session-preview">${esc(s.preview || 'No preview')}</div>
      <div class="chat-session-meta">
        <span>${formatDate(s.updated_at || s.created_at)}</span>
        <span>${s.turn_count || 0} msgs</span>
      </div>
      <div class="chat-session-actions">
        <button class="session-act-btn clone-btn" title="Clone conversation">📋</button>
        <button class="session-act-btn rename-btn" title="Rename conversation">✏️</button>
        <button class="session-act-btn del-btn" title="Delete conversation">🗑️</button>
      </div>
    </div>
  `).join('');

  chatSessionList.querySelectorAll('.chat-session-item').forEach(item => {
    const id = item.dataset.id;
    item.addEventListener('click', (e) => {
      if (e.target.closest('.chat-session-actions')) return;
      loadChatSession(id);
    });

    item.querySelector('.clone-btn')?.addEventListener('click', (e) => {
      e.stopPropagation();
      cloneChat(id);
    });

    item.querySelector('.rename-btn')?.addEventListener('click', (e) => {
      e.stopPropagation();
      openRenameModal(id);
    });

    item.querySelector('.del-btn')?.addEventListener('click', (e) => {
      e.stopPropagation();
      deleteChat(id);
    });
  });
}

async function loadChatSession(id) {
  try {
    const session = await api('get_chat_session', { session_id: id });
    currentSessionId = session.id;
    currentSessionTitle = session.title || 'Conversation';
    chatActiveTitle.textContent = currentSessionTitle;

    chatMessages.innerHTML = '';
    chatHistory = [];

    (session.turns || []).forEach((turn, idx) => {
      chatHistory.push({ role: turn.role, content: turn.content });
      appendChatMsg(turn.role, turn.content, turn.tool_calls || [], turn.sources || [], idx, turn.reasoning || null);
    });

    renderChatSessions(allChatSessions);
  } catch (e) {
    toast(e.message, 'error');
  }
}

function newChat() {
  currentSessionId = null;
  currentSessionTitle = 'New Conversation';
  chatActiveTitle.textContent = currentSessionTitle;
  chatMessages.innerHTML = '';
  chatHistory = [];
  chatInput.value = '';
  renderChatSessions(allChatSessions);
  chatInput.focus();
}

async function cloneChat(id) {
  try {
    const cloned = await api('clone_chat_session', { session_id: id || currentSessionId });
    toast('Conversation cloned!', 'success');
    await loadChatSessions();
    await loadChatSession(cloned.id);
  } catch (e) {
    toast(e.message, 'error');
  }
}

async function deleteChat(id) {
  if (!confirm('Delete this conversation history?')) return;
  try {
    await api('delete_chat_session', { session_id: id });
    toast('Conversation deleted.', 'success');
    if (currentSessionId === id) {
      newChat();
    }
    loadChatSessions();
  } catch (e) {
    toast(e.message, 'error');
  }
}

function openRenameModal(id = null) {
  const targetId = id || currentSessionId;
  if (!targetId) {
    toast('Start a conversation first before renaming.', 'info');
    return;
  }
  const session = allChatSessions.find(s => s.id === targetId);
  const currentTitle = session ? session.title : currentSessionTitle;
  const input = document.getElementById('rename-chat-input');
  input.value = currentTitle;
  const modal = document.getElementById('rename-chat-modal');
  modal.dataset.targetId = targetId;
  modal.classList.add('open');
  input.focus();
}

document.getElementById('rename-chat-save').addEventListener('click', async () => {
  const modal = document.getElementById('rename-chat-modal');
  const targetId = modal.dataset.targetId;
  const title = document.getElementById('rename-chat-input').value.trim();
  if (!title || !targetId) return;

  try {
    await api('rename_chat_session', { session_id: targetId, title });
    modal.classList.remove('open');
    toast('Title updated.', 'success');
    if (currentSessionId === targetId) {
      currentSessionTitle = title;
      chatActiveTitle.textContent = title;
    }
    loadChatSessions();
  } catch (e) {
    toast(e.message, 'error');
  }
});
document.getElementById('rename-chat-cancel').addEventListener('click', () => {
  document.getElementById('rename-chat-modal').classList.remove('open');
});

/* ── Edit Turn Modal ──────────────────────────────────────────────────────── */
function openEditTurnModal(turnIdx, content) {
  editingTurnIdx = turnIdx;
  document.getElementById('edit-turn-text').value = content;
  document.getElementById('edit-turn-modal').classList.add('open');
}

document.getElementById('edit-turn-save').addEventListener('click', async () => {
  if (editingTurnIdx === null || !currentSessionId) return;
  const newText = document.getElementById('edit-turn-text').value.trim();

  try {
    await api('edit_chat_turn', {
      session_id: currentSessionId,
      turn_index: editingTurnIdx,
      content: newText,
    });
    document.getElementById('edit-turn-modal').classList.remove('open');
    toast('Message updated.', 'success');
    await loadChatSession(currentSessionId);
  } catch (e) {
    toast(e.message, 'error');
  }
});
document.getElementById('edit-turn-cancel').addEventListener('click', () => {
  document.getElementById('edit-turn-modal').classList.remove('open');
});

/* Toggle sidebar drawer */
document.getElementById('toggle-sidebar-btn').addEventListener('click', () => {
  chatSidebar.classList.toggle('collapsed');
});
document.getElementById('new-chat-btn').addEventListener('click', newChat);
document.getElementById('sidebar-new-chat-btn').addEventListener('click', newChat);
document.getElementById('clone-active-chat-btn').addEventListener('click', () => cloneChat(currentSessionId));
document.getElementById('rename-active-chat-btn').addEventListener('click', () => openRenameModal(currentSessionId));
chatActiveTitle.addEventListener('click', () => openRenameModal(currentSessionId));
chatSearchInput.addEventListener('input', debounce(() => renderChatSessions(allChatSessions), 200));

async function sendChat() {
  const msg = chatInput.value.trim();
  if (!msg) return;
  chatInput.value = '';
  chatInput.style.height = '';

  const userTurnIndex = chatHistory.length;
  appendChatMsg('user', msg, [], [], userTurnIndex);
  chatHistory.push({ role: 'user', content: msg });

  showTyping('Analyzing thoughts & context…');
  chatSend.disabled = true;

  // Prepare active assistant message container for live streaming
  let assistantWrap = null;
  let bubble = null;
  let tcWrap = null;
  let reasoningBox = null;
  let reasoningToggle = null;
  let reasoningContent = null;
  let srcWrap = null;
  let srcList = null;
  let srcToggleBtn = null;
  const activeToolBadges = {};
  let accumulatedText = '';
  let accumulatedReasoning = '';
  let finalSources = [];

  function initAssistantMsg() {
    if (assistantWrap) return;
    hideTyping();
    assistantWrap = document.createElement('div');
    assistantWrap.className = 'chat-msg assistant';

    tcWrap = document.createElement('div');
    tcWrap.style.cssText = 'display:flex;flex-direction:column;gap:4px;margin-bottom:6px;max-width:100%';
    tcWrap.style.display = 'none';
    assistantWrap.appendChild(tcWrap);

    // Reasoning box
    reasoningBox = document.createElement('div');
    reasoningBox.className = 'reasoning-box';
    reasoningBox.style.display = 'none';

    reasoningToggle = document.createElement('button');
    reasoningToggle.className = 'reasoning-toggle';
    reasoningToggle.innerHTML = `<span>💭 <strong>AI Thought Process</strong></span> <span class="arrow">▼</span>`;

    reasoningContent = document.createElement('div');
    reasoningContent.className = 'reasoning-content';

    reasoningToggle.addEventListener('click', () => {
      const isOpen = reasoningContent.style.display !== 'none';
      reasoningContent.style.display = isOpen ? 'none' : 'block';
      const arrow = reasoningToggle.querySelector('.arrow');
      if (arrow) arrow.textContent = isOpen ? '▼' : '▲';
    });

    reasoningBox.appendChild(reasoningToggle);
    reasoningBox.appendChild(reasoningContent);
    assistantWrap.appendChild(reasoningBox);

    // Speech bubble
    bubble = document.createElement('div');
    bubble.className = 'msg-bubble';
    bubble.style.display = 'none';
    assistantWrap.appendChild(bubble);

    // Sources wrap
    srcWrap = document.createElement('div');
    srcWrap.className = 'chat-sources-wrap';
    srcWrap.style.display = 'none';

    srcToggleBtn = document.createElement('button');
    srcToggleBtn.className = 'sources-toggle-btn';

    srcList = document.createElement('div');
    srcList.className = 'chat-sources-list';
    srcList.style.display = 'none';

    srcToggleBtn.addEventListener('click', () => {
      const isOpen = srcList.style.display !== 'none';
      srcList.style.display = isOpen ? 'none' : 'flex';
      const arrow = srcToggleBtn.querySelector('span');
      if (arrow) arrow.textContent = isOpen ? '▼' : '▲';
    });

    srcWrap.appendChild(srcToggleBtn);
    srcWrap.appendChild(srcList);
    assistantWrap.appendChild(srcWrap);

    const metaRow = document.createElement('div');
    metaRow.className = 'msg-meta-row';
    const meta = document.createElement('div');
    meta.className = 'msg-meta';
    meta.textContent = new Date().toLocaleTimeString();
    metaRow.appendChild(meta);
    assistantWrap.appendChild(metaRow);

    chatMessages.appendChild(assistantWrap);
    chatMessages.scrollTop = chatMessages.scrollHeight;
  }

  function appendReasoning(chunk) {
    initAssistantMsg();
    accumulatedReasoning += chunk;
    reasoningBox.style.display = 'block';
    reasoningContent.textContent = accumulatedReasoning;
    const words = accumulatedReasoning.trim().split(/\s+/).length;
    reasoningToggle.innerHTML = `<span>💭 <strong>AI Thought Process (${words} words)</strong></span> <span class="arrow">▼</span>`;
    chatMessages.scrollTop = chatMessages.scrollHeight;
  }

  function updateSources(sources) {
    if (!sources || !sources.length) return;
    initAssistantMsg();
    finalSources = sources;
    srcWrap.style.display = 'flex';
    srcToggleBtn.innerHTML = `📚 <strong>${sources.length} source${sources.length > 1 ? 's' : ''} referenced</strong> <span style="font-size:10px;opacity:0.7">▼</span>`;
    srcList.innerHTML = '';
    sources.forEach(s => {
      const item = document.createElement('div');
      item.className = 'chat-source-item';
      item.setAttribute('title', 'Click to read full thought entry');
      const tagsHtml = (s.tags || []).map(t => `<span class="tag">${esc(t)}</span>`).join(' ');
      item.innerHTML = `
        <div class="chat-source-header">
          <span class="chat-source-title">📄 ${esc(s.title || 'Untitled Thought')}</span>
          <span class="chat-source-date">${formatDate(s.date)}</span>
        </div>
        ${s.preview ? `<div class="chat-source-preview">${esc(s.preview)}</div>` : ''}
        ${tagsHtml ? `<div style="margin-top:2px">${tagsHtml}</div>` : ''}
      `;
      item.addEventListener('click', () => openEntry(s.id));
      srcList.appendChild(item);
    });
    chatMessages.scrollTop = chatMessages.scrollHeight;
  }

  try {
    const response = await fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action:       'chat_stream',
        session_id:   currentSessionId,
        message:      msg,
        history:      chatHistory.slice(-20),
        save_turns:   document.getElementById('opt-save-turns').checked,
        allow_save:   document.getElementById('opt-allow-save').checked,
        use_memories: document.getElementById('opt-use-memories').checked,
      }),
    });

    if (!response.ok) {
      const errText = await response.text();
      throw new Error(`HTTP ${response.status}: ${errText}`);
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder('utf-8');
    let buffer = '';

    while (true) {
      const { done, value } = await reader.read();
      if (done) break;

      buffer += decoder.decode(value, { stream: true });
      const blocks = buffer.split('\n\n');
      buffer = blocks.pop() || '';

      for (const block of blocks) {
        if (!block.trim()) continue;

        let eventType = 'message';
        let dataStr = '';

        for (const line of block.split('\n')) {
          if (line.startsWith('event: ')) {
            eventType = line.slice(7).trim();
          } else if (line.startsWith('data: ')) {
            dataStr = line.slice(6);
          }
        }

        if (!dataStr) continue;

        let data = {};
        try {
          data = JSON.parse(dataStr);
        } catch (_) {
          continue;
        }

        if (eventType === 'session') {
          currentSessionId = data.session_id;
        } else if (eventType === 'status') {
          updateTyping(data.message || 'Thinking…');
        } else if (eventType === 'reasoning') {
          appendReasoning(data.chunk || data.reasoning || '');
        } else if (eventType === 'token') {
          hideTyping();
          initAssistantMsg();
          accumulatedText += data.token;
          bubble.style.display = 'block';
          bubble.innerHTML = renderMarkdown(accumulatedText);
          chatMessages.scrollTop = chatMessages.scrollHeight;
        } else if (eventType === 'tool_start') {
          initAssistantMsg();
          tcWrap.style.display = 'flex';
          const badge = document.createElement('button');
          badge.className = 'tool-badge';
          badge.innerHTML = `⏳ <strong>${esc(data.tool)}</strong>: ${esc(JSON.stringify(data.args)).slice(0, 70)} <span class="spin">⟳</span>`;
          const detail = document.createElement('div');
          detail.className = 'tool-detail';
          badge.addEventListener('click', () => badge.classList.toggle('expanded'));
          activeToolBadges[data.id] = { badge, detail, tool: data.tool, args: data.args };
          tcWrap.appendChild(badge);
          tcWrap.appendChild(detail);
          chatMessages.scrollTop = chatMessages.scrollHeight;
        } else if (eventType === 'tool_result') {
          if (activeToolBadges[data.id]) {
            const b = activeToolBadges[data.id];
            b.badge.innerHTML = `🔧 <strong>${esc(b.tool)}</strong>: ${esc(JSON.stringify(b.args)).slice(0, 70)}`;
            b.detail.textContent = JSON.stringify(data.result, null, 2);
          }
        } else if (eventType === 'sources') {
          updateSources(data.sources || []);
        } else if (eventType === 'done') {
          hideTyping();
          initAssistantMsg();
          if (data.reply && !accumulatedText) {
            accumulatedText = data.reply;
          }
          if (accumulatedText) {
            bubble.style.display = 'block';
            bubble.innerHTML = renderMarkdown(accumulatedText);
          }
          if (data.sources && data.sources.length) {
            updateSources(data.sources);
          }
          if (data.session_id) {
            currentSessionId = data.session_id;
          }
          if (data.session && data.session.title) {
            currentSessionTitle = data.session.title;
            chatActiveTitle.textContent = currentSessionTitle;
          }
          loadChatSessions();
        } else if (eventType === 'error') {
          throw new Error(data.error || 'Streaming error');
        }
      }
    }

    hideTyping();
    if (accumulatedText) {
      chatHistory.push({ role: 'assistant', content: accumulatedText });
    }
  } catch (e) {
    hideTyping();
    initAssistantMsg();
    if (bubble) {
      bubble.style.display = 'block';
      bubble.textContent = '⚠️ Error: ' + e.message;
    }
  } finally {
    hideTyping();
    chatSend.disabled = false;
    chatInput.focus();
  }
}

// Load past sessions on tab switch
document.querySelector('.tab-btn[data-tab="chat"]')?.addEventListener('click', () => {
  loadChatSessions();
});
loadChatSessions();

chatSend.addEventListener('click', sendChat);
chatInput.addEventListener('keydown', e => {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendChat(); }
});
chatInput.addEventListener('input', () => {
  chatInput.style.height = 'auto';
  chatInput.style.height = Math.min(chatInput.scrollHeight, 160) + 'px';
});
document.getElementById('clear-chat-btn').addEventListener('click', () => {
  chatHistory = [];
  chatMessages.innerHTML = '';
  toast('Chat cleared.', 'info');
});

/* ═══════════════════════════════════════════════════════════════════════════
   MEMORIES TAB
   ═══════════════════════════════════════════════════════════════════════════ */
let currentMemories = [];

async function loadMemories() {
  const list = document.getElementById('memory-list');
  try {
    const mems = await api('get_memories');
    currentMemories = mems || [];
    if (!currentMemories.length) {
      list.innerHTML = '<div class="empty-state"><div class="icon">🧩</div><h3>No memories saved</h3><p>Add persistent facts, goals, or context the AI should always know.</p></div>';
      return;
    }
    list.innerHTML = currentMemories.map(m => `
      <div class="memory-card" data-id="${m.id}">
        <div class="memory-body">
          <div class="memory-text">${esc(m.text)}</div>
          <div class="memory-meta">${esc(m.category)} · ${formatDate(m.updated_at || m.created_at)}</div>
        </div>
        <div class="memory-actions">
          <button class="btn btn-icon btn-secondary edit-mem" data-id="${m.id}" title="Edit">✏️</button>
          <button class="btn btn-icon btn-danger del-mem" data-id="${m.id}" title="Delete">🗑</button>
        </div>
      </div>
    `).join('');

    list.querySelectorAll('.edit-mem').forEach(btn => {
      btn.addEventListener('click', () => {
        const mem = currentMemories.find(m => m.id === btn.dataset.id);
        if (mem) openMemoryModal(mem.id, mem.text, mem.category);
      });
    });
    list.querySelectorAll('.del-mem').forEach(btn => {
      btn.addEventListener('click', () => deleteMemory(btn.dataset.id));
    });
  } catch (e) { toast(e.message, 'error'); }
}

function openMemoryModal(id = null, text = '', category = 'general') {
  editingMemoryId = id;
  document.getElementById('memory-modal-title').textContent = id ? 'Edit Memory' : 'Add Memory';
  document.getElementById('memory-text').value = text;
  document.getElementById('memory-category').value = category;
  document.getElementById('memory-modal').classList.add('open');
  document.getElementById('memory-text').focus();
}

document.getElementById('add-memory-btn').addEventListener('click', () => openMemoryModal());
document.getElementById('memory-cancel-btn').addEventListener('click', () => {
  document.getElementById('memory-modal').classList.remove('open');
  editingMemoryId = null;
});
document.getElementById('memory-modal').addEventListener('click', e => {
  if (e.target === e.currentTarget) { e.currentTarget.classList.remove('open'); editingMemoryId = null; }
});

document.getElementById('memory-save-btn').addEventListener('click', async () => {
  const text = document.getElementById('memory-text').value.trim();
  const cat  = document.getElementById('memory-category').value.trim() || 'general';
  if (!text) { toast('Memory text is required.', 'error'); return; }

  try {
    if (editingMemoryId) {
      await api('update_memory', { id: editingMemoryId, text, category: cat });
      toast('Memory updated.', 'success');
    } else {
      await api('add_memory', { text, category: cat });
      toast('Memory saved.', 'success');
    }
    document.getElementById('memory-modal').classList.remove('open');
    editingMemoryId = null;
    loadMemories();
  } catch (e) { toast(e.message, 'error'); }
});

async function deleteMemory(id) {
  if (!confirm('Delete this memory?')) return;
  try {
    await api('delete_memory', { id });
    toast('Memory deleted.', 'success');
    loadMemories();
  } catch (e) { toast(e.message, 'error'); }
}

/* ═══════════════════════════════════════════════════════════════════════════
   SETTINGS TAB
   ═══════════════════════════════════════════════════════════════════════════ */
async function loadSettings() {
  try {
    const cfg = await api('get_settings');
    const map = {
      'OPENROUTER_API_KEY':    'cfg-api-key',
      'OPENROUTER_CHAT_MODEL': 'cfg-chat-model',
      'OPENROUTER_FAST_MODEL': 'cfg-fast-model',
      'OPENROUTER_EMBED_MODEL':'cfg-embed-model',
      'SMTP_HOST':             'cfg-smtp-host',
      'SMTP_PORT':             'cfg-smtp-port',
      'SMTP_USER':             'cfg-smtp-user',
      'SMTP_PASS':             'cfg-smtp-pass',
      'SMTP_FROM':             'cfg-smtp-from',
      'SMTP_FROM_NAME':        'cfg-smtp-from-name',
      'SMTP_TO':               'cfg-smtp-to',
      'CRON_SECRET_KEY':       'cfg-cron-key',
    };
    for (const [k, elId] of Object.entries(map)) {
      if (cfg[k] !== undefined) document.getElementById(elId).value = cfg[k];
    }
  } catch (e) { toast('Could not load settings: ' + e.message, 'error'); }
}

document.getElementById('save-settings-btn').addEventListener('click', async () => {
  const payload = {
    OPENROUTER_API_KEY:     document.getElementById('cfg-api-key').value,
    OPENROUTER_CHAT_MODEL:  document.getElementById('cfg-chat-model').value,
    OPENROUTER_FAST_MODEL:  document.getElementById('cfg-fast-model').value,
    OPENROUTER_EMBED_MODEL: document.getElementById('cfg-embed-model').value,
    SMTP_HOST:              document.getElementById('cfg-smtp-host').value,
    SMTP_PORT:              document.getElementById('cfg-smtp-port').value,
    SMTP_USER:              document.getElementById('cfg-smtp-user').value,
    SMTP_PASS:              document.getElementById('cfg-smtp-pass').value,
    SMTP_FROM:              document.getElementById('cfg-smtp-from').value,
    SMTP_FROM_NAME:         document.getElementById('cfg-smtp-from-name').value,
    SMTP_TO:                document.getElementById('cfg-smtp-to').value,
    CRON_SECRET_KEY:        document.getElementById('cfg-cron-key').value,
  };
  try {
    await api('save_settings', payload);
    toast('Settings saved.', 'success');
  } catch (e) { toast(e.message, 'error'); }
});

document.getElementById('test-smtp-btn').addEventListener('click', async () => {
  const resultEl = document.getElementById('smtp-test-result');
  resultEl.className = 'smtp-test-result';
  resultEl.style.display = 'none';
  const btn = document.getElementById('test-smtp-btn');
  btn.disabled = true;
  btn.textContent = '⏳ Testing…';

  try {
    const result = await api('test_smtp');
    resultEl.className = 'smtp-test-result ' + (result.success ? 'success' : 'error');
    resultEl.innerHTML = (result.steps || []).map(s => `<div>${esc(s)}</div>`).join('');
    resultEl.style.display = 'block';
  } catch (e) {
    resultEl.className = 'smtp-test-result error';
    resultEl.textContent = e.message;
    resultEl.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = '🔌 Test Connection';
  }
});

document.getElementById('trigger-cron-btn').addEventListener('click', async () => {
  const key = document.getElementById('cfg-cron-key').value.trim();
  if (!key) { toast('Set your cron secret key first.', 'error'); return; }
  const btn = document.getElementById('trigger-cron-btn');
  btn.disabled = true;
  btn.textContent = '⏳ Running…';
  try {
    await api('trigger_cron', { key });
    toast('Synthesis complete! Check the Digests tab.', 'success');
  } catch (e) {
    toast('Cron error: ' + e.message, 'error');
  } finally {
    btn.disabled = false;
    btn.textContent = '🔁 Run Synthesis Now';
  }
});

/* ── Utilities ───────────────────────────────────────────────────────────── */
function esc(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function formatDate(str) {
  if (!str) return '';
  try {
    return new Date(str).toLocaleDateString('en-GB', { day:'numeric', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' });
  } catch { return str; }
}
function debounce(fn, ms) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

/* ═══════════════════════════════════════════════════════════════════════════
   VISUAL THOUGHT MAP TAB (Force-Directed Graph)
   ═══════════════════════════════════════════════════════════════════════════ */
let mapNodes = [];
let mapEdges = [];
let mapTags = [];
let mapGroups = [];
let mapTreeData = [];
let mapNodeMap = {};
let mapSimThreshold = 0.35;
let mapPhysicsPaused = false;
let mapAnimFrame = null;
let mapColorMode = 'group';
let currentMapViewMode = 'map';

const mapCamera = { x: 0, y: 0, zoom: 1 };
let isPanningMap = false;
let panStart = { x: 0, y: 0 };
let draggedMapNode = null;
let hoveredMapNode = null;
let selectedMapNode = null;

const mapCanvas = document.getElementById('map-canvas');
const mapCtx = mapCanvas ? mapCanvas.getContext('2d') : null;

async function loadThoughtMap() {
  if (!mapCanvas || !mapCtx) return;
  try {
    const data = await api('get_thought_map');
    initMapData(data);
  } catch (e) {
    toast('Error loading map: ' + e.message, 'error');
  }
}

function initMapData(data) {
  const rawNodes = data.nodes || [];
  const rawEdges = data.edges || [];
  mapTags        = data.tags || [];
  mapGroups      = data.groups || [];
  mapTreeData    = data.tree || [];

  // Populate tag filter select dropdown
  const tagSelect = document.getElementById('map-tag-filter');
  if (tagSelect) {
    tagSelect.innerHTML = '<option value="">All Tags (' + rawNodes.length + ')</option>' +
      mapTags.map(t => `<option value="${esc(t.name)}">${esc(t.name)} (${t.count})</option>`).join('');
  }

  // Populate map legend
  renderMapLegend();

  // Initialize nodes with random initial positions around center
  const width  = mapCanvas.clientWidth || 800;
  const height = mapCanvas.clientHeight || 600;
  const cx = width / 2;
  const cy = height / 2;

  mapNodes = rawNodes.map((n, i) => {
    const angle = (i / Math.max(1, rawNodes.length)) * Math.PI * 2;
    const dist  = 120 + Math.random() * 180;
    return {
      ...n,
      x: cx + Math.cos(angle) * dist,
      y: cy + Math.sin(angle) * dist,
      vx: (Math.random() - 0.5) * 2,
      vy: (Math.random() - 0.5) * 2,
      radius: 8 + Math.min(12, (n.tags || []).length * 2),
      isMatching: true,
    };
  });

  mapNodeMap = {};
  mapNodes.forEach(n => { mapNodeMap[n.id] = n; });

  mapEdges = rawEdges;

  mapCamera.x = 0;
  mapCamera.y = 0;
  mapCamera.zoom = 1;
  selectedMapNode = null;
  hoveredMapNode = null;

  document.getElementById('map-node-card').style.display = 'none';

  filterMapNodes();
  renderTreeView();

  if (currentMapViewMode === 'map' && !mapAnimFrame) {
    runMapLoop();
  }
}

function renderMapLegend() {
  const legendEl = document.getElementById('map-legend');
  if (!legendEl) return;

  if (mapColorMode === 'group') {
    legendEl.innerHTML = mapGroups.slice(0, 8).map(g => `
      <div class="map-legend-item" data-group="${esc(g.id)}">
        <span class="map-legend-dot" style="background:${g.color}"></span>
        <span>${esc(g.name)}</span>
      </div>
    `).join('');
  } else {
    legendEl.innerHTML = mapTags.slice(0, 8).map(t => `
      <div class="map-legend-item" data-tag="${esc(t.name)}">
        <span class="map-legend-dot" style="background:${t.color}"></span>
        <span>${esc(t.name)}</span>
      </div>
    `).join('');
  }

  legendEl.querySelectorAll('.map-legend-item').forEach(item => {
    item.addEventListener('click', () => {
      if (item.dataset.tag) {
        const tagSelect = document.getElementById('map-tag-filter');
        if (tagSelect) {
          tagSelect.value = item.dataset.tag;
          filterMapNodes();
        }
      }
    });
  });
}

function filterMapNodes() {
  const query = (document.getElementById('map-search')?.value || '').toLowerCase().trim();
  const selectedTag = (document.getElementById('map-tag-filter')?.value || '').toLowerCase().trim();

  const matching = [];
  mapNodes.forEach(n => {
    let matchQuery = !query || (n.title || '').toLowerCase().includes(query) || (n.preview || '').toLowerCase().includes(query);
    let matchTag   = !selectedTag || (n.tags || []).some(t => t.toLowerCase() === selectedTag);
    n.isMatching = matchQuery && matchTag;
    if (n.isMatching) {
      matching.push(n);
    }
  });

  updateAccessibleMapList(matching);
}

function updateAccessibleMapList(matchingNodes) {
  const listEl = document.getElementById('map-accessible-list');
  const countEl = document.getElementById('map-list-count');
  if (!listEl) return;

  if (countEl) countEl.textContent = matchingNodes.length;

  if (!matchingNodes.length) {
    listEl.innerHTML = '<li style="color:var(--text3)">No matching thoughts found</li>';
    return;
  }

  listEl.innerHTML = matchingNodes.slice(0, 50).map(n => `
    <li>
      <button class="map-acc-item" data-id="${esc(n.id)}" onclick="openEntry('${esc(n.id)}')">
        <strong>${esc(n.title)}</strong> <span style="font-size:10px; opacity:0.7">(${esc(n.date)})</span>
      </button>
    </li>
  `).join('');
}

function renderTreeView() {
  const rootEl = document.getElementById('tree-view-root');
  const summaryEl = document.getElementById('tree-summary-text');
  if (!rootEl) return;

  const searchQuery = (document.getElementById('map-search')?.value || '').toLowerCase().trim();
  const tagQuery = (document.getElementById('map-tag-filter')?.value || '').toLowerCase().trim();

  if (summaryEl) {
    summaryEl.textContent = `${mapGroups.length} cluster(s) · ${mapNodes.length} thought(s)`;
  }

  if (!mapTreeData.length) {
    rootEl.innerHTML = '<div style="color:var(--text3); font-size:13px; padding:12px 0;">No thoughts available for tree view.</div>';
    return;
  }

  let html = '';
  mapTreeData.forEach(groupNode => {
    // Filter matching thoughts in this group
    const matchingChildren = [];
    (groupNode.children || []).forEach(child => {
      if (child.type === 'thought') {
        const matchesQuery = !searchQuery || (child.name || '').toLowerCase().includes(searchQuery) || (child.preview || '').toLowerCase().includes(searchQuery);
        const matchesTag   = !tagQuery || (child.tags || []).some(t => t.toLowerCase() === tagQuery);
        if (matchesQuery && matchesTag) {
          matchingChildren.push(child);
        }
      } else if (child.type === 'tag') {
        const matchingSub = (child.children || []).filter(ti => {
          const mq = !searchQuery || (ti.name || '').toLowerCase().includes(searchQuery) || (ti.preview || '').toLowerCase().includes(searchQuery);
          const mt = !tagQuery || (ti.tags || []).some(t => t.toLowerCase() === tagQuery);
          return mq && mt;
        });
        if (matchingSub.length > 0) {
          matchingChildren.push({ ...child, children: matchingSub, count: matchingSub.length });
        }
      }
    });

    if (searchQuery || tagQuery) {
      if (matchingChildren.length === 0) return;
    }

    html += `
      <details open style="background:var(--surface2); border:1px solid var(--border); border-radius:var(--radius); padding:8px 12px;">
        <summary style="cursor:pointer; font-weight:600; font-size:14px; color:var(--text); display:flex; align-items:center; justify-content:space-between; user-select:none;">
          <span style="display:inline-flex; align-items:center; gap:8px;">
            <span style="width:10px; height:10px; border-radius:50%; background:${groupNode.color}; display:inline-block;"></span>
            📦 ${esc(groupNode.name)}
          </span>
          <span class="tag" style="background:var(--surface3); color:var(--text2); font-size:11px;">${matchingChildren.length} items</span>
        </summary>
        <div style="margin-top:10px; padding-left:14px; display:flex; flex-direction:column; gap:6px; border-left:2px dashed var(--border);">
    `;

    matchingChildren.forEach(item => {
      if (item.type === 'thought') {
        html += renderTreeThoughtItem(item);
      } else if (item.type === 'tag') {
        html += `
          <details open style="margin:4px 0;">
            <summary style="cursor:pointer; font-size:13px; font-weight:600; color:var(--text2); user-select:none;">
              🏷️ Tag: ${esc(item.name)} (${item.count})
            </summary>
            <div style="padding-left:14px; margin-top:6px; display:flex; flex-direction:column; gap:6px; border-left:1px solid var(--border);">
              ${(item.children || []).map(renderTreeThoughtItem).join('')}
            </div>
          </details>
        `;
      }
    });

    html += `
        </div>
      </details>
    `;
  });

  if (!html) {
    rootEl.innerHTML = '<div style="color:var(--text3); font-size:13px; padding:12px 0;">No matching thoughts in tree view.</div>';
    return;
  }

  rootEl.innerHTML = html;
}

function renderTreeThoughtItem(t) {
  const tagsHtml = (t.tags || []).map(tg => `<span class="tag" style="font-size:10px">${esc(tg)}</span>`).join(' ');
  return `
    <div class="entry-card" style="padding:10px 12px; border-radius:8px; margin:2px 0;" onclick="openEntry('${esc(t.id)}')">
      <div style="display:flex; justify-content:space-between; align-items:center;">
        <span style="font-weight:600; font-size:13px; color:var(--text);">📄 ${esc(t.name)}</span>
        <span style="font-size:11px; color:var(--text3);">${esc(t.date)}</span>
      </div>
      ${t.preview ? `<div style="font-size:12px; color:var(--text2); margin-top:4px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">${esc(t.preview)}</div>` : ''}
      <div style="margin-top:6px;">${tagsHtml}</div>
    </div>
  `;
}

function runMapLoop() {
  if (!document.getElementById('map-panel')?.classList.contains('active')) {
    mapAnimFrame = null;
    return;
  }
  updateMapPhysics();
  renderMap();
  mapAnimFrame = requestAnimationFrame(runMapLoop);
}

function updateMapPhysics() {
  if (mapPhysicsPaused || !mapNodes.length) return;

  const width  = mapCanvas.clientWidth || 800;
  const height = mapCanvas.clientHeight || 600;
  const cx = width / 2;
  const cy = height / 2;

  // 1. Repulsion between all node pairs (stronger repulsive force between unrelated / different group nodes)
  const len = mapNodes.length;
  for (let i = 0; i < len; i++) {
    const nodeA = mapNodes[i];
    for (let j = i + 1; j < len; j++) {
      const nodeB = mapNodes[j];
      const dx = nodeB.x - nodeA.x;
      const dy = nodeB.y - nodeA.y;
      const distSq = dx * dx + dy * dy + 1;
      const dist = Math.sqrt(distSq);

      const sameGroup = nodeA.groupId && nodeA.groupId === nodeB.groupId;
      const maxRepulsionDist = sameGroup ? 300 : 600;

      if (dist < maxRepulsionDist) {
        const baseForce = sameGroup ? 2200 : 5500;
        const force = baseForce / (distSq + 100);
        const fx = (dx / dist) * force;
        const fy = (dy / dist) * force;

        nodeA.vx -= fx;
        nodeA.vy -= fy;
        nodeB.vx += fx;
        nodeB.vy += fy;
      }
    }
  }

  // 2. Spring forces along active edges (pull related nodes closely together)
  mapEdges.forEach(e => {
    if (e.similarity < mapSimThreshold) return;
    const source = mapNodeMap[e.source];
    const target = mapNodeMap[e.target];
    if (!source || !target) return;

    const dx = target.x - source.x;
    const dy = target.y - source.y;
    const dist = Math.sqrt(dx * dx + dy * dy) || 1;
    const targetDist = 110 * (1 - e.similarity * 0.65);
    const delta = dist - targetDist;
    const force = delta * 0.03 * (e.similarity || 0.5);

    const fx = (dx / dist) * force;
    const fy = (dy / dist) * force;

    source.vx += fx;
    source.vy += fy;
    target.vx -= fx;
    target.vy -= fy;
  });

  // 3. Central gravity force & velocity damping
  mapNodes.forEach(n => {
    if (n === draggedMapNode) return;

    const dx = cx - n.x;
    const dy = cy - n.y;
    n.vx += dx * 0.003;
    n.vy += dy * 0.003;

    // Damping
    n.vx *= 0.82;
    n.vy *= 0.82;

    n.x += n.vx;
    n.y += n.vy;
  });
}

function renderMap() {
  if (!mapCanvas || !mapCtx) return;

  const rect = mapCanvas.getBoundingClientRect();
  const dpr = window.devicePixelRatio || 1;
  const targetW = Math.round(rect.width * dpr);
  const targetH = Math.round(rect.height * dpr);

  if (mapCanvas.width !== targetW || mapCanvas.height !== targetH) {
    mapCanvas.width  = targetW;
    mapCanvas.height = targetH;
  }

  mapCtx.save();
  mapCtx.scale(dpr, dpr);
  mapCtx.clearRect(0, 0, rect.width, rect.height);

  // Apply camera transformation
  mapCtx.save();
  mapCtx.translate(mapCamera.x, mapCamera.y);
  mapCtx.scale(mapCamera.zoom, mapCamera.zoom);

  // Draw grid lines
  mapCtx.strokeStyle = 'rgba(255, 255, 255, 0.03)';
  mapCtx.lineWidth = 1;
  const gridSize = 60;
  const startX = -2000;
  const endX   = 3000;
  const startY = -2000;
  const endY   = 3000;

  mapCtx.beginPath();
  for (let x = startX; x <= endX; x += gridSize) {
    mapCtx.moveTo(x, startY);
    mapCtx.lineTo(x, endY);
  }
  for (let y = startY; y <= endY; y += gridSize) {
    mapCtx.moveTo(startX, y);
    mapCtx.lineTo(endX, y);
  }
  mapCtx.stroke();

  // Active connected node IDs for hovered/selected node
  const activeNode = selectedMapNode || hoveredMapNode;
  const connectedNodeIds = new Set();
  if (activeNode) {
    connectedNodeIds.add(activeNode.id);
    mapEdges.forEach(e => {
      if (e.similarity >= mapSimThreshold) {
        if (e.source === activeNode.id) connectedNodeIds.add(e.target);
        if (e.target === activeNode.id) connectedNodeIds.add(e.source);
      }
    });
  }

  // 1. Draw Edges
  mapEdges.forEach(e => {
    if (e.similarity < mapSimThreshold) return;
    const source = mapNodeMap[e.source];
    const target = mapNodeMap[e.target];
    if (!source || !target) return;

    const isConnected = activeNode && (e.source === activeNode.id || e.target === activeNode.id);

    mapCtx.beginPath();
    mapCtx.moveTo(source.x, source.y);
    mapCtx.lineTo(target.x, target.y);

    if (isConnected) {
      mapCtx.strokeStyle = '#a78bfa';
      mapCtx.lineWidth = 3.0;
    } else {
      const alpha = Math.max(0.35, Math.min(0.85, (e.similarity - 0.15) * 1.2));
      mapCtx.strokeStyle = `rgba(139, 120, 247, ${alpha})`;
      mapCtx.lineWidth = Math.max(1.5, e.similarity * 3.2);
    }
    mapCtx.stroke();
  });

  // 2. Draw Nodes
  mapNodes.forEach(n => {
    const isHovered = n === hoveredMapNode;
    const isSelected = n === selectedMapNode;
    const isConnected = activeNode && connectedNodeIds.has(n.id);
    const alpha = n.isMatching ? (activeNode ? (isConnected ? 1 : 0.25) : 1) : 0.12;

    mapCtx.save();
    mapCtx.globalAlpha = alpha;

    let fillColor = n.color;
    if (mapColorMode === 'group') {
      fillColor = n.groupColor || n.color;
    } else if (mapColorMode === 'date') {
      fillColor = getDateColor(n.date);
    }

    // Glow for selected or hovered node
    if (isSelected || isHovered) {
      mapCtx.beginPath();
      mapCtx.arc(n.x, n.y, n.radius + 8, 0, Math.PI * 2);
      mapCtx.fillStyle = 'rgba(124, 106, 247, 0.25)';
      mapCtx.fill();
    }

    // Outer ring
    mapCtx.beginPath();
    mapCtx.arc(n.x, n.y, n.radius + (isSelected ? 3 : 0), 0, Math.PI * 2);
    mapCtx.fillStyle = fillColor;
    mapCtx.fill();
    mapCtx.lineWidth = isSelected ? 3 : 1.5;
    mapCtx.strokeStyle = isSelected ? '#ffffff' : 'rgba(255,255,255,0.4)';
    mapCtx.stroke();

    // Node label
    mapCtx.font = (isSelected ? 'bold ' : '') + '12px Inter, sans-serif';
    mapCtx.fillStyle = isSelected ? '#ffffff' : (isHovered ? '#e8e8f0' : '#a0a0c0');
    mapCtx.textAlign = 'center';
    mapCtx.fillText(n.title, n.x, n.y + n.radius + 15);

    mapCtx.restore();
  });

  mapCtx.restore(); // restore camera
  mapCtx.restore(); // restore dpr
}

function getDateColor(dateStr) {
  if (!dateStr) return '#60a5fa';
  const time = new Date(dateStr).getTime();
  const now  = Date.now();
  const diffDays = Math.max(0, (now - time) / (1000 * 3600 * 24));
  if (diffDays < 1) return '#34d399';
  if (diffDays < 7) return '#60a5fa';
  if (diffDays < 30) return '#a78bfa';
  return '#fbbf24';
}

function getNodeAtPos(cx, cy) {
  const rect = mapCanvas.getBoundingClientRect();
  const screenX = cx - rect.left;
  const screenY = cy - rect.top;

  const worldX = (screenX - mapCamera.x) / mapCamera.zoom;
  const worldY = (screenY - mapCamera.y) / mapCamera.zoom;

  for (let i = mapNodes.length - 1; i >= 0; i--) {
    const n = mapNodes[i];
    const dx = worldX - n.x;
    const dy = worldY - n.y;
    if (dx * dx + dy * dy <= (n.radius + 6) * (n.radius + 6)) {
      return n;
    }
  }
  return null;
}

function selectMapNode(node) {
  selectedMapNode = node;
  const card = document.getElementById('map-node-card');
  if (!node) {
    if (card) card.style.display = 'none';
    return;
  }

  document.getElementById('map-card-title').textContent = node.title || 'Untitled';
  document.getElementById('map-card-date').textContent  = formatDate(node.date);
  document.getElementById('map-card-tags').innerHTML  = (node.tags || []).map(t => `<span class="tag">${esc(t)}</span>`).join(' ');
  document.getElementById('map-card-preview').textContent = node.preview || 'No content preview.';
  card.style.display = 'flex';
}

if (mapCanvas) {
  mapCanvas.addEventListener('mousedown', e => {
    const hitNode = getNodeAtPos(e.clientX, e.clientY);
    if (hitNode) {
      draggedMapNode = hitNode;
      selectMapNode(hitNode);
    } else {
      isPanningMap = true;
      panStart = { x: e.clientX - mapCamera.x, y: e.clientY - mapCamera.y };
    }
  });

  window.addEventListener('mousemove', e => {
    if (draggedMapNode) {
      const rect = mapCanvas.getBoundingClientRect();
      const worldX = (e.clientX - rect.left - mapCamera.x) / mapCamera.zoom;
      const worldY = (e.clientY - rect.top - mapCamera.y) / mapCamera.zoom;
      draggedMapNode.x = worldX;
      draggedMapNode.y = worldY;
      draggedMapNode.vx = 0;
      draggedMapNode.vy = 0;
    } else if (isPanningMap) {
      mapCamera.x = e.clientX - panStart.x;
      mapCamera.y = e.clientY - panStart.y;
    } else {
      hoveredMapNode = getNodeAtPos(e.clientX, e.clientY);
    }
  });

  window.addEventListener('mouseup', () => {
    draggedMapNode = null;
    isPanningMap = false;
  });

  mapCanvas.addEventListener('wheel', e => {
    e.preventDefault();
    const zoomFactor = e.deltaY < 0 ? 1.12 : 0.88;
    const newZoom = Math.max(0.2, Math.min(4.0, mapCamera.zoom * zoomFactor));

    const rect = mapCanvas.getBoundingClientRect();
    const mouseX = e.clientX - rect.left;
    const mouseY = e.clientY - rect.top;

    mapCamera.x = mouseX - (mouseX - mapCamera.x) * (newZoom / mapCamera.zoom);
    mapCamera.y = mouseY - (mouseY - mapCamera.y) * (newZoom / mapCamera.zoom);
    mapCamera.zoom = newZoom;
  }, { passive: false });
}

document.getElementById('map-card-close')?.addEventListener('click', () => {
  selectMapNode(null);
});

document.getElementById('map-card-open')?.addEventListener('click', () => {
  if (selectedMapNode) {
    openEntry(selectedMapNode.id);
  }
});

document.getElementById('map-search')?.addEventListener('input', debounce(() => {
  filterMapNodes();
  renderTreeView();
}, 200));

document.getElementById('map-tag-filter')?.addEventListener('change', () => {
  filterMapNodes();
  renderTreeView();
});

document.getElementById('btn-view-map')?.addEventListener('click', () => {
  currentMapViewMode = 'map';
  document.getElementById('btn-view-map').classList.add('active');
  document.getElementById('btn-view-tree').classList.remove('active');
  document.getElementById('map-container').style.display = 'block';
  document.getElementById('map-tree-container').style.display = 'none';
  if (!mapAnimFrame) runMapLoop();
});

document.getElementById('btn-view-tree')?.addEventListener('click', () => {
  currentMapViewMode = 'tree';
  document.getElementById('btn-view-tree').classList.add('active');
  document.getElementById('btn-view-map').classList.remove('active');
  document.getElementById('map-container').style.display = 'none';
  document.getElementById('map-tree-container').style.display = 'block';
  renderTreeView();
});

document.getElementById('map-sim-slider')?.addEventListener('input', e => {
  mapSimThreshold = parseFloat(e.target.value);
  document.getElementById('map-sim-val').textContent = mapSimThreshold.toFixed(2);
});

document.getElementById('map-color-mode')?.addEventListener('change', e => {
  mapColorMode = e.target.value;
  renderMapLegend();
});

document.getElementById('map-reset-btn')?.addEventListener('click', () => {
  mapCamera.x = 0;
  mapCamera.y = 0;
  mapCamera.zoom = 1;
  selectedMapNode = null;
  selectMapNode(null);
  loadThoughtMap();
});

document.getElementById('map-pause-btn')?.addEventListener('click', e => {
  mapPhysicsPaused = !mapPhysicsPaused;
  e.target.textContent = mapPhysicsPaused ? '▶️ Resume' : '⏸️ Freeze';
});

/* ── Auth / Logout ───────────────────────────────────────────────────────── */
document.getElementById('logout-btn')?.addEventListener('click', async () => {
  try {
    await api('logout');
  } catch (e) {}
  window.location.reload();
});

/* ── Init ────────────────────────────────────────────────────────────────── */
loadFeed(true);
</script>
</body>
</html>
