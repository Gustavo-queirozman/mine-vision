CREATE TABLE audit_events (
  id BIGSERIAL PRIMARY KEY,
  company_id BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
  actor_user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
  action TEXT NOT NULL,
  resource_type TEXT NOT NULL,
  resource_id BIGINT,
  payload_json JSONB NOT NULL DEFAULT '{}',
  created_at TEXT NOT NULL
);
CREATE INDEX audit_events_tenant_idx ON audit_events(company_id, created_at DESC);
CREATE INDEX audit_events_resource_idx ON audit_events(company_id, resource_type, resource_id);
