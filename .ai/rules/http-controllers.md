---
paths:
  - 'app/Services/PrescriptionSafetyEngine.php,app/Http/Controllers/ConsultationController.php'
---

# Http Controllers

## Safety checks run against the submitted encounter, not the stored consultation
`PrescriptionSafetyEngine::check()` reads pregnancy_status, lmp, gestational_age, lactation_status, renal_impairment, hepatic_impairment, egfr and weight_kg off the consultation. Those fields are only persisted inside the transaction in `store()`, so passing the fetched row checked the PREVIOUS encounter — and checked nothing at all on a first save, silently disabling the organ and paediatric-dose gates over HTTP. Always pass `encounterSafetyContext($consultation, $validated)`, a detached copy carrying what was just submitted. Add new safety-relevant consultation fields to `ENCOUNTER_SAFETY_FIELDS` or their checks will be inert.

Pregnancy/lactation categories use a controlled vocabulary on `drugs` (safe / caution / avoid_first_trimester / avoid_third_trimester / contraindicated; lactation: safe / caution / avoid), not the retired FDA letter grades. A drug with no category recorded raises nothing — silence means "not recorded", never "safe", matching the dose checks. A trimester-specific finding blocks only inside its trimester and stays advisory when no LMP or gestational age is recorded, because a missing date is not evidence of safety.
