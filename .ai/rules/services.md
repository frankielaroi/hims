---
paths:
  - app/Services/AllergyEngine.php
---

# Services

## Allergy checking goes through AllergyEngine, never ad-hoc string matching
All prescribing-time allergy checks use `App\Services\AllergyEngine` against `patient_allergies` (the structured table). Do not reintroduce `str_contains($drug->name, $allergen)` — a short allergen entry matches almost every drug, and `generic_name` can be empty. The engine matches on word boundaries in three passes: exact (brand name), generic (`generic_name`), then class (allergen keyword -> `drugs.drug_class`, falling back to generic-name stems such as `cillin`). Allergens shorter than 3 characters are ignored.

Severity policy, decided deliberately: `severe` and `life-threatening` are "critical" and require a written justification of at least `AllergyEngine::JUSTIFICATION_MIN_LENGTH` characters to prescribe against — a warning with a gate, not an unbypassable hard-stop, because a blanket block gets worked around. Lower severities are warnings only. Every conflict shown is recorded in `allergy_conflict_checks` with `match_type`, `severity` and `was_overridden`.

The consultation screen receives conflicts precomputed by the engine (`$allergyConflicts`, keyed by drug id) so the warning the clinician sees is the same one that gates the save. Do not reimplement matching in JavaScript.

`patients.allergies` (JSON) is legacy: it was backfilled into `patient_allergies` and is intentionally still present, but clinical screens must read the structured table.
