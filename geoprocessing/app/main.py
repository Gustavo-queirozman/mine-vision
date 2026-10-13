import os
import secrets
from typing import Annotated

from fastapi import Depends, FastAPI, Header, HTTPException

from .models import AnalysisRequest, AnalysisResult
from .pipeline import analyze

app = FastAPI(title="MineVision Geoprocessing", version="0.1.0")


def require_internal_token(
    token: Annotated[str | None, Header(alias="X-Internal-Token")] = None,
) -> None:
    expected = os.getenv("INTERNAL_TOKEN", "")
    if not expected:
        raise HTTPException(status_code=503, detail="INTERNAL_TOKEN não configurado.")
    if not token or not secrets.compare_digest(token.encode(), expected.encode()):
        raise HTTPException(status_code=401, detail="Token interno inválido.")


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok", "service": "minevision-geoprocessing", "pipeline": "copernicus_enabled"}


@app.post("/analyze", response_model=AnalysisResult, dependencies=[Depends(require_internal_token)])
def run_analysis(request: AnalysisRequest) -> AnalysisResult:
    try:
        return analyze(request)
    except (ValueError, RuntimeError) as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc
