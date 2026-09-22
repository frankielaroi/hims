---
paths:
  - 'app/Services/PrescriptionSafetyEngine.php,app/Services/ImmunizationSchedule.php,app/Models/GrowthMeasurement.php'
---

# Services Models

## Clinical decision support: composed checks, and no invented reference data
`PrescriptionSafetyEngine` is the single entry point for prescribing checks. It composes allergy (delegated to `AllergyEngine`), interaction, dose and organ-function checks into one list of findings. Every finding carries `requires_justification`; the engine never silently blocks. Add new check categories as a method returning findings in the same shape, not as a separate gate in the controller.

Justification keys: allergy findings stay keyed by drug id under `allergy_justifications[...]` (the long-standing form contract); every other category is keyed by the finding's `key` under `safety_justifications[...]`. Keep both paths in `findingIsJustified()`.

Dose checks only fire when a dose is numeric and a limit is recorded. "1 tablet" cannot be compared to a milligram ceiling, and a drug with no recorded limit is deliberately not checked rather than wrongly flagged. `dosesPerDay()` understands OD/BD/TDS/QDS and returns null otherwise.

**Do not invent clinical reference data.** The paediatric growth module plots the child's own trajectory and grades acute malnutrition from MUAC using the published 11.5/12.5 cm thresholds for 6-59 months. It deliberately does **not** compute WHO weight-for-age percentiles, because the LMS reference tables are not bundled — fabricating them would be dangerous. If percentiles are wanted, load the real WHO tables first. The same rule applies to `DrugSafetySeeder`: it is a small starter set of well-documented interactions, not a substitute for a licensed interactions database.
