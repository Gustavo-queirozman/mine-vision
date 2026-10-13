# MineVision — plano de implementação

1. Monorepo, Compose, migrações PostGIS e seed idempotente.
2. Domínio PHP compartilhado, adaptador Hyperf, autenticação JWT, autorização e isolamento por empresa.
3. CRUD, mapa GeoJSON, jobs, alertas, inspeções, fotos e relatórios.
4. Next.js com mapa interativo, desenho de polígonos e seleção de Sentinel-1/Sentinel-2.
5. Adaptador Copernicus Data Space Ecosystem: catálogo, OAuth servidor-servidor e prévias reais.
6. Pipeline Python modular para diferença espacial; NDVI/NDWI/NBR aguardam leitura e alinhamento de rasters.
7. Testes de segurança, integração e processamento; builds e documentação.

Escopo inicial: monitoramento operacional de referências auríferas brasileiras. As geometrias iniciais são aproximadas e não substituem limites de concessão, licenças ou validação em campo. Satélites e índices não confirmam veios ou reservas.

Validação local alternativa: PHP CLI + SQLite no mesmo domínio PHP, para testes em Windows sem Docker. PostGIS é o banco da composição padrão. A compatibilidade SQLite é exclusiva do desenvolvimento e não substitui os testes espaciais PostGIS.
