# HIMS Implementation Plan

## Proposed Changes

### Core / Auth (Module 1)
- Custom RBAC system with `roles` and `permissions`.
- Advanced login with account locking and mandatory password change.
- Single Active Session enforcement.
- Security Audit Logging.

### Patient Registration & Identity (Module 2)
- Single source of truth for patient identity.
- **Forced Search**: Registry home enforces a search before allowing new registrations.
- **Identity Logging**: Changes to sensitive fields (Name, DOB) are logged in `patient_identity_logs`.
- **Clinical Safety**: Allergies, Chronic Conditions, and Blood Group tracking.
- **Insurance History**: Dedicated table for NHIS history.
- **Admin Merge**: Tool to combine duplicate records and preserve data integrity.

## Technical Details

#### [Patient Controller](file:///C:/Users/Frankie/hims/app/Http/Controllers/PatientController.php)
- Handles search, registration, profile viewing, and clinical safety updates.

#### [Merge Controller](file:///C:/Users/Frankie/hims/app/Http/Controllers/Admin/PatientMergeController.php)
- Exclusive to Admin role for cleaning up registry duplicates.

---

## Verification Plan

### Automated Tests
- `php artisan test` (Runs all auth and patient identity tests).
- Specific coverage for:
    - Forced search logic.
    - Duplicate detection.
    - Identity audit trail.
    - RBAC for clinical safety data.
