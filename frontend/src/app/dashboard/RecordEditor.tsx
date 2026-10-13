"use client";

import { FormEvent, useState } from "react";
import OperationalMap from "./OperationalMap";
import { choiceLabels, errorMessage, forms, Geometry, label, Position, Row } from "./model";

function EntitySelect({ name, value, set, rows }: { name: "mine_id" | "monitoring_area_id"; value: unknown; set: (name: string, value: unknown) => void; rows: Row[] }) {
  return <select required value={String(value ?? "")} onChange={event => set(name, event.target.value)} className="field mt-1 w-full"><option value="">Selecione</option>{rows.map(row => <option key={row.id} value={row.id}>{String(row.name ?? "Sem nome")} · ID {row.id}</option>)}</select>;
}

type Props = { row?: Row; resource: string; save: (body: Record<string, unknown>) => Promise<void>; close: () => void; mines: Row[]; areas: Row[] };

export default function RecordEditor({ row, resource, save, close, mines, areas }: Props) {
  const fields = forms[resource] ?? [];
  const [data, setData] = useState<Record<string, unknown>>(() => Object.fromEntries(fields.map(field => [field.name, row?.[field.name] ?? (field.kind === "boolean" ? true : "")])))
  const [error, setError] = useState("");
  const [busy, setBusy] = useState(false);
  const set = (name: string, value: unknown) => setData(current => ({ ...current, [name]: value }));
  const point = typeof data.longitude === "number" && typeof data.latitude === "number" ? [data.longitude, data.latitude] as Position : typeof data.longitude === "string" && typeof data.latitude === "string" && data.longitude !== "" && data.latitude !== "" ? [Number(data.longitude), Number(data.latitude)] as Position : undefined;
  const geometry = data.geometry as Geometry | undefined;
  const polygon = geometry?.type === "Polygon" ? (geometry.coordinates as Position[][])[0] ?? [] : [];

  async function submit(event: FormEvent) {
    event.preventDefault();
    try {
      const body: Record<string, unknown> = { ...data };
      delete body.location;
      for (const field of fields) {
        if (field.kind === "number" && body[field.name] !== "") body[field.name] = Number(body[field.name]);
        if (field.kind === "datetime" && body[field.name]) body[field.name] = new Date(String(body[field.name])).toISOString();
        if (field.kind === "map-area") {
          const points = (body.geometry as Geometry | undefined)?.coordinates as Position[][] | undefined;
          if (!points?.[0] || points[0].length < 3) throw Error("Marque ao menos três vértices no mapa para definir a área.");
          const ring = [...points[0]];
          if (ring[0][0] !== ring.at(-1)?.[0] || ring[0][1] !== ring.at(-1)?.[1]) ring.push([...ring[0]]);
          body.geometry = { type: "Polygon", coordinates: [ring] };
        }
        if (body[field.name] === "" && field.kind !== "boolean") delete body[field.name];
      }
      setBusy(true); await save(body); close();
    } catch (caught) { setError(errorMessage(caught)); } finally { setBusy(false); }
  }

  return <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
    {fields.length ? fields.map(field => field.kind === "map-point" ? <div key={field.name} className="sm:col-span-2"><p className="mb-1 text-sm text-slate-300">Localização no mapa</p><OperationalMap mode="point" point={point} onPointChange={([longitude, latitude]) => { set("longitude", longitude); set("latitude", latitude); }} /></div> : field.kind === "map-area" ? <div key={field.name} className="sm:col-span-2"><div className="mb-1 flex items-center justify-between text-sm text-slate-300"><span>{resource === "mines" ? "Delimite a área da mina no mapa" : "Delimitação da jazida no mapa"}</span><button type="button" onClick={() => set("geometry", undefined)} className="text-amber-300">Limpar área</button></div><OperationalMap mode="polygon" polygon={polygon} onPolygonChange={points => set("geometry", { type: "Polygon", coordinates: [points] })} />{resource === "mines" && <p className="mt-2 text-xs text-slate-400">O ponto de referência da mina será calculado a partir deste polígono.</p>}</div> : <label key={field.name} className={`text-sm text-slate-300 ${field.kind === "textarea" || field.kind === "vertices" ? "sm:col-span-2" : ""}`}>{label(field.name)}{field.name === "mine_id" ? <EntitySelect name="mine_id" value={data[field.name]} set={set} rows={mines} /> : field.name === "monitoring_area_id" ? <EntitySelect name="monitoring_area_id" value={data[field.name]} set={set} rows={areas} /> : field.kind === "boolean" ? <input type="checkbox" checked={Boolean(data[field.name])} onChange={event => set(field.name, event.target.checked)} className="ml-3 accent-amber-400" /> : field.kind === "textarea" || field.kind === "vertices" ? <textarea required={["name", "geometry", "notes"].includes(field.name) && !row} value={String(data[field.name] ?? "")} placeholder={field.kind === "vertices" ? "longitude,latitude; longitude,latitude; longitude,latitude" : ""} onChange={event => set(field.name, event.target.value)} className="field mt-1 h-24 w-full" /> : field.kind === "select" ? <select required value={String(data[field.name] ?? "")} onChange={event => set(field.name, event.target.value)} className="field mt-1 w-full"><option value="">Selecione</option>{field.choices?.map(choice => <option key={choice} value={choice}>{choiceLabels[choice] ?? choice}</option>)}</select> : <input required={["name", "email", "role", "status", "latitude", "longitude", "inspector_user_id"].includes(field.name) && !row} type={field.kind === "date" ? "date" : field.kind === "datetime" ? "datetime-local" : field.kind === "number" ? "number" : field.name === "password" ? "password" : "text"} value={String(data[field.name] ?? "")} onChange={event => set(field.name, event.target.value)} className="field mt-1 w-full" />}</label>) : <p className="sm:col-span-2 text-slate-400">Este recurso é criado pelos fluxos operacionais e não possui cadastro manual.</p>}
    {error && <p className="sm:col-span-2 text-red-200">{error}</p>}
    <button disabled={busy || !fields.length} className="w-fit rounded-xl bg-amber-400 px-4 py-2 font-semibold text-slate-950 disabled:opacity-60">{busy ? "Salvando…" : "Salvar"}</button>
  </form>;
}
