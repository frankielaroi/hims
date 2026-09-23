---
paths:
  - 'app/Services/PostnatalSchedule.php,app/Services/MaternalDeathSurveillance.php,app/Http/Controllers/PostnatalController.php,app/Http/Controllers/MaternalDeathController.php,app/Models/TheatreOperation.php'
---

# Http Controllers Models

## Postnatal, maternal death and theatre: derived schedules and judgements left to clinicians
`PostnatalSchedule` derives Ghana's 6-hour / 6-day / 6-week contacts from `delivery.delivered_at` — nothing is stored, so correcting the delivery time moves the schedule. A contact is `overdue` once 24 grace hours pass. Facilities on WHO's 4-contact model add a row to `CONTACTS`; nothing else changes. Only live births get a postnatal check — never generate one for a stillbirth.

Maternal death: `MaternalDeathSurveillance::suggestedTiming()` only finds deaths inside the WHO 42-day window. It NEVER sets `is_maternal_death`, because WHO excludes accidental and incidental causes and that is a clinical judgement. A clinician classifies via the surveillance board; notification is due within 24 hours and late notification is itself recorded as a review finding. `DeathRecord::$attributes` defaults `is_maternal_death` to false so a freshly created record does not read as null.

Theatre: `swab_count_correct` is REQUIRED on completion and is a nullable boolean on purpose — not recorded, correct and incorrect are three different states, and a retained swab is a never-event. A caesarean's `delivery_id` must belong to the same visit.
