CREATE TABLE source_sync_runs (
  id BIGSERIAL PRIMARY KEY,
  company_id BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
  monitoring_area_id BIGINT NOT NULL REFERENCES monitoring_areas(id) ON DELETE CASCADE,
  completed_at TEXT NOT NULL,
  result_json JSONB NOT NULL DEFAULT '{}',
  UNIQUE(company_id, monitoring_area_id)
);
CREATE INDEX source_sync_runs_due_idx ON source_sync_runs(completed_at);
