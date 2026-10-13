"""Fluxo de índices e detecção de mudanças com imagens Copernicus."""
from __future__ import annotations
import io, os
from pathlib import Path
from datetime import datetime, timezone
from typing import Any
import boto3, numpy as np, requests, tifffile
from botocore.client import Config
from PIL import Image
from .indices import ndvi, ndwi, nbr
from .models import AnalysisRequest, AnalysisResult

TOKEN_URL="https://identity.dataspace.copernicus.eu/auth/realms/CDSE/protocol/openid-connect/token"
PROCESS_URL="https://sh.dataspace.copernicus.eu/process/v1"

def analyze(request: AnalysisRequest) -> AnalysisResult:
    ref, cur=request.reference_image, request.current_image
    satellite=str(ref.get("satellite",""))
    if satellite not in ("SENTINEL-1","SENTINEL-2") or satellite!=str(cur.get("satellite","")):
        raise ValueError("Selecione duas imagens do mesmo Sentinel para uma análise comparável.")
    if request.analysis_type=="GOLD_POTENTIAL" and satellite!="SENTINEL-2":
        raise ValueError("A prospecção de potencial aurífero requer imagens Sentinel-2 ópticas.")
    box=_bbox(request.geometry.coordinates); before=_raster(ref,box,satellite); after=_raster(cur,box,satellite)
    if before.shape!=after.shape: raise RuntimeError("Rasters incompatíveis.")
    if request.analysis_type=="GOLD_POTENTIAL":
        # Proxies de óxidos de ferro e alteração hidrotermal; não detectam ouro diretamente.
        iron=np.divide(after[...,2],after[...,0],out=np.zeros_like(after[...,2]),where=after[...,0]>0)
        hydro=np.abs(after[...,4]-after[...,3])/(after[...,4]+after[...,3]+1e-6)
        valid=after[...,5]>0; score=np.clip((iron-1.15)/1.5,0,1)*.6+np.clip(hydro/.35,0,1)*.4
        change=score; changed=valid&np.isfinite(change)&(change>=request.threshold); values=score; kind="CUSTOM"; base=np.clip(after[..., [2,1,0]]*2.5,0,1)
    elif satellite=="SENTINEL-2":
        b={"NDVI":ndvi(before[...,3],before[...,2]),"NDWI":ndwi(before[...,1],before[...,3]),"NBR":nbr(before[...,3],before[...,4])}
        a={"NDVI":ndvi(after[...,3],after[...,2]),"NDWI":ndwi(after[...,1],after[...,3]),"NBR":nbr(after[...,3],after[...,4])}
        valid=(before[...,5]>0)&(after[...,5]>0); change=np.sqrt((a["NDVI"]-b["NDVI"])**2+(a["NBR"]-b["NBR"])**2); values=a["NDVI"]; kind="NDVI"; base=np.clip(after[..., [2,1,0]]*2.5,0,1)
    else:
        valid=(before[...,2]>0)&(after[...,2]>0); values=10*np.log10(np.maximum(after[...,0],1e-6))
        change=np.abs(values-10*np.log10(np.maximum(before[...,0],1e-6)))/25; kind="CUSTOM"; base=np.repeat(np.clip((values+25)/25,0,1)[...,None],3,2)
    changed=valid&np.isfinite(change)&(change>=request.threshold); total=_area(request.geometry.coordinates); affected=float(total*changed.mean())
    preview=(base*255).astype(np.uint8); preview[changed]=(0.5*preview[changed]+0.5*np.array([230,50,47])).astype(np.uint8)
    prefix=f"companies/{request.company_id}/analysis/{request.job_id}"; preview_path=_store(prefix+"/change.png",_png(preview)); index_path=_store(prefix+"/"+kind.lower()+".png",_png(_index(values,valid)))
    data=values[valid&np.isfinite(values)]; has=bool(changed.any() and affected>=.01)
    meta={"provider":"Copernicus Data Space Ecosystem","satellite":satellite,"analysis_type":request.analysis_type,"threshold":request.threshold,"valid_pixel_percentage":round(float(valid.mean()*100),2),"changed_pixel_percentage":round(float(changed.mean()*100),2),"requires_human_review":True}
    if request.analysis_type=="GOLD_POTENTIAL": meta["interpretation"]="Indicador de alteração hidrotermal e óxidos de ferro; não confirma a presença de ouro."
    return AnalysisResult(affected_area_hectares=round(affected,4) if has else 0,confidence=round(max(40,min(95,float(valid.mean()*100))),2),severity=_severity(affected,total),geometry=_geometry(changed,box) if has else None,metadata=meta,images=[{"id":int(cur["id"]),"storage_path":preview_path,"thumbnail_path":preview_path}],indices=[{"satellite_image_id":int(cur["id"]),"type":kind,"storage_path":index_path,"min_value":float(np.nanmin(data)) if data.size else None,"max_value":float(np.nanmax(data)) if data.size else None,"mean_value":float(np.nanmean(data)) if data.size else None,"metadata_json":meta}])

def _raster(image:dict[str,Any], box:list[float], satellite:str)->np.ndarray:
    meta=image.get("metadata_json") or {}; date=datetime.fromisoformat(str(meta.get("datetime") or image["acquisition_date"]).replace("Z","+00:00")).astimezone(timezone.utc).date().isoformat()
    bands=["B02","B03","B04","B08","B11","dataMask"] if satellite=="SENTINEL-2" else ["VV","VH","dataMask"]
    script='//VERSION=3\nfunction setup(){return {input:'+str(bands).replace("'", '"')+',output:{id:"default",bands:'+str(len(bands))+',sampleType:"FLOAT32"}};}\nfunction evaluatePixel(s){return ['+','.join("s."+x for x in bands)+'];}'
    payload={"input":{"bounds":{"bbox":box,"properties":{"crs":"http://www.opengis.net/def/crs/OGC/1.3/CRS84"}},"data":[{"type":"sentinel-2-l2a" if satellite=="SENTINEL-2" else "sentinel-1-grd","dataFilter":{"timeRange":{"from":date+"T00:00:00Z","to":date+"T23:59:59Z"}}}]},"output":{"width":512,"height":512,"responses":[{"identifier":"default","format":{"type":"image/tiff"}}]},"evalscript":script}
    response=requests.post(PROCESS_URL,headers={"Authorization":"Bearer "+_token(),"Accept":"image/tiff"},json=payload,timeout=120)
    if response.status_code!=200: raise RuntimeError(f"O Copernicus recusou as bandas (HTTP {response.status_code}): {response.text[:300]}")
    try: result=np.asarray(tifffile.imread(io.BytesIO(response.content)),dtype=float)
    except Exception as exc: raise RuntimeError(f"Raster Copernicus inválido ({response.headers.get('content-type')}): {response.content[:160]!r}") from exc
    if result.ndim!=3 or result.shape[-1]!=len(bands): raise RuntimeError("Raster Copernicus incompleto.")
    return result
def _token()->str:
    r=requests.post(TOKEN_URL,data={"grant_type":"client_credentials","client_id":os.getenv("COPERNICUS_CLIENT_ID",""),"client_secret":os.getenv("COPERNICUS_CLIENT_SECRET","")},timeout=30)
    if r.status_code!=200: raise RuntimeError("Não foi possível autenticar no Copernicus.")
    return r.json()["access_token"]
def _png(a:np.ndarray)->bytes:
    out=io.BytesIO();Image.fromarray(a,"RGB").save(out,"PNG");return out.getvalue()
def _index(v:np.ndarray,valid:np.ndarray)->np.ndarray:
    n=np.nan_to_num((v+1)/2,nan=0);out=np.zeros((*v.shape,3),dtype=np.uint8);out[...,1]=(np.clip(n,0,1)*255).astype(np.uint8);out[...,0]=(np.clip(1-n,0,1)*160).astype(np.uint8);out[~valid]=0;return out
def _store(key:str,body:bytes)->str:
    if os.getenv("STORAGE_DRIVER", "local") == "local":
        root=Path(os.getenv("LOCAL_OBJECT_ROOT", "/backend-runtime/objects"))
        path=(root/key).resolve()
        if root.resolve() not in path.parents: raise RuntimeError("Caminho de armazenamento inválido.")
        path.parent.mkdir(parents=True,exist_ok=True); path.write_bytes(body)
        return "local://"+key
    bucket=os.getenv("S3_BUCKET","minevision");c=boto3.client("s3",endpoint_url=os.getenv("S3_ENDPOINT"),aws_access_key_id=os.getenv("S3_ACCESS_KEY"),aws_secret_access_key=os.getenv("S3_SECRET_KEY"),config=Config(s3={"addressing_style":"path"}),region_name="us-east-1")
    try:c.head_bucket(Bucket=bucket)
    except Exception:c.create_bucket(Bucket=bucket)
    c.put_object(Bucket=bucket,Key=key,Body=body,ContentType="image/png");return "s3://"+bucket+"/"+key
def _bbox(c:list[Any])->list[float]:
    p=[]
    def walk(x:Any):
      if isinstance(x,list) and len(x)>=2 and isinstance(x[0],(float,int)) and isinstance(x[1],(float,int)):p.append((float(x[0]),float(x[1])))
      elif isinstance(x,list):
       for y in x:walk(y)
    walk(c);xs,ys=zip(*p);return [min(xs),min(ys),max(xs),max(ys)]
def _area(c:list[Any])->float:
    """Área aproximada em hectares para um retângulo WGS84 de pequena extensão."""
    w,s,e,n=_bbox(c); latitude=np.deg2rad((s+n)/2)
    width=(e-w)*111_320*np.cos(latitude); height=(n-s)*110_574
    return abs(width*height)/10_000
def _geometry(mask:np.ndarray,b:list[float])->dict[str,Any]:
    y,x=np.where(mask);w,s,e,n=b;west=w+x.min()/mask.shape[1]*(e-w);east=w+(x.max()+1)/mask.shape[1]*(e-w);north=n-y.min()/mask.shape[0]*(n-s);south=n-(y.max()+1)/mask.shape[0]*(n-s);return {"type":"Polygon","coordinates":[[[west,south],[east,south],[east,north],[west,north],[west,south]]]}
def _severity(a:float,t:float)->str:
    r=a/t if t else 0
    return "CRITICAL" if r>=.25 else "HIGH" if r>=.1 else "MEDIUM" if r>=.03 else "LOW"
