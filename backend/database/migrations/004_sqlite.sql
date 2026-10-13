CREATE TABLE compliance_layers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  company_id BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  source TEXT NOT NULL CHECK(source IN ('ANM','IBAMA','FUNAI','ICMBIO','MAPBIOMAS','STATE','COMPANY','OTHER')),
  category TEXT NOT NULL CHECK(category IN ('MINING_RIGHT','LICENSE_BOUNDARY','PROTECTED_AREA','INDIGENOUS_LAND','EMBARGO','WATER_BODY','TAILINGS','OTHER')),
  external_id TEXT,
  legal_status TEXT,
  source_url TEXT,
  source_updated_at TEXT,
  geometry TEXT NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  UNIQUE(id,company_id)
);
CREATE INDEX compliance_layers_tenant_idx ON compliance_layers(company_id);
