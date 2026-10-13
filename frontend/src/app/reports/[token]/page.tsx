import { notFound } from "next/navigation";

export default async function PublicReportPage({ params }: { params: Promise<{ token: string }> }) {
  const { token } = await params;
  if (!/^[a-f0-9]{64}$/.test(token)) notFound();

  return <iframe title="Relatório MineVision" src={`/api/v1/public/reports/${token}/html`} className="min-h-screen w-full border-0" />;
}
