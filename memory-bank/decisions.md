# Decisions

Important decisions and brief rationale. Add when we make choices that affect future work.

---

**Format for new entries:**
- **YYYY-MM-DD:** Short title. One line: what we decided and why.

---

- **2026-02-26:** Expand eval dataset AFTER tools are built. Writing 50+ eval cases against only the 3 MVP mock tools (get_patient_info, get_medications, check_drug_interactions) would be stale the moment the real RCM tools are wired in. Eval expansion is the last step.

- **2026-02-26:** Build `pdf_extractor` + `denial_analyzer` first (PR 1). Both are self-contained — no dependency on Pinecone, OpenEMR fork, or any external account being live. `pdf_extractor` is the core RCM differentiator. `denial_analyzer` is pure Python logic.

- **2026-02-26:** `evidence_ledger_write` is gated on OpenEMR fork. The tool calls `POST /api/rcm/verify` on the fork — it cannot be built until the `rcm_evidence_ledger` SQL table and that endpoint exist. Build it last (PR 3).

- **2026-02-26:** Production-first, not mock-first. All new tools target real production APIs (unstructured.io, Pinecone, OpenEMR FHIR R4) via env vars. No permanent local-only mocks. Local dev setup is handled at implementation time, not design time.

- **2026-02-26:** LangGraph extractor node currently calls MVP tools (tools.py). After each new tool is built, `extractor_node.py` is updated to call it — the node is the integration point for all tools.

- **2026-02-28:** Orchestrator runs a single Haiku call for intent classification and patient name extraction. When `orchestrator_ran=True`, the Extractor skips Step 0 (_extract_patient_identifier_llm) and uses `identified_patient_name` from state — halves API calls per request and avoids 529 overload.

- **2026-02-28:** policy_search has a keyword-based mock fallback when `USE_REAL_PINECONE` is not set or Pinecone/Voyage keys are missing. The tool always returns a valid result dict; production uses Pinecone + Voyage when env is configured.

- **2026-02-28:** Denial risk analysis runs only for intents INTERACTIONS and GENERAL_CLINICAL, or when a PDF is attached. It is skipped for SAFETY_CHECK (allergy/interaction checks already answer the question) and for simple list intents (e.g. MEDICATIONS, ALLERGIES) to avoid false positives.
