# MineVision

Plataforma para monitoramento e prospecção mineral. O projeto combina uma interface web, uma API multi-tenant, processamento geoespacial e serviços de infraestrutura executados em contêineres.

## Tecnologias

| Camada | Tecnologias |
| --- | --- |
| Frontend | Next.js 16 (App Router), React 19, TypeScript, Tailwind CSS 4 e Vitest |
| Backend | PHP 8.3, Hyperf 3, Swoole, Redis, PostgreSQL/PostGIS e Composer |
| Geoprocessamento | Python 3.12, FastAPI, Uvicorn, NumPy, Pillow, tifffile, Requests e Boto3 |
| Infraestrutura | Docker, Docker Compose, PostgreSQL 16 com PostGIS e Redis 7 |

## Pré-requisitos

O caminho recomendado é Docker Compose. Instale apenas Docker Desktop (com o Docker Compose v2) e garanta que as portas `3000` e `9501` estejam livres.

Para executar os serviços diretamente no computador, instale também Node.js 22, PHP 8.3 com as extensões requeridas pelo Hyperf, Composer e Python 3.12 ou superior. PostgreSQL/PostGIS e Redis precisam estar disponíveis para o backend local.

## Executar com Docker (recomendado)

1. Crie o arquivo de configuração local a partir do exemplo:

   ```powershell
   Copy-Item .env.example .env
   ```

2. Para usar dados reais do Copernicus, preencha `COPERNICUS_CLIENT_ID` e `COPERNICUS_CLIENT_SECRET` em `.env`. Para desenvolvimento com dados de demonstração, mantenha-os em branco.

3. Inicie toda a aplicação:

   ```powershell
   docker compose up --build
   ```

4. Acesse:

   - Frontend: http://localhost:3000
   - API do backend: http://localhost:9501

O Compose sobe também PostGIS, Redis, o worker PHP e o serviço de geoprocessamento. Este último fica disponível apenas na rede interna dos contêineres, na porta `8000`.

Para encerrar os serviços, use `docker compose down`. Para remover também os volumes locais do banco, Redis e dados gerados, use `docker compose down --volumes`.

## Variáveis de ambiente

Copie `.env.example` para `.env`; nunca versione o arquivo `.env`. As variáveis mais importantes são:

| Variável | Finalidade |
| --- | --- |
| `DB_PASSWORD` | Senha do PostgreSQL/PostGIS local |
| `JWT_SECRET` | Chave para assinatura dos tokens da API |
| `INTERNAL_TOKEN` | Token de comunicação entre backend e geoprocessamento |
| `DEMO_PASSWORD` | Senha dos usuários criados pela carga de demonstração |
| `COPERNICUS_CLIENT_ID` / `COPERNICUS_CLIENT_SECRET` | Credenciais do Copernicus Data Space Ecosystem |

Os valores de exemplo servem somente para desenvolvimento. Gere segredos próprios antes de qualquer implantação.

## Desenvolvimento sem Docker

Cada serviço possui seu próprio arquivo de configuração de exemplo e documentação complementar. Em terminais separados:

```powershell
# Frontend
Set-Location frontend
Copy-Item .env.example .env.local
npm ci
npm run dev
```

```powershell
# Geoprocessamento
Set-Location geoprocessing
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
$env:INTERNAL_TOKEN = 'defina-o-igual-ao-do-backend'
uvicorn app.main:app --reload --host 127.0.0.1 --port 8000
```

Para o backend, instale as dependências com `composer install` dentro de `backend/` e disponibilize PostgreSQL/PostGIS e Redis com as mesmas configurações de ambiente usadas em `compose.yaml`. O modo com Docker é o fluxo suportado para iniciar o conjunto completo, pois configura essas dependências e o worker automaticamente.

## Verificações

```powershell
# Frontend
Set-Location frontend
npm run typecheck
npm test
npm run build
```

```powershell
# Backend
Set-Location backend
composer test
```

```powershell
# Geoprocessamento
Set-Location geoprocessing
pip install -r requirements-dev.txt
pytest
```

## Estrutura

```text
backend/         API PHP/Hyperf, migrações, worker e testes
frontend/        Aplicação Next.js
geoprocessing/   Serviço FastAPI e pipeline de índices espectrais
compose.yaml     Orquestração local de todos os serviços
```

Saídas de relatórios, dados temporários, builds, caches, dependências instaladas e arquivos de ambiente são ignorados pelo Git. Os modelos seguros de configuração (`.env.example`) permanecem versionados.
