---
paths:
  - 'app/Http/Controllers/DeliveryController.php,app/Models/Delivery.php,app/Models/NewbornRecord.php,app/Services/PartographMonitor.php'
---

# Models Services

## A delivery has many babies, and the partograph lines are derived not stored
`deliveries` is the birth episode; babies live in `newborn_records` because a delivery can produce several and twins do not always share an outcome. Birth order is assigned from entry order in the controller, never taken from the form. Never collapse a baby's fields onto the delivery.

`newborn_records.patient_id` is the baby's own registry record and stays null until registered. Registration is what links mother to child, and it takes the date of birth from `delivery.delivered_at` rather than input — that date drives the whole EPI schedule via ImmunizationSchedule. Only a live birth can be registered.

PPH thresholds differ by mode: 500 ml vaginal, 1000 ml caesarean (`Delivery::hasPostpartumHaemorrhage`). `NewbornRecord::alerts()` returns ONLY the stillbirth line for a stillbirth — do not let it nag about vitamin K or feeding for a baby that did not live.

`PartographMonitor` derives the alert and action lines from the readings; nothing is stored, so correcting an entry moves the lines. Active phase starts at 4 cm, expected 1 cm/hour, action line 4 hours right of alert, expected dilatation capped at 10. Crossing either line is written to the audit log.
