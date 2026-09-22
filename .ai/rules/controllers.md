---
paths:
  - app/Http/Controllers/PatientController.php
---

# Controllers

## Patient allergies are edited as structured rows, not a comma-separated list
`updateClinical()` writes `patient_allergies` rows (allergen + severity + reaction + status) via `syncStructuredAllergies()`. It must keep doing so: the `AllergyEngine` only reads that table, so anything that writes allergies as a plain list is invisible to prescribing safety checks.

Rules that matter:
- Matching an existing allergen is case-insensitive, so re-saving "penicillin" updates the "Penicillin" row instead of duplicating it.
- An allergen the clinician removed is set to `status = 'inactive'`, never deleted — a recorded allergy is clinical history.
- `patients.allergies` (JSON) is a deprecated mirror, rewritten from the active structured rows on every save. It is kept only so older readers and `PatientMergeController` do not silently lose allergens. Do not read it in new code.
- Severity defaults to `unknown`, which the engine treats as a warning rather than a gated critical, so the editor must keep offering the severity select.

Still outstanding: `PatientMergeController` merges only the JSON arrays, so structured allergy rows are not carried across a patient merge.
