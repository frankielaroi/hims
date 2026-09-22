# Consultation Module Enhancement Plan v2

This plan implements the comprehensive Consultation Module v2 proposal, focusing on clinical safety, structured documentation, and robust workflow logic.

## Phased Approach

### Phase 1: Clinical Safety Foundation (CURRENT)
Focus: Data integrity, safety banners, lifecycle states, and structured core documentation.

#### Database & Models
- **[NEW MIGRATION] Update `consultations` table**:
    - Add `status` (queued, assigned, in_progress, draft, ready_for_sign_off, signed, finalized).
    - Add `locked_by` (user_id), `locked_at` (timestamp).
    - Add `version` (integer) for clinical note versioning.
- **[NEW MIGRATION] Update `diagnoses` table**:
    - Add `type` (primary, secondary, comorbidity, differential).
    - Add `certainty` (confirmed, probable, possible, rule-out).
- **[NEW MIGRATION] Structured `patient_allergies` table**:
    - Replace JSON `allergies` with a table: `patient_id`, `allergen`, `reaction`, `severity`, `status`.
- **[NEW MIGRATION] `patient_chronic_medications` table**:
    - `patient_id`, `drug_id`, `drug_name`, `strength`, `dose`, `route`, `frequency`, `is_active`.
- **[NEW MIGRATION] Pregnancy Encounter Snapshot**:
    - Add to `consultations`: `pregnancy_status`, `lmp`, `edd`, `gestational_age`, `lactation_status`.

#### Backend Logic
- **Concurrency Locking (Heartbeat)**: Implement a heartbeat mechanism to track active editing.
- **Draft/Auto-save**: Implement server-side draft storage every 30 seconds.
- **Medication Reconciliation**: Logic to "Continue" chronic meds into the current prescription.
- **Allergy Engine**: Structured checking (exact, generic, class-based).

---

### Phase 2: Clinical Workflow
Focus: Integration with Lab, Radiology, Referrals, and longitudinal timeline.

- **Clinical Timeline**: Longitudinal view of all patient encounters.
- **Laboratory Overhaul**: Remove "one pending order" limit, add result review tracking.
- **Radiology Reports**: Attachment support and review workflow.
- **Disposition Workflows**: Formal Admission, Discharge, DAMA, and Deceased workflows.

---

### Phase 3: Advanced Safety & Integration
Focus: Clinical decision support and external systems.

- **Advanced Safety Engine**: Drug-drug interactions, dose limits, renal/hepatic adjustments.
- **Specialty Templates**: Pediatrics (growth charts, immunization), OB/GYN, Emergency.
- **Clinical Task Management**: Outstanding follow-ups, pending results alerts.

---

## User Review Required

- **Allergy Severity Hard-Stop**: The proposal mentions not creating an "impossible-to-bypass" hard-stop. I will implement a "Critical Warning" that requires a senior clinician's secondary approval or a very detailed justification.
- **Heartbeat Interval**: I suggest a 60-second heartbeat with a 3-minute lock expiry.

## Verification Plan (Phase 1)

### Automated Tests
- `php artisan test --filter ConsultationLifecycleTest`:
    - Verify state transitions (Draft -> Signed -> Finalized).
    - Verify note versioning (Original vs Amended).
- `php artisan test --filter ConcurrencyTest`:
    - Verify heartbeat locking and "Break-Glass" override.
- `php artisan test --filter AllergyEngineTest`:
    - Verify structured matching (Generic vs Brand).

### Manual Verification
1. Open a case; verify status changes to "In Progress" in the queue.
2. Trigger an auto-save and verify data persists after refresh.
3. Attempt to sign a consultation without a primary diagnosis (should fail).
4. Perform an "Amendment" on a signed consultation and verify version history.
