---
paths:
  - 'app/Services/ImciClassifier.php,app/Models/ImciAssessment.php,app/Http/Controllers/ImciController.php,app/Models/ResuscitationRecord.php,app/Models/Referral.php'
---

# Models Models

## IMCI is a table lookup, and silence is never treated as a negative finding
`ImciClassifier` implements the published WHO IMCI tables for 2–59 months. It is deliberately mechanical: the findings decide the classification with no judgement in between, which is what makes it safe for a health worker who is not a paediatrician. Do not add scoring or weighting. Nothing is invented — where a threshold is not in the tables, the finding is left unclassified.

Fast breathing is age-dependent (>=50/min under 12 months, >=40/min from 12), so never use one number. Dehydration COUNTS signs (two or more from a column) rather than weighing them.

Three places deliberately refuse to read silence as reassurance: a cough with no counted respiratory rate returns "NOT CLASSIFIED — count the breaths", an untested fever returns "MALARIA NOT EXCLUDED" (endemic setting), and `ResuscitationRecord` flags a shockable rhythm with no defibrillation. Keep that behaviour — a blank field is not a negative result.

`classifications` is stored as a snapshot because the chart the clinician acted on is part of the record even if the tables are later revised. IMCI is refused outside 2–59 months; below two months the young infant chart applies and is NOT implemented.

Referrals of `urgent`/`emergency` urgency expect `pre_referral_treatments`; `needsPreReferralRecord()` drives the warning, and the referral letter prints "NOT RECORDED" rather than staying silent.
