CREATE TABLE companies (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  document TEXT,
  email TEXT NOT NULL,
  phone TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  company_id BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL CHECK(role IN ('ADMIN','ANALYST','FIELD_TECHNICIAN','VIEWER')),
  active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  UNIQUE(id,company_id)
);

CREATE INDEX users_tenant_idx ON users(company_id);

CREATE TABLE mines (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  company_id BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  description TEXT,
  status TEXT NOT NULL CHECK(status IN ('ACTIVE','INACTIVE','PLANNED')),
  latitude DOUBLE PRECISION NOT NULL,
  longitude DOUBLE PRECISION NOT NULL,
  municipality TEXT NOT NULL,
  state TEXT NOT NULL,
  country TEXT,
  mineral TEXT,
  geometry TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  UNIQUE(id,company_id),
  CHECK (latitude BETWEEN -90 AND 90),
  CHECK (longitude BETWEEN -180 AND 180)
);

CREATE INDEX mines_tenant_idx ON mines(company_id);

CREATE TABLE monitoring_areas (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  company_id BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
  mine_id BIGINT NOT NULL,
  name TEXT NOT NULL,
  description TEXT,
  geometry TEXT NOT NULL,
  area_hectares DOUBLE PRECISION NOT NULL,
  monitoring_enabled BOOLEAN NOT NULL DEFAULT TRUE,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  UNIQUE(id,company_id),
  FOREIGN KEY (mine_id,company_id) REFERENCES mines(id,company_id) ON DELETE CASCADE
);

CREATE INDEX monitoring_areas_tenant_idx ON monitoring_areas(company_id);

CREATE TABLE satellite_images (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  company_id BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
  mine_id BIGINT NOT NULL,
  monitoring_area_id BIGINT NOT NULL,
  source TEXT NOT NULL,
  satellite TEXT NOT NULL,
  acquisition_date TEXT NOT NULL,
  cloud_percentage DOUBLE PRECISION,
  storage_path TEXT,
  thumbnail_path TEXT,
  metadata_json TEXT NOT NULL DEFAULT '{}',
  processing_status TEXT NOT NULL CHECK(processing_status IN ('PENDING','PROCESSING','COMPLETED','FAILED')),
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  UNIQUE(id,company_id),
  FOREIGN KEY (mine_id,company_id) REFERENCES mines(id,company_id) ON DELETE CASCADE,
  FOREIGN KEY (monitoring_area_id,company_id) REFERENCES monitoring_areas(id,company_id) ON DELETE CASCADE
);

CREATE INDEX satellite_images_tenant_idx ON satellite_images(company_id);

CREATE TABLE spectral_indices (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  company_id BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
  satellite_image_id BIGINT NOT NULL,
  type TEXT NOT NULL CHECK(type IN ('NDVI','NDWI','NBR','CUSTOM')),
  storage_path TEXT,
  min_value DOUBLE PRECISION,
  max_value DOUBLE PRECISION,
  mean_value DOUBLE PRECISION,
  metadata_json TEXT NOT NULL DEFAULT '{}',
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  UNIQUE(id,company_id),
  FOREIGN KEY (satellite_image_id,company_id) REFERENCES satellite_images(id,company_id) ON DELETE CASCADE
);

CREATE INDEX spectral_indices_tenant_idx ON spectral_indices(company_id);

CREATE TABLE change_detections (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  company_id BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
  mine_id BIGINT NOT NULL,
  monitoring_area_id BIGINT NOT NULL,
  reference_image_id BIGINT NOT NULL,
  current_image_id BIGINT NOT NULL,
  type TEXT NOT NULL,
  severity TEXT NOT NULL CHECK(severity IN ('LOW','MEDIUM','HIGH','CRITICAL')),
  affected_area_hectares DOUBLE PRECISION NOT NULL CHECK(affected_area_hectares >= 0),
  geometry TEXT NOT NULL,
  confidence DOUBLE PRECISION NOT NULL CHECK(confidence BETWEEN 0 AND 100),
  metadata_json TEXT NOT NULL DEFAULT '{}',
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  UNIQUE(id,company_id),
  FOREIGN KEY (mine_id,company_id) REFERENCES mines(id,company_id) ON DELETE CASCADE,
  FOREIGN KEY (monitoring_area_id,company_id) REFERENCES monitoring_areas(id,company_id) ON DELETE CASCADE,
  FOREIGN KEY (reference_image_id,company_id) REFERENCES satellite_images(id,company_id) ON DELETE CASCADE,
  FOREIGN KEY (current_image_id,company_id) REFERENCES satellite_images(id,company_id) ON DELETE CASCADE
);

CREATE INDEX change_detections_tenant_idx ON change_detections(company_id);

CREATE TABLE alerts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  company_id BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
  mine_id BIGINT NOT NULL,
  monitoring_area_id BIGINT,
  change_detection_id BIGINT,
  title TEXT NOT NULL,
  description TEXT,
  type TEXT NOT NULL,
  severity TEXT NOT NULL CHECK(severity IN ('LOW','MEDIUM','HIGH','CRITICAL')),
  status TEXT NOT NULL CHECK(status IN ('OPEN','INVESTIGATING','RESOLVED','DISMISSED')),
  detected_at TEXT,
  acknowledged_at TEXT,
  resolved_at TEXT,
  assigned_user_id BIGINT,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  UNIQUE(id,company_id),
  FOREIGN KEY (mine_id,company_id) REFERENCES mines(id,company_id) ON DELETE CASCADE,
  FOREIGN KEY (monitoring_area_id,company_id) REFERENCES monitoring_areas(id,company_id) ON DELETE CASCADE,
  FOREIGN KEY (change_detection_id,company_id) REFERENCES change_detections(id,company_id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_user_id,company_id) REFERENCES users(id,company_id) ON DELETE CASCADE
);

CREATE INDEX alerts_tenant_idx ON alerts(company_id);

CREATE TABLE inspections (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  company_id BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
  mine_id BIGINT NOT NULL,
  monitoring_area_id BIGINT,
  alert_id BIGINT,
  inspector_user_id BIGINT NOT NULL,
  status TEXT NOT NULL CHECK(status IN ('SCHEDULED','IN_PROGRESS','COMPLETED','CANCELLED')),
  scheduled_at TEXT,
  performed_at TEXT,
  latitude DOUBLE PRECISION,
  longitude DOUBLE PRECISION,
  notes TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  UNIQUE(id,company_id),
  FOREIGN KEY (mine_id,company_id) REFERENCES mines(id,company_id) ON DELETE CASCADE,
  FOREIGN KEY (monitoring_area_id,company_id) REFERENCES monitoring_areas(id,company_id) ON DELETE CASCADE,
  FOREIGN KEY (alert_id,company_id) REFERENCES alerts(id,company_id) ON DELETE CASCADE,
  FOREIGN KEY (inspector_user_id,company_id) REFERENCES users(id,company_id) ON DELETE CASCADE,
  CHECK (latitude BETWEEN -90 AND 90),
  CHECK (longitude BETWEEN -180 AND 180)
);

CREATE INDEX inspections_tenant_idx ON inspections(company_id);

CREATE TABLE inspection_images (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  company_id BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
  inspection_id BIGINT NOT NULL,
  storage_path TEXT NOT NULL,
  latitude DOUBLE PRECISION,
  longitude DOUBLE PRECISION,
  captured_at TEXT,
  description TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  UNIQUE(id,company_id),
  FOREIGN KEY (inspection_id,company_id) REFERENCES inspections(id,company_id) ON DELETE CASCADE
);

CREATE INDEX inspection_images_tenant_idx ON inspection_images(company_id);

CREATE TABLE reports (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  company_id BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
  mine_id BIGINT NOT NULL,
  title TEXT NOT NULL,
  type TEXT NOT NULL CHECK(type IN ('MONITORING','CHANGES','INSPECTION','EXECUTIVE')),
  period_start TEXT NOT NULL,
  period_end TEXT NOT NULL,
  storage_path TEXT,
  status TEXT NOT NULL,
  created_by BIGINT,
  content_json TEXT NOT NULL DEFAULT '{}',
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  UNIQUE(id,company_id),
  FOREIGN KEY (mine_id,company_id) REFERENCES mines(id,company_id) ON DELETE CASCADE,
  FOREIGN KEY (created_by,company_id) REFERENCES users(id,company_id) ON DELETE CASCADE
);

CREATE INDEX reports_tenant_idx ON reports(company_id);

CREATE TABLE notification_rules (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  company_id BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  event_type TEXT NOT NULL,
  minimum_severity TEXT NOT NULL CHECK(minimum_severity IN ('LOW','MEDIUM','HIGH','CRITICAL')),
  email_enabled BOOLEAN,
  webhook_enabled BOOLEAN,
  webhook_url TEXT,
  active BOOLEAN,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  UNIQUE(id,company_id)
);

CREATE INDEX notification_rules_tenant_idx ON notification_rules(company_id);

CREATE TABLE analysis_jobs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  company_id BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
  mine_id BIGINT NOT NULL,
  monitoring_area_id BIGINT NOT NULL,
  reference_image_id BIGINT NOT NULL,
  current_image_id BIGINT NOT NULL,
  threshold DOUBLE PRECISION,
  status TEXT NOT NULL CHECK(status IN ('PENDING','PROCESSING','COMPLETED','FAILED')),
  requested_by BIGINT,
  started_at TEXT,
  completed_at TEXT,
  error TEXT,
  result_json TEXT NOT NULL DEFAULT '{}',
  attempts INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  UNIQUE(id,company_id),
  FOREIGN KEY (mine_id,company_id) REFERENCES mines(id,company_id) ON DELETE CASCADE,
  FOREIGN KEY (monitoring_area_id,company_id) REFERENCES monitoring_areas(id,company_id) ON DELETE CASCADE,
  FOREIGN KEY (reference_image_id,company_id) REFERENCES satellite_images(id,company_id) ON DELETE CASCADE,
  FOREIGN KEY (current_image_id,company_id) REFERENCES satellite_images(id,company_id) ON DELETE CASCADE,
  FOREIGN KEY (requested_by,company_id) REFERENCES users(id,company_id) ON DELETE CASCADE
);

CREATE INDEX analysis_jobs_tenant_idx ON analysis_jobs(company_id);

CREATE TABLE auth_sessions (id TEXT PRIMARY KEY, user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE, refresh_hash TEXT NOT NULL UNIQUE, expires_at BIGINT NOT NULL, revoked BOOLEAN NOT NULL DEFAULT FALSE);

CREATE TABLE rate_limits (key TEXT PRIMARY KEY, hits INTEGER NOT NULL, expires_at BIGINT NOT NULL);

CREATE INDEX jobs_pending_idx ON analysis_jobs(status,created_at);

CREATE UNIQUE INDEX one_alert_per_detection ON alerts(company_id,change_detection_id);
