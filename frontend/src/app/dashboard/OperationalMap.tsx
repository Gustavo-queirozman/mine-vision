"use client";

import { useEffect, useRef, useState } from "react";

type Position = [number, number];
type Geometry = { type: "Point" | "Polygon" | "MultiPolygon"; coordinates: unknown };
export type MapFeature = { id: number; geometry: Geometry; properties: Record<string, unknown> & { resource?: string } };
export type MapLayer = { resource: string; name: string; description: string; count: number; color: string };

declare global { interface Window { L?: any } }

let leafletLoader: Promise<any> | undefined;
function leaflet() {
  if (typeof window === "undefined") return Promise.reject(new Error("Mapa indisponível no servidor."));
  if (window.L) return Promise.resolve(window.L);
  if (!leafletLoader) leafletLoader = new Promise((resolve, reject) => {
    if (!document.querySelector("link[data-minevision-leaflet]")) {
      const css = document.createElement("link");
      css.rel = "stylesheet"; css.dataset.minevisionLeaflet = "true";
      css.href = "https://unpkg.com/leaflet@1.9.4/dist/leaflet.css";
      document.head.appendChild(css);
    }
    const script = document.createElement("script");
    script.src = "https://unpkg.com/leaflet@1.9.4/dist/leaflet.js";
    script.onload = () => resolve(window.L); script.onerror = () => reject(new Error("Não foi possível carregar o mapa."));
    document.head.appendChild(script);
  });
  return leafletLoader;
}

const colors: Record<string, string> = { mines: "#1e88e5", monitoring_areas: "#d99a32", compliance_layers: "#e05263", change_detections: "#8b5cf6", alerts: "#e05263", inspections: "#16856d" };
const layerNames: Record<string, string> = { mines: "Minas", monitoring_areas: "Áreas", compliance_layers: "Contexto regulatório", change_detections: "Detecções", alerts: "Alertas", inspections: "Inspeções" };
const layerDescriptions: Record<string, string> = {
  mines: "Polígonos das minas cadastradas.",
  monitoring_areas: "Áreas acompanhadas dentro das minas.",
  compliance_layers: "Camadas oficiais e restrições regulatórias importadas.",
  change_detections: "Áreas onde a análise identificou mudança.",
  alerts: "Ocorrências que exigem acompanhamento da equipe.",
  inspections: "Inspeções de campo e seus locais associados.",
};
const layerOrder = ["mines", "monitoring_areas", "compliance_layers", "change_detections", "alerts", "inspections"];
const baseLayers = {
  satellite: { url: "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}", attribution: "Tiles © Esri" },
  terrain: { url: "https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png", attribution: "© OpenStreetMap contributors © CARTO" },
};
const defaultCenter: Position = [-44.0, -18.5];

export default function OperationalMap({ mode = "view", features = [], point, polygon = [], fullScreen = false, onPointChange, onPolygonChange, onFeatureSelect, onLayerSelect }: { mode?: "view" | "point" | "polygon"; features?: MapFeature[]; point?: Position; polygon?: Position[]; fullScreen?: boolean; onPointChange?: (point: Position) => void; onPolygonChange?: (points: Position[]) => void; onFeatureSelect?: (feature: MapFeature) => void; onLayerSelect?: (layer: MapLayer) => void }) {
  const element = useRef<HTMLDivElement>(null), map = useRef<any>(undefined), data = useRef<any>(undefined), draft = useRef<any>(undefined), tiles = useRef<any>(undefined), fittedData = useRef<string | undefined>(undefined);
  const callbacks = useRef({ onPointChange, onPolygonChange, onFeatureSelect, mode, polygon });
  const [message, setMessage] = useState("Carregando mapa…");
  const [ready, setReady] = useState(false);
  const [baseLayer, setBaseLayer] = useState<keyof typeof baseLayers>("satellite");
  callbacks.current = { onPointChange, onPolygonChange, onFeatureSelect, mode, polygon };

  useEffect(() => {
    let cancelled = false;
    void leaflet().then((L) => {
      if (cancelled || !element.current || map.current) return;
      const instance = L.map(element.current, { zoomControl: false, attributionControl: false, preferCanvas: true }).setView([defaultCenter[1], defaultCenter[0]], 5);
      tiles.current = L.tileLayer(baseLayers.satellite.url, { maxZoom: 19, attribution: baseLayers.satellite.attribution }).addTo(instance);
      instance.on("click", (event: any) => {
        const next: Position = [event.latlng.lng, event.latlng.lat];
        if (callbacks.current.mode === "point") callbacks.current.onPointChange?.(next);
        if (callbacks.current.mode === "polygon") callbacks.current.onPolygonChange?.([...callbacks.current.polygon, next]);
      });
      map.current = instance; setMessage(""); setReady(true);
      setTimeout(() => instance.invalidateSize(), 0);
    }).catch((error: Error) => setMessage(error.message));
    return () => { cancelled = true; if (map.current) { map.current.remove(); map.current = undefined; } setReady(false); };
  }, []);

  useEffect(() => {
    const instance = map.current, L = window.L;
    if (!instance || !L) return;
    tiles.current?.remove();
    const config = baseLayers[baseLayer];
    tiles.current = L.tileLayer(config.url, { maxZoom: 19, attribution: config.attribution }).addTo(instance);
  }, [baseLayer, ready]);

  useEffect(() => { if (fullScreen) setTimeout(() => map.current?.invalidateSize(), 0); }, [fullScreen]);

  useEffect(() => {
    const instance = map.current, L = window.L;
    if (!instance || !L) return;
    data.current?.remove(); const group = L.featureGroup();
    const identicalGeometries = new Map<string, number>();
    features.forEach((feature) => {
      const resource = String(feature.properties.resource), color = colors[resource] ?? "#64748b";
      const name = String(feature.properties.name ?? feature.properties.title ?? `${layerNames[resource] ?? "Elemento"} #${feature.id}`);
      const geometryKey = JSON.stringify(feature.geometry);
      const duplicateIndex = identicalGeometries.get(geometryKey) ?? 0;
      identicalGeometries.set(geometryKey, duplicateIndex + 1);
      const layer = L.geoJSON({ type: "Feature", geometry: feature.geometry, properties: feature.properties }, {
        // Eventos vinculados à mesma área têm a mesma geometria. A variação de
        // traço torna cada ocorrência identificável sem alterar sua coordenada.
        pointToLayer: (_: unknown, latlng: any) => {
          const angle = duplicateIndex * 2.4;
          const distance = duplicateIndex ? 10 + Math.floor((duplicateIndex - 1) / 6) * 8 : 0;
          const displaced = duplicateIndex ? instance.layerPointToLatLng(instance.latLngToLayerPoint(latlng).add(L.point(Math.cos(angle) * distance, Math.sin(angle) * distance))) : latlng;
          return L.circleMarker(displaced, { radius: 8, color: "#fff", weight: 2.5, fillColor: color, fillOpacity: 1, className: "minevision-map-point" });
        },
        style: { color, weight: 3 + duplicateIndex * .8, opacity: .95, fillColor: color, fillOpacity: Math.max(.08, .22 - duplicateIndex * .02), dashArray: duplicateIndex ? `${5 + duplicateIndex} ${4 + duplicateIndex}` : undefined, className: "minevision-map-shape" },
      });
      layer.bindTooltip(name, { direction: "top", offset: [0, -8], className: "minevision-map-tooltip" });
      layer.on("click", () => callbacks.current.onFeatureSelect?.(feature)); group.addLayer(layer);
    });
    group.addTo(instance); data.current = group;
    // Preserve o enquadramento durante seleções; somente dados novos devem ajustar o zoom.
    const dataKey = JSON.stringify(features.map(feature => [feature.id, feature.properties.resource, feature.geometry]));
    if (mode === "view" && features.length && fittedData.current !== dataKey) { const bounds = group.getBounds(); if (bounds.isValid()) { instance.fitBounds(bounds.pad(.15)); fittedData.current = dataKey; } }
  }, [features, mode, ready]);

  useEffect(() => {
    const instance = map.current, L = window.L;
    if (!instance || !L || mode === "view") return;
    draft.current?.remove(); const group = L.featureGroup();
    if (mode === "point" && point) { L.marker([point[1], point[0]], { draggable: true }).on("dragend", (event: any) => { const value = event.target.getLatLng(); callbacks.current.onPointChange?.([value.lng, value.lat]); }).addTo(group); instance.setView([point[1], point[0]], Math.max(instance.getZoom(), 12)); }
    if (mode === "polygon" && polygon.length) { const latlngs = polygon.map(([lng, lat]) => [lat, lng]); L.polyline(latlngs, { color: "#d99a32", weight: 3 }).addTo(group); latlngs.forEach((latlng, index) => L.circleMarker(latlng, { radius: 6, color: "#fff", weight: 2, fillColor: "#d99a32", fillOpacity: 1 }).on("click", () => callbacks.current.onPolygonChange?.(polygon.filter((_, item) => item !== index))).addTo(group)); if (polygon.length >= 3) L.polygon(latlngs, { color: "#d99a32", fillColor: "#d99a32", fillOpacity: .2 }).addTo(group); instance.fitBounds(group.getBounds().pad(.2)); }
    group.addTo(instance); draft.current = group;
  }, [mode, point?.[0], point?.[1], polygon, ready]);

  // As categorias continuam visíveis mesmo sem geometrias, para deixar claro
  // quais camadas fazem parte da leitura territorial.
  const layers = layerOrder.map(resource => ({ resource, name: layerNames[resource], description: layerDescriptions[resource] ?? "Elementos geográficos desta camada.", count: features.filter(feature => String(feature.properties.resource) === resource).length, color: colors[resource] ?? "#64748b" }));
  return <div className={`operational-map relative overflow-hidden rounded-xl border border-slate-700 ${fullScreen ? "operational-map-fullscreen" : ""}`}>
    <div ref={element} className={mode === "view" ? "h-[440px] w-full bg-slate-800" : "h-72 w-full bg-slate-800"} />
    {mode === "view" && <><div className="map-title"><span className="map-title-dot" />Monitoramento territorial</div><div className="map-toolbar" aria-label="Estilo do mapa"><button type="button" onClick={() => setBaseLayer("satellite")} className={baseLayer === "satellite" ? "active" : ""}>Satélite</button><button type="button" onClick={() => setBaseLayer("terrain")} className={baseLayer === "terrain" ? "active" : ""}>Claro</button></div><div className="map-zoom" aria-label="Controles de zoom"><button type="button" aria-label="Aproximar mapa" onClick={() => map.current?.zoomIn()}>+</button><button type="button" aria-label="Afastar mapa" onClick={() => map.current?.zoomOut()}>−</button></div>{layers.length > 0 && <div className="map-legend" aria-label="Camadas do mapa">{layers.map(layer => <button type="button" key={layer.resource} onClick={() => onLayerSelect?.(layer)} title={layer.description}><i style={{ backgroundColor: layer.color }} />{layer.name} <b>{layer.count}</b></button>)}</div>}<div className="map-north" aria-hidden="true"><b>▲</b><span>N</span></div></>}
    {message && <div className="absolute inset-0 grid place-items-center bg-slate-900/90 text-sm text-slate-300">{message}</div>}
    {mode !== "view" && <p className="map-draw-hint">{mode === "point" ? "Clique no mapa ou arraste o marcador para definir a localização." : "Clique para adicionar vértices; clique em um vértice para removê-lo."}</p>}
  </div>;
}
