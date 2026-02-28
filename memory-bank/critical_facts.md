# Critical Facts

Facts about this project that should stay consistent across sessions.

---

- **Workspace:** OpenEMR monorepo; `openemr-agent/` is the agent/API subproject (Python/FastAPI).
- **Stack:** See root `CLAUDE.md` for PHP/OpenEMR; see `openemr-agent/` docs for Python stack.

## Agent tool status (as of 2026-02-28)

| Tool | File | Status | Notes |
|---|---|---|---|
| `get_patient_info` | `tools/__init__.py` | ✅ Built | Mock — reads `mock_data/patients.json` (stand-in for real patient_lookup) |
| `get_medications` | `tools/__init__.py` | ✅ Built | Mock — reads `mock_data/medications.json` (stand-in for real med_retrieval) |
| `check_drug_interactions` | `tools/__init__.py` | ✅ Built | Mock — reads `mock_data/interactions.json` |
| `pdf_extractor` | `pdf_extractor.py` | ✅ Built + Live | unstructured.io API; content-hash cache in state |
| `policy_search` | `tools/policy_search.py` | ✅ Built | Pinecone + Voyage when `USE_REAL_PINECONE=true`; else keyword mock |
| `denial_analyzer` | `denial_analyzer.py` | ✅ Built | Pure custom logic; no external API |
| `check_allergy_conflict` | `verification.py` | ✅ Built | Drug vs allergy list (name + class); SAFETY_CHECK path |
| **patient_lookup (real)** | — | ❌ Not yet | OpenEMR FHIR R4 Patient; replace mock when `OPENEMR_BASE_URL` ready |
| **med_retrieval (real)** | — | ❌ Not yet | OpenEMR FHIR R4 MedicationRequest |
| **evidence_ledger_write** | — | ❌ Not yet | Calls `POST /api/rcm/verify` — gated on OpenEMR fork |

## LangGraph node status (as of 2026-02-28)

| File | Status |
|---|---|
| `langgraph_agent/state.py` | ✅ Built |
| `langgraph_agent/router_node.py` | ✅ Built |
| `langgraph_agent/orchestrator_node.py` | ✅ Built |
| `langgraph_agent/extractor_node.py` | ✅ Built — calls patient, meds, interactions, PDF, policy_search, denial_analyzer, allergy conflict |
| `langgraph_agent/auditor_node.py` | ✅ Built |
| `langgraph_agent/clarification_node.py` | ✅ Built |
| `langgraph_agent/workflow.py` | ✅ Built |

## Key env vars required (production)

- `ANTHROPIC_API_KEY` — Claude (Orchestrator, Extractor, Auditor)
- `LANGSMITH_API_KEY` — observability tracing
- `UNSTRUCTURED_API_KEY` — pdf_extractor
- `PINECONE_API_KEY`, `PINECONE_INDEX`, `VOYAGE_API_KEY` — policy_search (when `USE_REAL_PINECONE=true`)
- `OPENEMR_BASE_URL` — future real patient_lookup, med_retrieval, evidence_ledger_write

## Coding standards (mandatory — see `docs/CODING_STANDARDS.md`)

- TDD: tests written and confirmed FAIL before any implementation file is created
- Every file: module docstring in exact format
- Every function: docstring with Args/Returns/Raises + full type annotations
- Error handling: `try/except` returning structured dict — never raise to caller
- No hardcoded API keys, magic numbers, or TODO comments in committed code
- Run full eval suite after every new feature; save results to `tests/results/`
