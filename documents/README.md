# NEAL Documents — staged implementation

This module is being built additively on branch `2026-10-11`. It is not linked to the live portal navigation and must not be considered production-ready yet.

## Files in this commit
- `migration-001.sql`: new folder, file metadata, per-user ACL and audit tables. It does not alter existing tables.
- `common.php`: session actor detection, active account checks, CSRF helpers, audit insertion and deny-by-default permission lookup.
- `STATUS-FA.md`: Persian status note.

## Mandatory validation before activation
1. Back up the live SQLite database and test restore.
2. Apply the SQL to a separate copy of `data/neal.db`, with the existing `users` and `admins` tables present.
3. Run SQLite integrity checks and inspect all created indexes.
4. Review ACL semantics, especially parent-folder inheritance and direct file overrides.
5. Implement storage API calls using server-side secret configuration only. Never commit the storage token or ONLYOFFICE JWT secret.
6. Test unauthorized direct URLs, CSRF, upload type/size checks, traversal attempts, callback signatures, and denied access logging.
7. Only after successful staging tests should navigation be integrated.

## Current limitations
- Upload, download, folder CRUD and permission-management screens are not implemented here yet.
- ONLYOFFICE editor and callback are not wired to these ACL functions yet.
- No production migration has been run.
