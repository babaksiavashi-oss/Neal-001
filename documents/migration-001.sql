-- NEAL document module schema; additive only.
-- Run against a database COPY first. Do not run on production without review.
CREATE TABLE IF NOT EXISTS document_folders (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 name TEXT NOT NULL,
 parent_id INTEGER NULL REFERENCES document_folders(id) ON DELETE RESTRICT,
 folder_type TEXT NOT NULL CHECK(folder_type IN ('personal','shared')),
 owner_user_id INTEGER NULL REFERENCES users(id) ON DELETE RESTRICT,
 created_by_admin_id INTEGER NULL REFERENCES admins(id) ON DELETE RESTRICT,
 created_by_user_id INTEGER NULL REFERENCES users(id) ON DELETE RESTRICT,
 is_deleted INTEGER NOT NULL DEFAULT 0 CHECK(is_deleted IN (0,1)),
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CHECK((folder_type='personal' AND owner_user_id IS NOT NULL) OR (folder_type='shared' AND owner_user_id IS NULL))
);
CREATE INDEX IF NOT EXISTS idx_doc_folders_parent ON document_folders(parent_id,is_deleted);
CREATE INDEX IF NOT EXISTS idx_doc_folders_owner ON document_folders(owner_user_id,folder_type,is_deleted);

CREATE TABLE IF NOT EXISTS document_files (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 folder_id INTEGER NOT NULL REFERENCES document_folders(id) ON DELETE RESTRICT,
 display_name TEXT NOT NULL,
 storage_id TEXT NOT NULL UNIQUE,
 extension TEXT NOT NULL,
 mime_type TEXT NOT NULL,
 file_size INTEGER NOT NULL DEFAULT 0 CHECK(file_size>=0),
 created_by_admin_id INTEGER NULL REFERENCES admins(id) ON DELETE RESTRICT,
 created_by_user_id INTEGER NULL REFERENCES users(id) ON DELETE RESTRICT,
 updated_by_user_id INTEGER NULL REFERENCES users(id) ON DELETE RESTRICT,
 version INTEGER NOT NULL DEFAULT 1,
 is_deleted INTEGER NOT NULL DEFAULT 0 CHECK(is_deleted IN (0,1)),
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_doc_files_folder ON document_files(folder_id,is_deleted);

CREATE TABLE IF NOT EXISTS document_permissions (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 folder_id INTEGER NULL REFERENCES document_folders(id) ON DELETE CASCADE,
 file_id INTEGER NULL REFERENCES document_files(id) ON DELETE CASCADE,
 user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
 can_view INTEGER NOT NULL DEFAULT 0 CHECK(can_view IN (0,1)),
 can_download INTEGER NOT NULL DEFAULT 0 CHECK(can_download IN (0,1)),
 can_upload INTEGER NOT NULL DEFAULT 0 CHECK(can_upload IN (0,1)),
 can_edit INTEGER NOT NULL DEFAULT 0 CHECK(can_edit IN (0,1)),
 can_delete INTEGER NOT NULL DEFAULT 0 CHECK(can_delete IN (0,1)),
 granted_by_admin_id INTEGER NOT NULL REFERENCES admins(id) ON DELETE RESTRICT,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CHECK((folder_id IS NOT NULL AND file_id IS NULL) OR (folder_id IS NULL AND file_id IS NOT NULL))
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_doc_perm_folder_user ON document_permissions(folder_id,user_id) WHERE folder_id IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS idx_doc_perm_file_user ON document_permissions(file_id,user_id) WHERE file_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_doc_perm_user ON document_permissions(user_id);

CREATE TABLE IF NOT EXISTS document_audit_log (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 admin_id INTEGER NULL REFERENCES admins(id) ON DELETE SET NULL,
 user_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
 action TEXT NOT NULL,
 target_type TEXT NOT NULL CHECK(target_type IN ('folder','file','permission','system')),
 target_id INTEGER NULL,
 outcome TEXT NOT NULL CHECK(outcome IN ('success','denied','failure')),
 details TEXT NOT NULL DEFAULT '',
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_doc_audit_created ON document_audit_log(created_at);
