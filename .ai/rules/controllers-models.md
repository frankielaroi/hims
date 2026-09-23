---
paths:
  - 'app/Services/AntenatalSchedule.php,app/Http/Controllers/AncController.php,app/Models/AncAssessment.php'
---

# Controllers Models

## IPTp and tetanus are dose-numbered and derived, never booleans off the form
Ghana's antenatal programme is audited on at least three IPTp-SP doses and on where the woman sits in the TT1-TT5 schedule, so `anc_assessments` records `iptp_dose_number` / `tt_dose_number`, not yes/no flags. A boolean per contact can never prove a third dose was given.

The form posts `iptp_given` / `tt_given` as checkboxes; `AncController` derives the dose NUMBER from what is already on the record, the same way `contact_number` is derived. Never accept a dose number as input.

`AntenatalSchedule` holds the rules, mirroring `ImmunizationSchedule` — nothing is stored, everything is derived from recorded contacts. IPTp is refused before 13 weeks (SP is not given in the first trimester) and within 28 days of the last dose. An UNKNOWN gestational age is refused too: that is the case the gate exists for, so it is never assumed safe. Tetanus stops at 5 doses.

`PROFILE_ITEMS` drives the outstanding-booking-profile list. Rh-negative, reactive HIV/syphilis/hepatitis B and positive sickling are danger signs on the assessment, because each has an action attached (anti-D, PMTCT, treat partner, birth-dose prophylaxis, electrophoresis).
