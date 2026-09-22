---
paths:
  - 'app/Http/Controllers/LabController.php,app/Http/Controllers/RadiologyController.php,app/Services/ClinicalTaskGenerator.php'
---

# Controllers Services

## Investigations: verification is not review, and a visit can have several orders in flight
Two distinct steps, do not collapse them. **Verification** is the laboratory saying the numbers are right (`verified_by`/`verified_at`). **Review** is a clinician saying they have read them and what they are doing (`reviewed_by`/`reviewed_at`/`review_action`). Radiology mirrors this: reported vs reviewed.

A visit may have several lab orders open at once. `LabController::verify()` returns the visit to `waiting_doctor` only when `$visit->outstandingLabOrders()->count() === 0` — verifying one order while others are still running used to call the patient back with a partial picture. Use the `outstanding()` and `awaitingReview()` scopes rather than re-writing the status lists.

A `critical` result must be acknowledged per item (`critical_acknowledged_by`) before the order as a whole can be reviewed — that is the read-back record. Re-filing a radiology report clears `reviewed_at`, so an amended report has to be read again.

Radiology attachments are patient-identifiable: they go on the private `local` disk and are streamed through `downloadAttachment()` (which audits the access). Never move them to the public disk or expose a direct URL. Uploads are restricted by `mimetypes`, not by extension.

`ClinicalTaskGenerator` is a reconciler, not an event handler: it is keyed on `source_type` + `source_id` + `type`, so running it repeatedly opens new work and closes work that has since been done. It runs on each load of `/tasks` and via `php artisan clinical:generate-tasks`. Do not create `ClinicalTask` rows ad hoc from controllers — add a sync method to the generator instead.
