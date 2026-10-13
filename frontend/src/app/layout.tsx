import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "MineVision | Monitoramento geoespacial para mineração",
  description: "Acompanhe áreas minerárias com mapa, imagens de satélite, detecção de mudanças, alertas, inspeções e relatórios em uma única plataforma.",
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return <html lang="pt-BR"><body>{children}</body></html>;
}
