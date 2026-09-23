---
paths:
  - 'app/Policies/**,app/Http/Controllers/LabController.php,app/Http/Requests/Lab/**'
---

# Lab

## Lab authorisation is a licensed act — gate it with policies, never a single permission
AHPC separates three acts: collecting a specimen (`collect_lab_specimens`), entering a raw metric (`enter_lab_results`) and authorising a result for release (`authorize_lab_results`). These replaced the old fused `process_lab_orders`. A Medical Laboratory Assistant (`lab_tech`) holds the first two; only `lab_scientist` / `pathologist` hold the third.

Enforce it in LabOrderPolicy / LabOrderItemPolicy / LabSamplePolicy, called from `$this->authorize()` or a Lab form request's `authorize()` — not with ad-hoc `hasPermission()` in the controller body. Gate the matching Blade controls with `@can` too, or staff see buttons that 403.

`lab.require_second_person` (config/lab.php, default off) additionally bars whoever entered a value from signing it off. It is deliberately NOT the default: a scientist is licensed to authorise their own work, so holding the permission is the rule that binds. When it is on, authorisation is all-or-nothing per order rather than partial, so the audit trail stays readable.

Every 403 app-wide is written to `audit_logs` as `authorization_denied` by the render callback in bootstrap/app.php. Refusals are the evidence an inspector asks for — do not swallow them.
