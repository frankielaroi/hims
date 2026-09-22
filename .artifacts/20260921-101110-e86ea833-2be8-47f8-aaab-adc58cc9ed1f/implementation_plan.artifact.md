# Laboratory Information Management (LIM) Module Enhancement Plan

This plan aims to transform the basic laboratory functionality into a robust LIM module by introducing structured sample tracking, manual order entry, reagent inventory, and enhanced result validation.

## User Review Required
- **Analyzer Integration**: Real HL7 integration requires specialized middleware and hardware connection. I will implement a simulated "Result Import" feature that mimics this behavior for demonstration.
- **Barcoding**: I will generate unique alphanumeric codes that can be rendered as barcodes using a library or simple font in the UI.

## Proposed Changes

### Database & Models

#### [NEW] [LabSample.php](file:///C:/Users/Frankie/hims/app/Models/LabSample.php)
- Stores physical specimen details.
- Fields: `lab_order_id`, `barcode_number`, `specimen_type`, `collected_by`, `collected_at`, `received_at`, `status` (collected, received, processed).

#### [NEW] [LabReagent.php](file:///C:/Users/Frankie/hims/app/Models/LabReagent.php)
- Tracks lab supplies.
- Fields: `name`, `code`, `current_stock`, `min_stock_level`, `expiry_date`, `unit`.

#### [LabOrderItem.php](file:///C:/Users/Frankie/hims/app/Models/LabOrderItem.php)
- Update to include `lab_sample_id`.
- Move specimen collection fields to `LabSample`.

---

### Order & Entry Management

#### [LabController.php](file:///C:/Users/Frankie/hims/app/Http/Controllers/LabController.php)
- Add `create` and `store` methods for manual orders.
- Add `receiveSample` method to track when a sample reaches the lab.

#### [NEW] [lab/create.blade.php](file:///C:/Users/Frankie/hims/resources/views/lab/create.blade.php)
- Form to create lab orders for existing patients.

---

### Sample Tracking & Barcoding

#### [lab/show.blade.php](file:///C:/Users/Frankie/hims/resources/views/lab/show.blade.php)
- Group items by specimen type to facilitate sample collection.
- Add "Print Barcode" button.

---

### Inventory & Reagent Tracking

#### [NEW] [LabInventoryController.php](file:///C:/Users/Frankie/hims/app/Http/Controllers/LabInventoryController.php)
- Manage reagents and stock levels.

#### [NEW] [lab/inventory/index.blade.php](file:///C:/Users/Frankie/hims/resources/views/lab/inventory/index.blade.php)
- View for inventory management.

---

## Verification Plan

### Automated Tests
- `php artisan test --filter LaboratoryTest`: Verify existing flows still work.
- Add new test `tests/Feature/LabSampleTrackingTest.php` to verify the new sample-based workflow.

### Manual Verification
1. Create a lab order for a patient.
2. Collect specimens and observe barcode generation.
3. Receive samples in the lab.
4. Enter results and verify critical value flagging.
5. Verify reagent stock levels after performing tests.
