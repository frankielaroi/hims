---
paths:
  - 'app/Models/Invoice.php,app/Models/Payment.php,app/Services/NhisExemption.php'
---

# Models Models Services

## Exempt does not mean free, and only settled payments count
`Invoice::recalculateTotals()` sums `settledPayments()`, never all payments. Mobile money is approved on the patient's handset after the row is written, so counting a pending row would release goods against money that has not moved. Payment defaults to `successful` (cash/card across the desk); pending is opt-in.

NHIS exemption: `NhisExemption` records the category on the visit and `addCharge` flags the line, but the patient amount is NOT zeroed unless `config('billing.waive_exempt_charges')` is on. Ghana's exempt groups are exempt from PREMIUMS and covered by the scheme — that is not the same as every service being free at the desk. An exempt line is only ever flagged when the payer is `cash`, i.e. NHIS is not paying for it, so waiving by default would mean the facility is never paid at all. Do not "fix" this by zeroing it.

Pregnancy is only inferred from a status recorded at the visit or an ANC contact — never from age and sex.

`payer` stays cash/nhis for exempt lines so existing claim queries keep working; it is an enum with a CHECK constraint, so adding a value needs a table rebuild.

NHIA reimburses at the G-DRG tariff (`nhis_tariff` on services and lab_tests), not the facility price. `addCharge`'s last argument carries it and falls back to the price when no tariff is loaded, so a claim is never silently valued at zero.
