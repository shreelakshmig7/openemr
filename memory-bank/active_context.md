# Active Context

**Current focus:** Real FHIR tools (patient_lookup, med_retrieval) and/or evidence_ledger_write when OpenEMR fork is ready

**Updated:** 2026-02-28

---

## Done (as of 2026-02-28)

- **PR 1:** `pdf_extractor` + `denial_analyzer` — built, tested, wired in extractor_node (with content-hash cache for PDFs).
- **Policy search:** `tools/policy_search.py` — built; Pinecone + Voyage when `USE_REAL_PINECONE=true`, else keyword mock. Extractor calls it when `policy_search` is in `tool_plan`.
- **Orchestrator:** Single Haiku call for intent + patient name; extractor skips Step 0 when `orchestrator_ran=True`.
- **Eval:** 52 test cases in `eval/golden_data.yaml`; runner `eval/run_eval.py`; results in `tests/results/`.

---

## Next steps (in order)

| Priority | Work | Notes |
|---|---|---|
| 1 | **Real patient_lookup** | Replace mock `get_patient_info` with OpenEMR FHIR R4 Patient. Needs `OPENEMR_BASE_URL`. |
| 2 | **Real med_retrieval** | Replace mock `get_medications` with OpenEMR FHIR R4 MedicationRequest. Same env. |
| 3 | **evidence_ledger_write** | Gated on OpenEMR fork: `rcm_evidence_ledger` table + `POST /api/rcm/verify`. Build last. |
| 4 (optional) | **Eval expansion** | Add more golden cases after real FHIR + evidence_ledger so evals cover full tool surface. |

---

## Agreed tool build order (reference)

| Priority | Tool | Status |
|---|---|---|
| 1 | `pdf_extractor` | ✅ Done |
| 1 | `denial_analyzer` | ✅ Done |
| 2 | `policy_search` | ✅ Done (Pinecone or mock) |
| 2 | `patient_lookup` (real FHIR) | ❌ Not yet |
| 2 | `med_retrieval` (real FHIR) | ❌ Not yet |
| 3 | `evidence_ledger_write` | ❌ Not yet — gated on fork |

**Single source of truth for decisions and status:** see [agent-reference.md](agent-reference.md) and [critical_facts.md](critical_facts.md).

---

## Pending documentation (TODO tomorrow)

- **User manual update needed:** Document the `policy_search` TDD test suite and all the scenarios it covers. Specifically:
  - How `payer_id` / `procedure_code` are now extracted from query text (mirrors patient_name pattern)
  - The MRI / imaging procedure distinction — "criteria for MRI" vs "what does MRI show"
  - Mock vs Pinecone mode routing (`USE_REAL_PINECONE` env var)
  - Known payers in mock data: `cigna`, `aetna`, `uhc`
  - The `no_policy_found` safe response for unknown/missing payers
  - New test files: `tests/test_policy_search.py` (31 tests), new classes in `tests/test_langgraph_orchestrator.py` (15 tests)
