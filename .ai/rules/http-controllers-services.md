---
paths:
  - 'app/Models/Payment.php,app/Models/Invoice.php,app/Http/Controllers/BillingController.php,app/Services/NhisExemption.php'
---

# Http Controllers Services

## Only settled money counts, and Ghana's exemptions are applied at charge time
`Invoice::recalculateTotals()` sums `settledPayments()`, never `payments()`. A pending mobile money row must not move a balance — the patient has not approved it on their handset yet, and counting it would release goods against a transaction that may never complete.

A cashier records money they have already seen arrive, so `Payment` defaults to `successful` and pending is opt-in via `awaiting_confirmation`. Do not flip that default: it would break every manual desk payment. When a gateway is added, it creates `pending` rows and settles them through `BillingController::settlePayment`, which is the same transition the cashier uses.

`NhisExemption` applies Ghana's point-of-service exemptions inside `Invoice::addCharge`. The charge is STILL recorded at full value — the facility needs to know what care it gave and what it is owed — but `patient_amount` becomes 0 and the item is marked `is_exempt` with a reason. Only under-fives, pregnancy recorded at this visit, and age 70+ are inferred; indigent and SSNIT status depend on documents, so a clerk sets `visits.exemption_category` and `applyTo()` never overwrites one already there. Pregnancy is never assumed from sex and age.

Claims use `nhis_tariff` (G-DRG) where recorded, falling back to the facility price. Claiming the facility price when a tariff exists is why claims fail to reconcile with NHIA.
