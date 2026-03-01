<?php
/**
 * AgentForge AI — OpenEMR integration page.
 *
 * Loaded inside OpenEMR's iframe tab system when the user clicks
 * "AgentForge AI" in the top navigation bar. Validates the OpenEMR
 * session then serves the full agent chat UI. All API calls are
 * routed to the FastAPI backend running on localhost:8000.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Shreelakshmi Gopinatha Rao
 * @copyright Copyright (c) 2026 Shreelakshmi Gopinatha Rao
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once('../globals.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>AgentForge AI</title>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background: #f0f4f8;
      display: flex;
      flex-direction: column;
      height: 100vh;
      color: #1a202c;
      overflow: hidden;
    }

    /* ── Slim toolbar (replaces the full header — OpenEMR nav is already above) ── */
    #toolbar {
      background: #1a56db;
      color: #fff;
      padding: 10px 18px;
      display: flex;
      align-items: center;
      gap: 10px;
      flex-shrink: 0;
      box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    }
    #toolbar .brand { font-size: 14px; font-weight: 700; letter-spacing: -0.2px; }
    #toolbar .sub   { font-size: 11px; opacity: 0.8; margin-left: 4px; }
    #toolbar .toolbar-actions { margin-left: auto; display: flex; gap: 8px; }

    .toolbar-btn {
      background: rgba(255,255,255,0.18);
      color: #fff;
      border: 1px solid rgba(255,255,255,0.35);
      border-radius: 7px;
      padding: 6px 12px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.15s;
    }
    .toolbar-btn:hover { background: rgba(255,255,255,0.30); }

    /* ── Status pill for agent connectivity ── */
    #agent-status {
      font-size: 11px;
      font-weight: 600;
      padding: 4px 10px;
      border-radius: 20px;
      background: rgba(255,255,255,0.15);
      border: 1px solid rgba(255,255,255,0.3);
      display: flex;
      align-items: center;
      gap: 5px;
    }
    #status-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #fbd38d;
      flex-shrink: 0;
    }
    #status-dot.online  { background: #68d391; }
    #status-dot.offline { background: #fc8181; }

    /* ── Main split area ── */
    #main-area {
      flex: 1;
      display: flex;
      flex-direction: row;
      overflow: hidden;
    }

    /* ── Chat pane (left) ── */
    #chat-pane {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      min-width: 0;
    }

    /* ── Chat messages ── */
    #chat {
      flex: 1;
      overflow-y: auto;
      padding: 24px 16px;
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    /* ── PDF viewer pane (right) ── */
    #pdf-pane {
      width: 0;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      background: #1a202c;
      border-left: 0px solid #2d3748;
      transition: width 0.35s ease, border-left-width 0.35s ease;
      flex-shrink: 0;
    }
    #pdf-pane.open {
      width: 55%;
      border-left-width: 3px;
    }

    #pdf-viewer-header {
      background: #2d3748;
      padding: 10px 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-shrink: 0;
    }
    #pdf-viewer-title {
      color: #e2e8f0;
      font-size: 13px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    #pdf-close-btn {
      background: none;
      border: none;
      color: #a0aec0;
      font-size: 18px;
      cursor: pointer;
      line-height: 1;
      padding: 2px 6px;
      border-radius: 4px;
      transition: background 0.15s;
    }
    #pdf-close-btn:hover { background: #4a5568; color: #fff; }

    #citation-links {
      background: #2d3748;
      padding: 8px 12px;
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      flex-shrink: 0;
      border-bottom: 1px solid #4a5568;
    }
    .citation-pill {
      background: #1a56db;
      color: #fff;
      border: none;
      border-radius: 16px;
      padding: 5px 12px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.15s, transform 0.1s;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    .citation-pill:hover  { background: #1e429f; transform: translateY(-1px); }
    .citation-pill.active { background: #f6ad55; color: #1a202c; }

    #pdf-canvas-container {
      flex: 1;
      overflow-y: auto;
      overflow-x: hidden;
      position: relative;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 16px;
      gap: 12px;
    }

    .pdf-page-wrapper {
      position: relative;
      box-shadow: 0 4px 20px rgba(0,0,0,0.5);
      border-radius: 2px;
    }
    .pdf-page-wrapper canvas { display: block; }

    .pdf-highlight-overlay {
      position: absolute;
      top: 0; left: 0;
      width: 100%; height: 100%;
      pointer-events: none;
      border-radius: 2px;
      animation: citation-pulse 1.2s ease-in-out infinite;
      border: 4px solid rgba(246,173,85,0.9);
    }
    @keyframes citation-pulse {
      0%   { background: rgba(255,220,0,0.20); border-color: rgba(246,173,85,1.0); }
      50%  { background: rgba(255,220,0,0.05); border-color: rgba(246,173,85,0.3); }
      100% { background: rgba(255,220,0,0.20); border-color: rgba(246,173,85,1.0); }
    }

    #pdf-status {
      color: #a0aec0;
      font-size: 13px;
      text-align: center;
      padding: 24px;
      line-height: 1.6;
    }

    /* ── Welcome card ── */
    .welcome {
      background: #fff;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 20px;
      max-width: 680px;
      width: 100%;
      align-self: center;
    }
    .welcome h2 { font-size: 15px; font-weight: 600; margin-bottom: 6px; color: #1a56db; }
    .welcome p  { font-size: 13px; color: #4a5568; margin-bottom: 14px; line-height: 1.5; }
    .chips { display: flex; flex-wrap: wrap; gap: 8px; }
    .chip {
      background: #ebf4ff;
      border: 1px solid #bee3f8;
      color: #1a56db;
      border-radius: 20px;
      padding: 7px 14px;
      font-size: 13px;
      cursor: pointer;
      transition: background 0.15s;
      white-space: nowrap;
    }
    .chip:hover { background: #bee3f8; }

    /* ── Message bubbles ── */
    .msg { display: flex; flex-direction: column; max-width: 680px; width: 100%; }
    .msg.user  { align-self: flex-end; align-items: flex-end; }
    .msg.agent { align-self: center; align-items: flex-start; }

    .bubble {
      border-radius: 14px;
      padding: 12px 16px;
      font-size: 14px;
      line-height: 1.6;
      white-space: pre-wrap;
      word-break: break-word;
    }
    .msg.user  .bubble { background: #1a56db; color: #fff; border-bottom-right-radius: 4px; }
    .msg.agent .bubble { background: #fff; border: 1px solid #e2e8f0; border-bottom-left-radius: 4px; color: #1a202c; }

    .escalation-banner {
      display: flex;
      align-items: center;
      gap: 8px;
      background: #fff5f5;
      border: 1px solid #fc8181;
      border-radius: 8px;
      padding: 8px 12px;
      margin-top: 8px;
      font-size: 13px;
      color: #c53030;
      font-weight: 600;
    }

    .disclaimer { font-size: 11px; color: #a0aec0; margin-top: 6px; padding: 0 4px; line-height: 1.4; }
    .confidence { font-size: 11px; color: #718096; margin-top: 4px; padding: 0 4px; }

    .citation-strip { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
    .citation-anchor-btn {
      background: #ebf4ff;
      border: 1px solid #bee3f8;
      color: #1a56db;
      border-radius: 14px;
      padding: 4px 11px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.15s, transform 0.1s;
      display: flex;
      align-items: center;
      gap: 4px;
    }
    .citation-anchor-btn:hover { background: #bee3f8; transform: translateY(-1px); }

    /* ── Tool trace ── */
    .trace-container { margin-top: 8px; }
    .trace-container details { border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
    .trace-container summary {
      background: #f7fafc;
      padding: 7px 12px;
      font-size: 12px;
      font-weight: 600;
      color: #4a5568;
      cursor: pointer;
      user-select: none;
      list-style: none;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .trace-container summary::-webkit-details-marker { display: none; }
    .trace-container summary::before { content: '▶'; font-size: 9px; transition: transform 0.15s; }
    .trace-container details[open] summary::before { transform: rotate(90deg); }
    .trace-steps { padding: 10px 12px; display: flex; flex-direction: column; gap: 10px; }
    .trace-step { background: #fff; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden; }
    .trace-step-header {
      background: #ebf4ff;
      padding: 5px 10px;
      font-size: 11px;
      font-weight: 700;
      color: #1a56db;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .trace-step-body { padding: 8px 10px; display: flex; flex-direction: column; gap: 6px; }
    .trace-label { font-size: 10px; font-weight: 700; color: #718096; text-transform: uppercase; letter-spacing: 0.5px; }
    .trace-json {
      background: #1a202c;
      color: #68d391;
      border-radius: 4px;
      padding: 7px 10px;
      font-family: "SF Mono", "Fira Code", monospace;
      font-size: 11px;
      white-space: pre-wrap;
      word-break: break-all;
      max-height: 160px;
      overflow-y: auto;
    }

    /* ── Typing indicator ── */
    .typing .bubble { display: flex; gap: 4px; align-items: center; padding: 14px 18px; }
    .dot { width: 7px; height: 7px; background: #a0aec0; border-radius: 50%; animation: bounce 1.2s infinite; }
    .dot:nth-child(2) { animation-delay: 0.2s; }
    .dot:nth-child(3) { animation-delay: 0.4s; }
    @keyframes bounce {
      0%, 60%, 100% { transform: translateY(0); }
      30%           { transform: translateY(-6px); }
    }

    /* ── Denial risk badge ── */
    .denial-badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      border-radius: 6px;
      padding: 5px 10px;
      font-size: 12px;
      font-weight: 700;
      margin-top: 8px;
    }
    .denial-NONE     { background: #f0fff4; border: 1px solid #9ae6b4; color: #276749; }
    .denial-LOW      { background: #f0fff4; border: 1px solid #9ae6b4; color: #276749; }
    .denial-MEDIUM   { background: #fffbeb; border: 1px solid #f6e05e; color: #744210; }
    .denial-HIGH     { background: #fff5f5; border: 1px solid #fc8181; color: #c53030; }
    .denial-CRITICAL { background: #c53030; border: 1px solid #c53030; color: #fff; }
    .denial-UNKNOWN  { background: #f7fafc; border: 1px solid #cbd5e0; color: #4a5568; }

    /* ── PDF attachment badge ── */
    #pdf-badge {
      display: none;
      align-items: center;
      gap: 6px;
      background: #ebf4ff;
      border: 1px solid #bee3f8;
      border-radius: 8px;
      padding: 5px 10px;
      font-size: 12px;
      color: #1a56db;
      font-weight: 600;
      margin: 0 16px 8px;
      flex-shrink: 0;
    }
    #pdf-badge button { background: none; border: none; cursor: pointer; color: #c53030; font-size: 14px; line-height: 1; padding: 0 0 0 4px; }

    /* ── Input bar ── */
    #input-bar {
      background: #fff;
      border-top: 1px solid #e2e8f0;
      padding: 14px 16px;
      display: flex;
      gap: 10px;
      flex-shrink: 0;
    }
    #input {
      flex: 1;
      border: 1px solid #cbd5e0;
      border-radius: 10px;
      padding: 10px 14px;
      font-size: 14px;
      outline: none;
      transition: border-color 0.15s;
      resize: none;
      height: 44px;
      font-family: inherit;
    }
    #input:focus { border-color: #1a56db; }
    #upload-btn {
      background: #f7fafc;
      color: #4a5568;
      border: 1px solid #cbd5e0;
      border-radius: 10px;
      padding: 0 14px;
      font-size: 18px;
      cursor: pointer;
      transition: background 0.15s, border-color 0.15s;
      height: 44px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    #upload-btn:hover { background: #ebf4ff; border-color: #1a56db; }
    #send-btn {
      background: #1a56db;
      color: #fff;
      border: none;
      border-radius: 10px;
      padding: 0 20px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.15s;
      height: 44px;
      white-space: nowrap;
    }
    #send-btn:hover    { background: #1e429f; }
    #send-btn:disabled { background: #a0aec0; cursor: not-allowed; }

    /* ── Eval overlay ── */
    #eval-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.45);
      z-index: 100;
      align-items: flex-start;
      justify-content: center;
      padding: 24px 16px;
      overflow-y: auto;
    }
    #eval-overlay.open { display: flex; }

    #eval-panel {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.25);
      width: 100%;
      max-width: 1100px;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      max-height: calc(100vh - 48px);
    }

    #eval-panel-header {
      background: #1a56db;
      color: #fff;
      padding: 18px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-shrink: 0;
    }
    #eval-panel-title    { font-size: 17px; font-weight: 700; }
    #eval-panel-subtitle { font-size: 12px; opacity: 0.75; margin-top: 2px; }
    #eval-close-btn {
      background: rgba(255,255,255,0.15);
      border: none;
      color: #fff;
      font-size: 18px;
      cursor: pointer;
      border-radius: 6px;
      padding: 4px 10px;
      line-height: 1;
      transition: background 0.15s;
    }
    #eval-close-btn:hover { background: rgba(255,255,255,0.3); }
    #run-eval-btn {
      background: #fff;
      color: #1a56db;
      border: none;
      border-radius: 8px;
      padding: 7px 16px;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      transition: background 0.15s;
    }
    #run-eval-btn:hover    { background: #ebf4ff; }
    #run-eval-btn:disabled { background: #a0aec0; color: #fff; cursor: not-allowed; }

    #eval-summary-cards {
      display: flex;
      gap: 12px;
      padding: 16px 24px;
      background: #f7fafc;
      border-bottom: 1px solid #e2e8f0;
      flex-shrink: 0;
      flex-wrap: wrap;
    }
    .eval-card {
      background: #fff;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      padding: 12px 20px;
      flex: 1;
      min-width: 100px;
      text-align: center;
    }
    .eval-card-pass { border-color: #9ae6b4; background: #f0fff4; }
    .eval-card-fail { border-color: #fc8181; background: #fff5f5; }
    .eval-card-rate { border-color: #bee3f8; background: #ebf4ff; }
    .eval-card-label { font-size: 11px; font-weight: 600; color: #718096; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
    .eval-card-value { font-size: 26px; font-weight: 700; color: #1a202c; }
    .eval-card-pass .eval-card-value { color: #276749; }
    .eval-card-fail .eval-card-value { color: #c53030; }
    .eval-card-rate .eval-card-value { color: #1a56db; }

    #eval-filter-bar {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 12px 24px;
      background: #fff;
      border-bottom: 1px solid #e2e8f0;
      flex-shrink: 0;
      flex-wrap: wrap;
    }
    .eval-filter-btn {
      background: #f7fafc;
      border: 1px solid #e2e8f0;
      border-radius: 20px;
      padding: 5px 13px;
      font-size: 12px;
      font-weight: 600;
      color: #4a5568;
      cursor: pointer;
      transition: background 0.15s, border-color 0.15s;
    }
    .eval-filter-btn:hover  { background: #ebf4ff; border-color: #bee3f8; color: #1a56db; }
    .eval-filter-btn.active { background: #1a56db; border-color: #1a56db; color: #fff; }
    #eval-search {
      margin-left: auto;
      border: 1px solid #cbd5e0;
      border-radius: 8px;
      padding: 6px 12px;
      font-size: 12px;
      outline: none;
      width: 200px;
      transition: border-color 0.15s;
    }
    #eval-search:focus { border-color: #1a56db; }

    #eval-table-wrap { flex: 1; overflow-y: auto; overflow-x: auto; }
    #eval-empty { display: none; padding: 48px; text-align: center; color: #718096; font-size: 14px; }
    #eval-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    #eval-table thead th {
      background: #f7fafc;
      padding: 10px 14px;
      text-align: left;
      font-size: 11px;
      font-weight: 700;
      color: #4a5568;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border-bottom: 2px solid #e2e8f0;
      white-space: nowrap;
      position: sticky;
      top: 0;
      z-index: 1;
    }
    #eval-table tbody tr   { border-bottom: 1px solid #f0f4f8; transition: background 0.1s; }
    #eval-table tbody tr:hover { background: #f7fafc; }
    #eval-table tbody td   { padding: 10px 14px; vertical-align: top; }

    .status-badge { display: inline-flex; align-items: center; gap: 4px; border-radius: 20px; padding: 3px 10px; font-size: 12px; font-weight: 700; white-space: nowrap; }
    .status-PASS { background: #f0fff4; color: #276749; border: 1px solid #9ae6b4; }
    .status-FAIL { background: #fff5f5; color: #c53030; border: 1px solid #fc8181; }

    .cat-badge { display: inline-block; border-radius: 12px; padding: 2px 9px; font-size: 11px; font-weight: 600; white-space: nowrap; }
    .cat-happy_path    { background: #f0fff4; color: #276749; }
    .cat-edge_case     { background: #fffbeb; color: #744210; }
    .cat-adversarial   { background: #fff5f5; color: #c53030; }
    .cat-pdf_clinical  { background: #ebf4ff; color: #1a56db; }

    .score-dots { display: flex; gap: 4px; flex-wrap: wrap; }
    .score-dot  { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
    .score-dot.ok   { background: #48bb78; }
    .score-dot.fail { background: #fc8181; }
    .score-dot.na   { background: #cbd5e0; }

    .resp-preview {
      color: #4a5568;
      font-size: 12px;
      line-height: 1.5;
      max-width: 320px;
      overflow: hidden;
      display: -webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
    }

    .denial-inline { display: inline-block; border-radius: 4px; padding: 2px 7px; font-size: 11px; font-weight: 700; }
    .denial-inline-HIGH     { background: #fff5f5; color: #c53030; }
    .denial-inline-CRITICAL { background: #c53030; color: #fff; }
    .denial-inline-MEDIUM   { background: #fffbeb; color: #744210; }
    .denial-inline-LOW      { background: #f0fff4; color: #276749; }
    .denial-inline-NONE     { color: #a0aec0; }

    #eval-spinner { display: none; flex-direction: column; align-items: center; gap: 14px; padding: 48px; color: #4a5568; font-size: 14px; }
    .spinner-ring { width: 40px; height: 40px; border: 4px solid #e2e8f0; border-top-color: #1a56db; border-radius: 50%; animation: spin 0.8s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>

<!-- ── Slim toolbar ─────────────────────────────────────────────────────────── -->
<div id="toolbar">
  <span>&#x1F916;</span>
  <span class="brand">AgentForge AI</span>
  <span class="sub">Healthcare RCM &middot; Medication Safety</span>
  <div class="toolbar-actions">
    <div id="agent-status">
      <span id="status-dot"></span>
      <span id="status-text">Connecting&hellip;</span>
    </div>
    <button class="toolbar-btn" type="button" onclick="openEvalPanel()">&#x1F4CA; Eval</button>
    <button class="toolbar-btn" type="button" onclick="handleNewCase()">New Case</button>
  </div>
</div>

<!-- ── Eval overlay ─────────────────────────────────────────────────────────── -->
<div id="eval-overlay">
  <div id="eval-panel">
    <div id="eval-panel-header">
      <div>
        <div id="eval-panel-title">&#x1F4CA; Eval Results</div>
        <div id="eval-panel-subtitle">AgentForge RCM &mdash; Golden Dataset</div>
      </div>
      <div style="display:flex;gap:8px;align-items:center;">
        <button id="run-eval-btn" onclick="runEval()">&#x25B6; Run Eval</button>
        <button id="eval-close-btn" onclick="closeEvalPanel()">&#x2715;</button>
      </div>
    </div>

    <div id="eval-summary-cards">
      <div class="eval-card"><div class="eval-card-label">Total Cases</div><div class="eval-card-value" id="card-total-val">&mdash;</div></div>
      <div class="eval-card eval-card-pass"><div class="eval-card-label">Passed</div><div class="eval-card-value" id="card-passed-val">&mdash;</div></div>
      <div class="eval-card eval-card-fail"><div class="eval-card-label">Failed</div><div class="eval-card-value" id="card-failed-val">&mdash;</div></div>
      <div class="eval-card eval-card-rate"><div class="eval-card-label">Pass Rate</div><div class="eval-card-value" id="card-rate-val">&mdash;</div></div>
      <div class="eval-card"><div class="eval-card-label">Last Run</div><div class="eval-card-value" id="card-ts-val" style="font-size:13px;">&mdash;</div></div>
    </div>

    <div id="eval-filter-bar">
      <span style="font-size:12px;font-weight:600;color:#718096;">Filter:</span>
      <button class="eval-filter-btn active" onclick="applyFilter('all',this)">All</button>
      <button class="eval-filter-btn" onclick="applyFilter('PASS',this)">&#x2705; Pass</button>
      <button class="eval-filter-btn" onclick="applyFilter('FAIL',this)">&#x274C; Fail</button>
      <button class="eval-filter-btn" onclick="applyFilter('pdf_clinical',this)">&#x1F4C4; PDF Clinical</button>
      <button class="eval-filter-btn" onclick="applyFilter('happy_path',this)">&#x2705; Happy Path</button>
      <button class="eval-filter-btn" onclick="applyFilter('edge_case',this)">&#x26A0;&#xFE0F; Edge Case</button>
      <button class="eval-filter-btn" onclick="applyFilter('adversarial',this)">&#x1F6E1; Adversarial</button>
      <input id="eval-search" type="text" placeholder="Search ID or response&hellip;" oninput="applySearch(this.value)" />
    </div>

    <div id="eval-table-wrap">
      <div id="eval-empty">No results yet. Click &#x25B6; Run Eval to execute the test suite.</div>
      <table id="eval-table">
        <thead>
          <tr>
            <th>ID</th><th>Category</th><th>Status</th><th>Confidence</th>
            <th>Denial Risk</th><th>Latency</th><th>Scores</th><th>Response Preview</th>
          </tr>
        </thead>
        <tbody id="eval-tbody"></tbody>
      </table>
    </div>
    <div id="eval-spinner"><div class="spinner-ring"></div><div>Running eval suite&hellip; this may take a few minutes.</div></div>
  </div>
</div>

<!-- ── Main area ────────────────────────────────────────────────────────────── -->
<div id="main-area">

  <div id="chat-pane">
    <div id="chat">
      <div class="welcome">
        <h2>What can I help you with?</h2>
        <p>Ask about patient medications, drug interactions, or allergy conflicts. Attach a clinical PDF to unlock inline citations &mdash; click any &#x1F4C4; tag to jump to the exact page.</p>
        <div class="chips">
          <button class="chip" onclick="sendChip(this)">&#x1F48A; What medications is John Smith on?</button>
          <button class="chip" onclick="sendChip(this)">&#x26A0;&#xFE0F; Check drug interactions for Mary Johnson</button>
          <button class="chip" onclick="sendChip(this)">&#x1F6A8; Is it safe to give Robert Davis Aspirin?</button>
          <button class="chip" onclick="sendChip(this)">&#x1FA7A; Does John Smith have any known allergies?</button>
          <button class="chip" onclick="sendChip(this)">&#x1F6A8; Is it safe to give Emily Rodriguez Amoxicillin?</button>
        </div>
      </div>
    </div>

    <div id="pdf-badge">
      &#x1F4C4; <span id="pdf-badge-name"></span>
      <button onclick="clearPdf()" title="Remove PDF">&#x2715;</button>
    </div>
    <div id="input-bar">
      <button id="upload-btn" title="Attach a clinical PDF" onclick="document.getElementById('pdf-input').click()">&#x1F4CE;</button>
      <input type="file" id="pdf-input" accept=".pdf" style="display:none" onchange="handlePdfSelect(event)" />
      <textarea id="input" placeholder="Ask about a patient's medications, interactions, or allergies&hellip;" rows="1"></textarea>
      <button id="send-btn" onclick="sendMessage()">Send</button>
    </div>
  </div>

  <div id="pdf-pane">
    <div id="pdf-viewer-header">
      <div id="pdf-viewer-title">&#x1F4C4; Clinical Document</div>
      <button id="pdf-close-btn" onclick="closePdfPane()" title="Close viewer">&#x2715;</button>
    </div>
    <div id="citation-links"></div>
    <div id="pdf-canvas-container">
      <div id="pdf-status">Select a citation below to jump to the page.</div>
    </div>
  </div>

</div>

<script>
  // ── API base — all requests go to the AgentForge FastAPI backend ─────────────
  const API_BASE = 'http://127.0.0.1:8000';

  pdfjsLib.GlobalWorkerOptions.workerSrc =
    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

  // ── Health check — update the status pill ────────────────────────────────────
  (function checkHealth() {
    fetch(API_BASE + '/health')
      .then(r => r.ok ? r.json() : Promise.reject())
      .then(() => {
        document.getElementById('status-dot').className = 'online';
        document.getElementById('status-text').textContent = 'Agent online';
      })
      .catch(() => {
        document.getElementById('status-dot').className = 'offline';
        document.getElementById('status-text').textContent = 'Agent offline';
      });
  })();

  function generateThreadId() {
    return 'case_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
  }

  let threadId = generateThreadId();

  function handleNewCase() {
    threadId = generateThreadId();
    attachedPdfPath = null;
    pdfDoc          = null;
    currentPdfPath  = null;
    if (activePill) { activePill.classList.remove('active'); activePill = null; }
    pdfBadge.style.display = 'none';
    pdfBadgeName.textContent = '';
    closePdfPane();
    chat.innerHTML = `
      <div class="welcome">
        <h2>What can I help you with?</h2>
        <p>Ask about patient medications, drug interactions, or allergy conflicts. Attach a clinical PDF to unlock inline citations.</p>
        <div class="chips">
          <button class="chip" onclick="sendChip(this)">&#x1F48A; What medications is John Smith on?</button>
          <button class="chip" onclick="sendChip(this)">&#x26A0;&#xFE0F; Check drug interactions for Mary Johnson</button>
          <button class="chip" onclick="sendChip(this)">&#x1F6A8; Is it safe to give Robert Davis Aspirin?</button>
          <button class="chip" onclick="sendChip(this)">&#x1FA7A; Does John Smith have any known allergies?</button>
          <button class="chip" onclick="sendChip(this)">&#x1F6A8; Is it safe to give Emily Rodriguez Amoxicillin?</button>
        </div>
      </div>`;
    console.info('[HIPAA] Session purged. New thread: ' + threadId);
  }

  let attachedPdfPath = null;
  let pdfDoc          = null;
  let currentPdfPath  = null;
  let activePill      = null;

  const chat              = document.getElementById('chat');
  const input             = document.getElementById('input');
  const sendBtn           = document.getElementById('send-btn');
  const pdfBadge          = document.getElementById('pdf-badge');
  const pdfBadgeName      = document.getElementById('pdf-badge-name');
  const pdfPane           = document.getElementById('pdf-pane');
  const citationLinks     = document.getElementById('citation-links');
  const pdfCanvasContainer = document.getElementById('pdf-canvas-container');
  const pdfStatus         = document.getElementById('pdf-status');
  const pdfViewerTitle    = document.getElementById('pdf-viewer-title');

  input.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
  });

  // ── PDF viewer ───────────────────────────────────────────────────────────────

  function openPdfPane(anchors) {
    citationLinks.innerHTML = '';
    anchors.forEach((anchor) => {
      const btn = document.createElement('button');
      btn.className = 'citation-pill';
      btn.textContent = '\u{1F4C4} ' + anchor.label;
      btn.dataset.file = anchor.file;
      btn.dataset.page = anchor.page;
      btn.onclick = () => jumpToPage(anchor, btn);
      citationLinks.appendChild(btn);
    });
    pdfPane.classList.add('open');
    if (anchors.length > 0) {
      const firstBtn = citationLinks.querySelector('.citation-pill');
      setTimeout(() => jumpToPage(anchors[0], firstBtn), 380);
    }
  }

  function closePdfPane() {
    pdfPane.classList.remove('open');
    pdfDoc = null;
    currentPdfPath = null;
    pdfCanvasContainer.innerHTML = '<div id="pdf-status">Select a citation below to jump to the page.</div>';
    citationLinks.innerHTML = '';
    if (activePill) { activePill.classList.remove('active'); activePill = null; }
  }

  async function jumpToPage(anchor, pillBtn) {
    if (activePill) activePill.classList.remove('active');
    activePill = pillBtn;
    if (pillBtn) pillBtn.classList.add('active');
    pdfViewerTitle.textContent = '\u{1F4C4} ' + anchor.label;
    try {
      if (currentPdfPath !== anchor.file) {
        pdfStatus.textContent = 'Loading document\u2026';
        pdfCanvasContainer.innerHTML = '';
        const pdfUrl = API_BASE + '/pdf?path=' + encodeURIComponent(anchor.file);
        pdfDoc = await pdfjsLib.getDocument(pdfUrl).promise;
        currentPdfPath = anchor.file;
      }
      const page = await pdfDoc.getPage(anchor.page);
      const containerWidth = pdfCanvasContainer.clientWidth - 32;
      const baseViewport   = page.getViewport({ scale: 1.0 });
      const scale          = containerWidth / baseViewport.width;
      const viewport       = page.getViewport({ scale });

      pdfCanvasContainer.innerHTML = '';
      const wrapper = document.createElement('div');
      wrapper.className = 'pdf-page-wrapper';
      wrapper.style.width  = viewport.width + 'px';
      wrapper.style.height = viewport.height + 'px';

      const canvas = document.createElement('canvas');
      canvas.width  = viewport.width;
      canvas.height = viewport.height;
      wrapper.appendChild(canvas);

      const overlay = document.createElement('div');
      overlay.className = 'pdf-highlight-overlay';
      wrapper.appendChild(overlay);
      pdfCanvasContainer.appendChild(wrapper);

      await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
    } catch (err) {
      pdfCanvasContainer.innerHTML =
        '<div id="pdf-status">Could not load page: ' + escHtml(String(err)) + '</div>';
    }
  }

  // ── Upload ───────────────────────────────────────────────────────────────────

  async function handlePdfSelect(event) {
    const file = event.target.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('file', file);
    sendBtn.disabled = true;
    try {
      const res  = await fetch(API_BASE + '/upload', { method: 'POST', body: formData });
      const data = await res.json();
      if (data.success) {
        attachedPdfPath  = data.path;
        pdfBadgeName.textContent = data.filename;
        pdfBadge.style.display   = 'flex';
      } else {
        alert('PDF upload failed: ' + (data.error || 'Unknown error'));
      }
    } catch (e) {
      alert('Could not upload PDF. Is the AgentForge server running?');
    } finally {
      sendBtn.disabled = false;
      event.target.value = '';
    }
  }

  function clearPdf() {
    attachedPdfPath = null;
    pdfBadge.style.display   = 'none';
    pdfBadgeName.textContent = '';
  }

  function sendChip(btn) {
    const text = btn.textContent.replace(/^[\u{1F300}-\u{1FAFF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}\u26A0\u{1F6A8}\u{1FA7A}\u{1F48A}]\s*/u, '').trim();
    input.value = text;
    sendMessage();
  }

  // ── Message rendering ────────────────────────────────────────────────────────

  function appendUserMessage(text) {
    const div = document.createElement('div');
    div.className = 'msg user';
    div.innerHTML = '<div class="bubble">' + escHtml(text) + '</div>';
    chat.appendChild(div);
    scrollBottom();
  }

  function appendTyping() {
    const div = document.createElement('div');
    div.className = 'msg agent typing';
    div.id = 'typing-indicator';
    div.innerHTML = '<div class="bubble"><div class="dot"></div><div class="dot"></div><div class="dot"></div></div>';
    chat.appendChild(div);
    scrollBottom();
    return div;
  }

  function removeTyping() {
    const el = document.getElementById('typing-indicator');
    if (el) el.remove();
  }

  function buildTraceHtml(toolTrace) {
    if (!toolTrace || toolTrace.length === 0) return '';
    const steps = toolTrace.map((step, i) => `
      <div class="trace-step">
        <div class="trace-step-header">&#x1F527; Step ${i + 1} &mdash; ${escHtml(step.tool)}</div>
        <div class="trace-step-body">
          <div class="trace-label">Input</div>
          <div class="trace-json">${escHtml(JSON.stringify(step.input, null, 2))}</div>
          <div class="trace-label">Output</div>
          <div class="trace-json">${escHtml(JSON.stringify(step.output, null, 2))}</div>
        </div>
      </div>`).join('');
    return `<div class="trace-container"><details>
      <summary>&#x1F6E0;&#xFE0F; Tool Trace (${toolTrace.length} call${toolTrace.length > 1 ? 's' : ''})</summary>
      <div class="trace-steps">${steps}</div></details></div>`;
  }

  function buildDenialBadgeHtml(denialRisk) {
    if (!denialRisk || !denialRisk.risk_level) return '';
    const level = denialRisk.risk_level;
    if (level === 'NONE') return '';
    const score = denialRisk.denial_risk_score != null ? Math.round(denialRisk.denial_risk_score * 100) : null;
    const icons = { NONE:'&#x2705;', LOW:'&#x2705;', MEDIUM:'&#x26A0;&#xFE0F;', HIGH:'&#x1F534;', CRITICAL:'&#x1F6A8;', UNKNOWN:'&#x2753;' };
    const scoreText   = score != null ? ` (${score}%)` : '';
    const patterns    = (denialRisk.matched_patterns || []).map(p => p.code).join(', ');
    const patternText = patterns ? `<br><span style="font-weight:400;font-size:11px;">Patterns: ${escHtml(patterns)}</span>` : '';
    return `<div class="denial-badge denial-${level}">${icons[level] || '&#x2753;'} Denial Risk: ${level}${scoreText}${patternText}</div>`;
  }

  function buildCitationStripHtml(anchors) {
    if (!anchors || anchors.length === 0) return '';
    const btns = anchors.map((a, i) =>
      `<button class="citation-anchor-btn" onclick="handleCitationClick(${i})">&#x1F4C4; ${escHtml(a.label)}</button>`
    ).join('');
    return `<div class="citation-strip">${btns}</div>`;
  }

  let _lastAnchors = [];

  function handleCitationClick(idx) {
    const anchor = _lastAnchors[idx];
    if (!anchor) return;
    openPdfPane(_lastAnchors);
    const pill = citationLinks.querySelectorAll('.citation-pill')[idx];
    jumpToPage(anchor, pill || null);
  }

  function appendAgentMessage(data) {
    const anchors = data.citation_anchors || [];
    _lastAnchors = anchors;

    const div = document.createElement('div');
    div.className = 'msg agent';

    const escalationHtml = data.escalate
      ? '<div class="escalation-banner">&#x1F534; Physician Review Recommended &mdash; Confidence below threshold.</div>'
      : '';

    const confidencePct = Math.round((data.confidence || 0) * 100);

    div.innerHTML = `
      <div class="bubble">${escHtml(data.answer)}</div>
      ${escalationHtml}
      ${buildDenialBadgeHtml(data.denial_risk)}
      ${buildCitationStripHtml(anchors)}
      <div class="confidence">Confidence: ${confidencePct}%</div>
      <div class="disclaimer">${escHtml(data.disclaimer || '')}</div>
      ${buildTraceHtml(data.tool_trace)}
    `;
    chat.appendChild(div);
    scrollBottom();

    if (anchors.length > 0) openPdfPane(anchors);
  }

  function appendErrorMessage(text) {
    const div = document.createElement('div');
    div.className = 'msg agent';
    div.innerHTML = '<div class="bubble" style="border-color:#fc8181;color:#c53030;">' + escHtml(text) + '</div>';
    chat.appendChild(div);
    scrollBottom();
  }

  // ── Send ─────────────────────────────────────────────────────────────────────

  async function sendMessage() {
    const text = input.value.trim();
    if (!text) return;

    input.value      = '';
    sendBtn.disabled = true;
    const pdfUsed    = attachedPdfPath;
    clearPdf();

    appendUserMessage(pdfUsed ? `${text}\n\u{1F4CE} ${pdfUsed.split('/').pop()}` : text);
    appendTyping();

    try {
      const body = { question: text, thread_id: threadId };
      if (pdfUsed) body.pdf_source_file = pdfUsed;

      const res = await fetch(API_BASE + '/ask', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      });

      removeTyping();

      if (!res.ok) {
        appendErrorMessage('The AgentForge server returned an error. Please try again.');
      } else {
        const data = await res.json();
        threadId = data.thread_id || data.session_id || threadId;
        appendAgentMessage(data);
      }
    } catch (err) {
      removeTyping();
      appendErrorMessage('Could not reach the AgentForge server (localhost:8000). Ensure it is running.');
    } finally {
      sendBtn.disabled = false;
      input.focus();
    }
  }

  function scrollBottom() { chat.scrollTop = chat.scrollHeight; }

  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/\n/g, '<br>');
  }

  // ── Eval dashboard ───────────────────────────────────────────────────────────

  let _evalRows      = [];
  let _activeFilter  = 'all';
  let _activeSearch  = '';

  function openEvalPanel() {
    document.getElementById('eval-overlay').classList.add('open');
    loadEvalResults();
  }

  function closeEvalPanel() {
    document.getElementById('eval-overlay').classList.remove('open');
  }

  document.getElementById('eval-overlay').addEventListener('click', e => {
    if (e.target === document.getElementById('eval-overlay')) closeEvalPanel();
  });

  async function loadEvalResults() {
    try {
      const res  = await fetch(API_BASE + '/eval/results');
      if (!res.ok) { renderEvalEmpty(); return; }
      const data = await res.json();
      if (data.message || !data.results) { renderEvalEmpty(); return; }
      renderEvalDashboard(data);
    } catch (e) {
      renderEvalEmpty();
    }
  }

  async function runEval() {
    const btn = document.getElementById('run-eval-btn');
    btn.disabled = true;
    btn.textContent = '&#x23F3; Running\u2026';
    showEvalSpinner(true);
    try {
      const res  = await fetch(API_BASE + '/eval', { method: 'POST' });
      const data = await res.json();
      renderEvalDashboard(data);
    } catch (e) {
      alert('Eval run failed: ' + e.message);
    } finally {
      btn.disabled = false;
      btn.innerHTML = '&#x25B6; Run Eval';
      showEvalSpinner(false);
    }
  }

  function showEvalSpinner(show) {
    document.getElementById('eval-spinner').style.display        = show ? 'flex' : 'none';
    document.getElementById('eval-table-wrap').style.display     = show ? 'none' : '';
    document.getElementById('eval-summary-cards').style.display  = show ? 'none' : '';
    document.getElementById('eval-filter-bar').style.display     = show ? 'none' : '';
  }

  function renderEvalEmpty() {
    document.getElementById('eval-empty').style.display = 'block';
    document.getElementById('eval-table').style.display = 'none';
    clearSummaryCards();
  }

  function clearSummaryCards() {
    ['total','passed','failed','rate','ts'].forEach(k =>
      document.getElementById(`card-${k}-val`).textContent = '\u2014'
    );
  }

  function renderEvalDashboard(data) {
    const results = data.results || [];
    const total   = data.total   || results.length;
    const passed  = data.passed  || results.filter(r => r.passed).length;
    const failed  = data.failed  || (total - passed);
    const rate    = data.pass_rate != null ? data.pass_rate : (total > 0 ? passed / total : 0);
    const ts      = data.timestamp
      ? new Date(data.timestamp.replace(/(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})/, '$1-$2-$3T$4:$5:$6')).toLocaleString()
      : '\u2014';

    document.getElementById('card-total-val').textContent  = total;
    document.getElementById('card-passed-val').textContent = passed;
    document.getElementById('card-failed-val').textContent = failed;
    document.getElementById('card-rate-val').textContent   = (rate * 100).toFixed(1) + '%';
    document.getElementById('card-ts-val').textContent     = ts;

    _evalRows = results;
    renderEvalTable(_evalRows);
  }

  function renderEvalTable(rows) {
    const tbody = document.getElementById('eval-tbody');
    const empty = document.getElementById('eval-empty');
    const table = document.getElementById('eval-table');

    if (!rows || rows.length === 0) {
      empty.style.display = 'block';
      table.style.display = 'none';
      return;
    }
    empty.style.display = 'none';
    table.style.display = '';

    tbody.innerHTML = rows.map(r => {
      const status  = r.passed ? 'PASS' : 'FAIL';
      const cat     = r.category || '';
      const conf    = r.actual?.confidence != null ? Math.round(r.actual.confidence * 100) + '%' : '\u2014';
      const denial  = r.actual?.denial_risk_level || 'NONE';
      const lat     = r.latency_seconds != null ? r.latency_seconds.toFixed(1) + 's' : '\u2014';
      const preview = r.response_preview || '';
      const scores  = r.scores || {};

      const scoreDefs = [
        { key: 'must_contain',     label: 'Must Contain' },
        { key: 'must_not_contain', label: 'Must Not Contain' },
        { key: 'confidence_max',   label: 'Confidence' },
        { key: 'escalate',         label: 'Escalate' },
        { key: 'denial_risk',      label: 'Denial Risk' },
      ];
      const dots = scoreDefs.map(s => {
        const val = scores[s.key];
        const cls = val === true ? 'ok' : val === false ? 'fail' : 'na';
        return `<span class="score-dot ${cls}" title="${s.label}: ${val === true ? 'Pass' : val === false ? 'Fail' : 'N/A'}"></span>`;
      }).join('');

      const denialCls = `denial-inline denial-inline-${denial}`;
      const denialTxt = denial === 'NONE' ? '\u2014' : denial;

      return `<tr data-status="${status}" data-category="${cat}" data-preview="${escHtml(preview).toLowerCase()}" data-id="${escHtml(r.id)}">
        <td style="font-family:monospace;font-weight:700;white-space:nowrap;">${escHtml(r.id)}</td>
        <td><span class="cat-badge cat-${cat}">${cat.replace('_',' ')}</span></td>
        <td><span class="status-badge status-${status}">${status === 'PASS' ? '&#x2705;' : '&#x274C;'} ${status}</span></td>
        <td style="font-weight:600;text-align:center;">${conf}</td>
        <td><span class="${denialCls}">${denialTxt}</span></td>
        <td style="text-align:center;">${lat}</td>
        <td><div class="score-dots">${dots}</div></td>
        <td><div class="resp-preview">${escHtml(preview)}</div></td>
      </tr>`;
    }).join('');
  }

  function applyFilter(filter, btn) {
    _activeFilter = filter;
    document.querySelectorAll('.eval-filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    applyFiltersAndSearch();
  }

  function applySearch(val) {
    _activeSearch = val.toLowerCase().trim();
    applyFiltersAndSearch();
  }

  function applyFiltersAndSearch() {
    let rows = _evalRows;
    if (_activeFilter === 'PASS')       rows = rows.filter(r => r.passed);
    else if (_activeFilter === 'FAIL')  rows = rows.filter(r => !r.passed);
    else if (_activeFilter !== 'all')   rows = rows.filter(r => r.category === _activeFilter);
    if (_activeSearch) {
      rows = rows.filter(r =>
        (r.id || '').toLowerCase().includes(_activeSearch) ||
        (r.response_preview || '').toLowerCase().includes(_activeSearch) ||
        (r.description || '').toLowerCase().includes(_activeSearch)
      );
    }
    renderEvalTable(rows);
  }
</script>
</body>
</html>
