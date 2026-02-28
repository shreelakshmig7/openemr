# Agent Reference — OpenEMR Agent (AgentForge RCM)

Single reference for AI and developers: feature decisions, what’s done, what’s left, and architecture.  
Complements the other memory-bank files (see [README](README.md)).

**Last updated:** 2026-02-28

---

## 1. Overview

- **Project:** `openemr-agent/` — Healthcare RCM (Revenue Cycle Management) AI agent inside the OpenEMR monorepo.
- **Purpose:** Natural language queries → LangGraph pipeline → tools (patient/meds, PDF extraction, policy search, denial risk) → cited, auditable responses.
- **Stack:** Python 3.9+, FastAPI, LangGraph, LangChain, Claude (Anthropic), LangSmith. Optional: Pinecone, Voyage AI, unstructured.io.
- **Live:** [Railway](https://openemr-agent-production.up.railway.app); see `openemr-agent/README.md` for runbook.

---

## 2. Feature decisions (summary)

Full rationale lives in [decisions.md](decisions.md). Summary:

| Decision | Summary |
|----------|--------|
| **Tool build order** | (1) pdf_extractor + denial_analyzer (self-contained); (2) policy_search, patient_lookup, med_retrieval; (3) evidence_ledger_write last (gated on OpenEMR fork). |
| **Eval expansion** | Expand eval dataset (50+ cases) after all real tools are built, so evals cover the real tool surface. |
| **evidence_ledger_write** | Gated on OpenEMR fork: requires `rcm_evidence_ledger` table and `POST /api/rcm/verify`. Not buildable until that endpoint exists. |
| **Production-first** | New tools target real APIs (unstructured.io, Pinecone, OpenEMR FHIR) via env; no permanent local-only mocks. Mock fallbacks (e.g. policy_search keyword search) for dev when real backends are unset. |
| **Extractor integration** | After each new tool is built, `langgraph_agent/extractor_node.py` is updated to call it; the extractor is the single integration point for all tools. |
| **Orchestrator single call** | One Haiku call in the Orchestrator does intent classification + patient name extraction. Extractor skips Step 0 when `orchestrator_ran=True` to halve API calls and avoid 529 overload. |
| **Policy search fallback** | When `USE_REAL_PINECONE` is not set, policy_search uses an in-repo keyword-based mock so the tool always returns a valid result without Pinecone/Voyage. |
| **Denial risk intents** | Denial risk analysis runs only for `INTERACTIONS`, `GENERAL_CLINICAL`, or when a PDF is attached; not for `SAFETY_CHECK` (allergy/interaction already answer the question) or simple list intents (e.g. MEDICATIONS, ALLERGIES). |

---

## 3. What’s done

### 3.1 Tools

| Tool | Location | Status | Notes |
|------|----------|--------|--------|
| **get_patient_info** | `tools/__init__.py` | Done (mock) | Reads `mock_data/patients.json`. Used as patient_lookup stand-in until real FHIR. |
| **get_medications** | `tools/__init__.py` | Done (mock) | Reads `mock_data/medications.json`. Used as med_retrieval stand-in. |
| **check_drug_interactions** | `tools/__init__.py` | Done (mock) | Reads `mock_data/interactions.json`. |
| **pdf_extractor** | `pdf_extractor.py` | Done (live) | unstructured.io API; verified. Content-hash cache in state (`extracted_pdf_hash`, `extracted_pdf_pages`). |
| **denial_analyzer** | `denial_analyzer.py` | Done | Pure logic; no external API. |
| **policy_search** | `tools/policy_search.py` | Done | Pinecone + Voyage when `USE_REAL_PINECONE=true` and keys set; else keyword mock. |
| **check_allergy_conflict** | `verification.py` | Done | Drug vs allergy list (name + class); used in SAFETY_CHECK path. |

### 3.2 LangGraph nodes

| Node | File | Status |
|------|------|--------|
| **state** | `langgraph_agent/state.py` | Done — AgentState, create_initial_state, Layer 1/2/3 memory fields. |
| **router** | `langgraph_agent/router_node.py` | Done — intent classification. |
| **orchestrator** | `langgraph_agent/orchestrator_node.py` | Done — tool_plan, identified_patient_name, single Haiku call. |
| **extractor** | `langgraph_agent/extractor_node.py` | Done — calls patient, meds, interactions, PDF, policy_search, denial_analyzer, allergy conflict; Layer 2 cache writes. |
| **auditor** | `langgraph_agent/auditor_node.py` | Done — verification, response synthesis, citation handling. |
| **clarification** | `langgraph_agent/clarification_node.py` | Done — pause for user input. |
| **workflow** | `langgraph_agent/workflow.py` | Done — graph assembly, run_workflow, SQLite checkpointer (Layer 3). |

### 3.3 API and eval

- **FastAPI:** `main.py` — `/`, `/health`, `POST /ask`, `POST /upload`, `GET /pdf`, `POST /eval`, `GET /eval/results`, `GET /api/audit/{thread_id}` (when `AUDIT_TOKEN` set).
- **Eval:** 52 test cases in `eval/golden_data.yaml`; runner `eval/run_eval.py`; results under `tests/results/`. Cases **gs-036 through gs-052** require test PDFs in `mock_data/`: **`AgentForge_Test_PriorAuth.pdf`** and **`AgentForge_Test_ClinicalNote.pdf`** (see `openemr-agent/mock_data/README.md`).
- **Tests:** Unit tests for tools, verification, denial_analyzer, pdf_extractor, state, orchestrator, extractor, workflow, clarification, auditor, eval, main; conversation/agent tests require `ANTHROPIC_API_KEY`.

---

## 4. What’s left

| Item | Description | Blocker / notes |
|------|-------------|------------------|
| **Real patient_lookup** | Replace mock `get_patient_info` with OpenEMR FHIR R4 Patient lookup. | Needs `OPENEMR_BASE_URL` and FHIR client; extractor already calls `tool_get_patient_info` — swap implementation. |
| **Real med_retrieval** | Replace mock `get_medications` with OpenEMR FHIR R4 MedicationRequest. | Same env; swap in `tools` or add `tools/fhir_medications.py` and wire in extractor. |
| **evidence_ledger_write** | Tool that calls `POST /api/rcm/verify` on OpenEMR fork and writes to `rcm_evidence_ledger`. | **Gated on OpenEMR fork:** table and endpoint must exist first. Build last (PR 3). |
| **Eval expansion (optional)** | Grow golden set beyond 52 cases after real FHIR + evidence_ledger are in place. | Per decision: evals should target real tool surface. |
| **PII (optional)** | Replace stub PII scrubber in extractor with e.g. Microsoft Presidio. | Noted in extractor as TODO. |

### 4.1 Known gaps — policy_search flow (to discuss)

These were discovered during UI testing on 2026-02-28 and are **not yet implemented**. Discuss before touching any code.

---

**GAP 1 — Missing payer clarification trigger**

- **What happens now:** "Does John Smith meet criteria for knee replacement?" (no payer mentioned) → Orchestrator extracts `payer_name: null` → extractor runs `policy_search` with `payer_id: ""` → `no_policy_found` returned silently → response says "I cannot determine."
- **What should happen:** Clarification node should fire and ask the user for the payer name before proceeding, exactly the same way it asks for a patient name when none is provided.
- **Example query that breaks:** `"Does John Smith meet criteria for knee replacement?"` (no payer)
- **Expected response:** `"Which insurance payer should I check criteria for? (e.g. Cigna, Aetna, UHC)"`
- **Where to fix:** `langgraph_agent/extractor_node.py` — add a guard before the `if "policy_search" in tool_plan` block: if `payer_id` is empty, set `clarification_needed` and `pending_user_input`, return early.
- **Open questions before implementing:**
  - Should it fire even when a PDF is attached (user might want payer inferred from PDF)?
  - Should it also ask for `procedure_code` if that is missing too, or ask only for payer?
  - Clarification response feeds back through Orchestrator (Haiku re-extracts payer from reply) — confirm this is the intended resume path.

---

**GAP 2 — "Criteria for MRI" refused as out-of-domain**

- **What happens now:** "Does John Smith meet Aetna's criteria for MRI?" → Agent replies "I am a specialized Healthcare RCM agent. I can only assist with clinical documentation, patient medications, drug interactions, allergy checks, and insurance verification." Confidence: 100%.
- **What should happen:** Query is a valid prior-auth criteria check — MRI is the procedure being authorized. Policy_search should run with `payer_id: "aetna"`, `procedure_code: "mri"`.
- **Example query that breaks:** `"Does John Smith meet Aetna's criteria for MRI?"`
- **Root cause (hypothesis):** Something downstream of the Orchestrator (likely the router node or the adversarial guard in the auditor) is seeing "MRI" and classifying the query as a radiology / imaging question outside RCM scope. The Orchestrator prompt fix (pdf_required=false for policy+imaging procedure) did not touch the router or auditor adversarial filter.
- **Where to investigate:** `langgraph_agent/router_node.py` and `langgraph_agent/auditor_node.py` — check what keywords or rules trigger the out-of-scope rejection.
- **Note:** Aetna IS in mock data, so once routing is fixed, the policy_search would return real criteria.

---

## 5. Architecture (concise)

- **Graph:** START → router → (OUT_OF_SCOPE → output → END; else → orchestrator) → extractor → (pending_user_input → clarification → END; else → auditor) → by routing_decision: pass/partial → output → END; missing/ambiguous → clarification or extractor (review loop).
- **Memory:** Layer 1 = `messages` (conversation history); Layer 2 = session caches (`extracted_patient`, `extracted_pdf_pages`/`extracted_pdf_hash`, `payer_policy_cache`, etc.); Layer 3 = SQLite checkpointer (`agent_checkpoints.sqlite`, `thread_id` = `session_id`).
- **Env (production):** `ANTHROPIC_API_KEY`, `UNSTRUCTURED_API_KEY`; optional: `LANGSMITH_API_KEY`, `PINECONE_API_KEY`, `PINECONE_INDEX`, `VOYAGE_API_KEY`, `USE_REAL_PINECONE`, `OPENEMR_BASE_URL` (for future FHIR), `AUDIT_TOKEN`.
- **Coding standards:** See `openemr-agent/docs/CODING_STANDARDS.md` (TDD, docstrings, type hints, structured error dicts, no hardcoded keys/TODOs).

---

## 6. Where to look in code

| Need | Location |
|------|----------|
| Tool implementations | `openemr-agent/tools/` (`__init__.py`, `policy_search.py`) |
| PDF extraction | `openemr-agent/pdf_extractor.py` |
| Denial risk | `openemr-agent/denial_analyzer.py` |
| Allergy/drug checks | `openemr-agent/verification.py` |
| Graph and state | `openemr-agent/langgraph_agent/` (state, workflow, router, orchestrator, extractor, auditor, clarification) |
| API and uploads | `openemr-agent/main.py` |
| Eval cases and runner | `openemr-agent/eval/golden_data.yaml`, `openemr-agent/eval/run_eval.py` |
| Mock data and test PDFs | `openemr-agent/mock_data/` — see `mock_data/README.md`. Evals gs-036–gs-052 require `AgentForge_Test_PriorAuth.pdf` and `AgentForge_Test_ClinicalNote.pdf`. |
| Decisions and rationale | `memory-bank/decisions.md` |
| Facts and env | `memory-bank/critical_facts.md` |
| Current focus | `memory-bank/active_context.md` |
