export type Row = Record<string, unknown> & { id: number };
export type List = { data: Row[]; meta: { page: number; per_page: number; total: number; last_page: number } };
export type Position = [number, number];
export type Geometry = { type: "Point" | "Polygon" | "MultiPolygon"; coordinates: Position | Position[][] | Position[][][] };
export type GeoFeature = { type: "Feature"; id: number; geometry: Geometry; properties: Row & { resource?: string } };
export type MapLayers = Record<string, { type: "FeatureCollection"; features: GeoFeature[] }>;
export type View = "overview" | "map" | "regulation" | "exploration" | "intelligence" | "resources" | "images" | "analysis" | "alerts" | "inspections" | "reports" | "account";

export type Field = {
  name: string;
  kind?: "text" | "number" | "date" | "datetime" | "boolean" | "select" | "textarea" | "vertices" | "map-point" | "map-area";
  choices?: string[];
};

export const resources = [["mines", "Minas"], ["monitoring-areas", "Jazidas"], ["mineral-occurrences", "Ocorrências oficiais de ouro"], ["exploration-records", "Amostras e sondagens"], ["satellite-images", "Imagens de satélite"], ["spectral-indices", "Índices espectrais"], ["change-detections", "Detecções"], ["alerts", "Alertas"], ["inspections", "Inspeções"], ["inspection-images", "Fotos de inspeção"], ["reports", "Relatórios"], ["users", "Usuários"], ["analysis-jobs", "Jobs de análise"], ["companies", "Empresa"]] as const;

export const navigation: [View, string][] = [["overview", "Visão geral"], ["map", "Mapa"], ["regulation", "Regulamentação"], ["exploration", "Exploração mineral"], ["intelligence", "Inteligência"], ["resources", "Cadastros"], ["images", "Imagens"], ["analysis", "Análises"], ["alerts", "Alertas"], ["inspections", "Inspeções"], ["reports", "Relatórios"], ["account", "Conta e API"]];

export const navigationIcons: Record<View, string> = { overview: "◫", map: "⌖", regulation: "⚖", exploration: "◈", intelligence: "✦", resources: "▦", images: "▣", analysis: "⌁", alerts: "◇", inspections: "✓", reports: "≡", account: "◎" };

const fieldLabels: Record<string, string> = { id: "ID", company_id: "Empresa (ID)", name: "Nome", description: "Descrição", status: "Status", location: "Localização", latitude: "Latitude", longitude: "Longitude", municipality: "Município", state: "Estado", country: "País", mineral: "Minério", mine_id: "Mina", monitoring_area_id: "Jazida", change_detection_id: "Detecção (ID)", geometry: "Área delimitada", monitoring_enabled: "Monitoramento ativo", email: "E-mail", password: "Senha", role: "Perfil", active: "Ativo", title: "Título", type: "Tipo", severity: "Prioridade", assigned_user_id: "Responsável (ID)", inspector_user_id: "Inspetor (ID)", scheduled_at: "Agendada para", performed_at: "Realizada em", notes: "Observações", period_start: "Início do período", period_end: "Fim do período", created_at: "Criado em", updated_at: "Atualizado em", source: "Fonte oficial", processing_status: "Processamento", document: "Documento", phone: "Telefone", event_type: "Tipo de evento", minimum_severity: "Prioridade mínima", reference_image_id: "Imagem de referência (ID)", current_image_id: "Imagem atual (ID)", analysis_type: "Tipo de análise", threshold: "Limite", category: "Categoria", external_id: "Identificador externo", legal_status: "Situação declarada", source_url: "Link da fonte", source_updated_at: "Atualização da fonte" };

export const choiceLabels: Record<string, string> = { CRITICAL: "Crítica", HIGH: "Alta", MEDIUM: "Média", LOW: "Baixa", OPEN: "Aberto", INVESTIGATING: "Em análise", RESOLVED: "Resolvido", DISMISSED: "Descartado", FAILED: "Falhou", PROCESSING: "Processando", COMPLETED: "Concluído", ACTIVE: "Ativo", INACTIVE: "Inativo", PLANNED: "Planejado", SCHEDULED: "Agendada", IN_PROGRESS: "Em andamento", CANCELLED: "Cancelada", ADMIN: "Administrador", ANALYST: "Analista", FIELD_TECHNICIAN: "Técnico de campo", VIEWER: "Visualizador", MONITORING: "Monitoramento", CHANGES: "Mudanças", INSPECTION: "Inspeção", EXECUTIVE: "Executivo", CHANGE_DETECTION: "Detecção de mudanças", GOLD_POTENTIAL: "Potencial aurífero", MINING_RIGHT: "Direito minerário", LICENSE_BOUNDARY: "Limite de licença", PROTECTED_AREA: "Área protegida", INDIGENOUS_LAND: "Terra indígena", EMBARGO: "Embargo", WATER_BODY: "Corpo d’água", TAILINGS: "Barragem/rejeito", OTHER: "Outro" };

export const forms: Record<string, Field[]> = {
  mines: [{ name: "name" }, { name: "description", kind: "textarea" }, { name: "status", kind: "select", choices: ["ACTIVE", "INACTIVE", "PLANNED"] }, { name: "geometry", kind: "map-area" }, { name: "municipality" }, { name: "state" }, { name: "country" }, { name: "mineral" }],
  "monitoring-areas": [{ name: "mine_id", kind: "number" }, { name: "name" }, { name: "description", kind: "textarea" }, { name: "geometry", kind: "map-area" }, { name: "monitoring_enabled", kind: "boolean" }],
  "compliance-layers": [{ name: "name" }, { name: "source", kind: "select", choices: ["ANM", "IBAMA", "FUNAI", "ICMBIO", "MAPBIOMAS", "STATE", "COMPANY", "OTHER"] }, { name: "category", kind: "select", choices: ["MINING_RIGHT", "LICENSE_BOUNDARY", "PROTECTED_AREA", "INDIGENOUS_LAND", "EMBARGO", "WATER_BODY", "TAILINGS", "OTHER"] }, { name: "external_id" }, { name: "legal_status" }, { name: "source_url" }, { name: "source_updated_at", kind: "date" }, { name: "geometry", kind: "map-area" }],
  users: [{ name: "name" }, { name: "email" }, { name: "password", kind: "text" }, { name: "role", kind: "select", choices: ["ADMIN", "ANALYST", "FIELD_TECHNICIAN", "VIEWER"] }, { name: "active", kind: "boolean" }],
  alerts: [{ name: "mine_id", kind: "number" }, { name: "monitoring_area_id", kind: "number" }, { name: "change_detection_id", kind: "number" }, { name: "title" }, { name: "description", kind: "textarea" }, { name: "type" }, { name: "severity", kind: "select", choices: ["LOW", "MEDIUM", "HIGH", "CRITICAL"] }, { name: "status", kind: "select", choices: ["OPEN", "INVESTIGATING", "RESOLVED", "DISMISSED"] }, { name: "assigned_user_id", kind: "number" }],
  inspections: [{ name: "mine_id", kind: "number" }, { name: "monitoring_area_id", kind: "number" }, { name: "alert_id", kind: "number" }, { name: "inspector_user_id", kind: "number" }, { name: "status", kind: "select", choices: ["SCHEDULED", "IN_PROGRESS", "COMPLETED", "CANCELLED"] }, { name: "scheduled_at", kind: "datetime" }, { name: "performed_at", kind: "datetime" }, { name: "location", kind: "map-point" }, { name: "latitude", kind: "number" }, { name: "longitude", kind: "number" }, { name: "notes", kind: "textarea" }],
  reports: [{ name: "mine_id", kind: "number" }, { name: "title" }, { name: "type", kind: "select", choices: ["MONITORING", "CHANGES", "INSPECTION", "EXECUTIVE"] }, { name: "period_start", kind: "date" }, { name: "period_end", kind: "date" }],
  "notification-rules": [{ name: "name" }, { name: "event_type" }, { name: "minimum_severity", kind: "select", choices: ["LOW", "MEDIUM", "HIGH", "CRITICAL"] }, { name: "active", kind: "boolean" }],
  "operational-assets": [{ name: "mine_id", kind: "number" }, { name: "kind", kind: "select", choices: ["TAILINGS_DAM", "LICENSE", "DRONE_SURVEY"] }, { name: "name" }, { name: "status" }, { name: "due_at", kind: "datetime" }, { name: "geometry", kind: "map-area" }],
  "exploration-records": [{ name: "mine_id", kind: "number" }, { name: "monitoring_area_id", kind: "number" }, { name: "sample_code" }, { name: "record_type", kind: "select", choices: ["SAMPLE", "DRILLHOLE"] }, { name: "lithology" }, { name: "depth_m", kind: "number" }, { name: "grade_gpt", kind: "number" }],
  companies: [{ name: "name" }, { name: "document" }, { name: "email" }, { name: "phone" }],
};

export const label = (value: string) => fieldLabels[value] ?? value.replaceAll("_", " ").replace(/\b\w/g, character => character.toUpperCase());
export const value = (item: unknown) => item === null || item === undefined || item === "" ? "—" : typeof item === "object" ? JSON.stringify(item) : String(item);
export const dateTime = (item: unknown) => item ? new Date(String(item)).toLocaleString("pt-BR") : "—";
export const errorMessage = (error: unknown) => error instanceof Error ? error.message : "Não foi possível concluir a operação.";
