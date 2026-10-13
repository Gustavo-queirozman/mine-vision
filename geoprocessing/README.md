# MineVision — geoprocessamento

Base Python/FastAPI compatível com o worker PHP existente.

## Desenvolvimento

Requer Python 3.12 ou superior.

```sh
python -m venv .venv
```

No PowerShell:

```powershell
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
$env:INTERNAL_TOKEN = 'dev-only-minevision-internal-token'
uvicorn app.main:app --reload --host 127.0.0.1 --port 8000
```

O arquivo `.env.example` documenta as variáveis; o serviço lê o ambiente do processo e não carrega `.env` automaticamente. No Compose, as variáveis já são fornecidas.

- `GET /health`: disponibilidade do serviço e estado do pipeline.
- `POST /analyze`: contrato do backend, protegido por `X-Internal-Token`.
- `/docs`: documentação interativa da API.

`/analyze` retorna HTTP 501 enquanto o pipeline não estiver implementado. Assim, jobs enviados pelo worker atual serão marcados como FAILED; nenhum resultado de detecção fictício é produzido.

`app/models.py` define entrada e saída. `app/pipeline.py` é o ponto de extensão para leitura de rasters, alinhamento, recorte, comparação, cálculo de área e vetorização. A validação geométrica completa e a integração S3 também estão pendentes.

`app/indices.py` implementa NDVI, NDWI e NBR para bandas já alinhadas em memória. Denominadores zero retornam NaN; tratamento de nodata, máscaras de nuvens e leitura de GeoTIFF devem ser feitos no futuro pipeline.

Rasterio, GeoPandas, GDAL e Shapely serão adicionados junto da implementação espacial, com as dependências nativas necessárias.

## Contexto regulatório

O backend permite registrar camadas vetoriais de fontes como ANM, IBAMA, FUNAI, ICMBio, MapBiomas, órgãos estaduais e dados da própria empresa. Para cada área monitorada, `GET /api/v1/monitoring-areas/{id}/risk-context` calcula sobreposições e prioriza a revisão. Em produção, com PostGIS, a interseção é geométrica; no SQLite local, o resultado é explicitamente aproximado e não deve orientar uma decisão operacional.

Na raiz do projeto: `docker compose up --build geoprocessing`. O serviço atende na porta 8000 da rede interna do Compose; não há porta publicada no host.
