"use client";

import { FormEvent, useState } from "react";
import { useRouter } from "next/navigation";
import { ApiError, api } from "@/lib/api";

type LoginResponse = { access_token: string; refresh_token: string; user: { name: string } };

const capabilities = [
  { number: "01", icon: "⌖", title: "Áreas sob controle", text: "Cadastre minas e delimite as áreas que precisam de acompanhamento em um mapa operacional." },
  { number: "02", icon: "◫", title: "Imagens e índices", text: "Organize imagens Sentinel e acompanhe indicadores espectrais como NDVI, NDWI e NBR ao longo do tempo." },
  { number: "03", icon: "⌁", title: "Mudanças em foco", text: "Compare períodos, registre detecções e transforme ocorrências em alertas para a equipe analisar." },
  { number: "04", icon: "↗", title: "Ação documentada", text: "Planeje inspeções, anexe fotos e reúna os resultados em relatórios para apoiar decisões." },
];
const steps = [
  { title: "Configure a operação", text: "Cadastre a empresa, as minas, os usuários e os limites das áreas monitoradas." },
  { title: "Acompanhe as evidências", text: "Consulte imagens, indicadores, comparações temporais e eventos no mapa." },
  { title: "Investigue e comunique", text: "Priorize alertas, registre inspeções de campo e gere relatórios." },
];

export default function Home() {
  const router = useRouter();
  const [email, setEmail] = useState("admin@minevision.local");
  const [password, setPassword] = useState("MineVision!2026");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  async function handleLogin(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setError(""); setLoading(true);
    try {
      const result = await api<LoginResponse>("/auth/login", { method: "POST", body: JSON.stringify({ email, password }) });
      sessionStorage.setItem("minevision.access_token", result.access_token);
      sessionStorage.setItem("minevision.refresh_token", result.refresh_token);
      router.push("/dashboard");
    } catch (caught) {
      setError(caught instanceof ApiError && caught.status === 401 ? "E-mail ou senha inválidos." : "Não foi possível entrar. Confirme se o backend está em execução.");
    } finally { setLoading(false); }
  }
  return <main className="site-shell">
    <header className="site-header">
      <a className="brand" href="#inicio" aria-label="MineVision, início"><span className="brand-mark" aria-hidden="true">M</span><span>Mine<span className="brand-accent">Vision</span></span></a>
      <nav className="site-nav" aria-label="Navegação principal"><a href="#plataforma">Plataforma</a><a href="#como-funciona">Como funciona</a><a href="#entrega">O que inclui</a></nav>
      <a className="header-cta" href="#acesso">Acessar plataforma <span aria-hidden="true">↗</span></a>
    </header>
    <section id="inicio" className="hero-section">
      <div className="hero-copy"><p className="eyebrow"><span className="eyebrow-line"/> INTELIGÊNCIA GEOESPACIAL PARA MINERAÇÃO</p><h1>Enxergue a operação <em>por inteiro.</em></h1><p className="hero-lead">Do satélite à inspeção de campo, reúna as informações essenciais para acompanhar áreas minerárias, identificar mudanças e agir com mais contexto.</p><div className="hero-actions"><a className="button-primary" href="#plataforma">Conhecer a plataforma <span aria-hidden="true">↗</span></a><a className="button-text" href="#acesso">Explorar demonstração <span aria-hidden="true">→</span></a></div><p className="hero-note">Uma visão integrada para equipes técnicas e gestores.</p></div>
      <div className="hero-visual" aria-label="Prévia ilustrativa do painel MineVision"><div className="visual-top"><span className="visual-live"><span/> PAINEL OPERACIONAL</span><span>MINAS GERAIS · BR</span></div><div className="visual-map"><div className="map-grid"/><div className="map-region region-one"/><div className="map-region region-two"/><div className="map-track"/><span className="map-pin pin-one">01</span><span className="map-pin pin-two">02</span><span className="map-pin pin-three">03</span><div className="map-caption"><span>ÁREA MONITORADA</span><strong>Uma visão de cada território.</strong></div></div><div className="visual-bottom"><div><span>01 / MAPA</span><strong>Localize</strong></div><div><span>02 / ANÁLISE</span><strong>Compare</strong></div><div><span>03 / CAMPO</span><strong>Investigue</strong></div></div></div>
    </section>
    <section className="trust-strip" aria-label="Fluxo da plataforma"><span>UM FLUXO CONECTADO</span><strong>Território</strong><i aria-hidden="true">→</i><strong>Imagem</strong><i aria-hidden="true">→</i><strong>Alerta</strong><i aria-hidden="true">→</i><strong>Inspeção</strong><i aria-hidden="true">→</i><strong>Relatório</strong></section>
    <section id="plataforma" className="site-section capability-section"><div className="section-heading"><div><p className="eyebrow">A PLATAFORMA</p><h2>Informação que acompanha <em>cada etapa.</em></h2></div><p>O MineVision conecta dados espaciais e trabalho de campo em um único ambiente. Assim, sua equipe encontra a evidência, registra a decisão e acompanha o desdobramento.</p></div><div className="capability-grid">{capabilities.map(item=><article className="capability-card" key={item.number}><span className="card-number">{item.number} /</span><div className="card-symbol" aria-hidden="true">{item.icon}</div><h3>{item.title}</h3><p>{item.text}</p></article>)}</div></section>
    <section id="como-funciona" className="workflow-section"><div className="workflow-inner"><div className="workflow-intro"><p className="eyebrow">COMO FUNCIONA</p><h2>Da observação à <em>ação.</em></h2><p>Uma rotina simples para transformar dados em acompanhamento operacional.</p><a href="#acesso" className="button-light">Ver a demonstração <span aria-hidden="true">↗</span></a></div><div className="workflow-list">{steps.map((step,index)=><article key={step.title}><span>0{index+1}</span><div><h3>{step.title}</h3><p>{step.text}</p></div><span aria-hidden="true">↗</span></article>)}</div></div></section>
    <section id="entrega" className="site-section deliver-section"><div className="deliver-panel"><div><p className="eyebrow">O QUE VOCÊ CONTRATA</p><h2>Um ambiente para monitorar, investigar e comunicar.</h2><p>O sistema reúne painel de indicadores, mapa de áreas, gestão de imagens e análises, alertas, inspeções, relatórios, usuários e acesso à API.</p></div><ul><li>Cadastro e delimitação de minas e áreas monitoradas</li><li>Consulta a imagens Sentinel e índices espectrais</li><li>Detecção de mudanças e gestão de alertas</li><li>Inspeções com evidências fotográficas</li><li>Relatórios operacionais e executivos</li></ul></div><div className="scope-note"><span aria-hidden="true">i</span><p><strong>O papel dos dados.</strong> Imagens de satélite e índices espectrais ajudam a observar mudanças na superfície. Eles não confirmam veios, teor ou reservas minerais. A plataforma organiza evidências para análise técnica e verificação em campo.</p></div></section>
    <section id="acesso" className="access-section"><div className="access-intro"><p className="eyebrow">CONHEÇA NA PRÁTICA</p><h2>Entre no ambiente de demonstração.</h2><p>Explore os fluxos e visualize como as informações aparecem para a equipe. Os dados disponíveis neste ambiente são demonstrativos.</p><div className="access-detail"><span className="access-detail-icon" aria-hidden="true">↗</span><span>Do mapa aos relatórios, veja a operação conectada em uma única interface.</span></div></div><div className="login-card"><div className="login-card-heading"><span>ACESSO À PLATAFORMA</span><span aria-hidden="true">↗</span></div><h3>Bem-vindo de volta.</h3><p>Use suas credenciais para continuar.</p><form onSubmit={handleLogin}><label>E-mail<input type="email" autoComplete="email" value={email} onChange={event=>setEmail(event.target.value)} required /></label><label>Senha<input type="password" autoComplete="current-password" value={password} onChange={event=>setPassword(event.target.value)} required /></label>{error&&<p role="alert" className="login-error">{error}</p>}<button type="submit" disabled={loading}>Entrar</button></form><p className="demo-hint">Acesso demonstrativo: admin@minevision.local</p></div></section>
    <footer className="site-footer"><a className="brand" href="#inicio"><span className="brand-mark" aria-hidden="true">M</span><span>Mine<span className="brand-accent">Vision</span></span></a><p>Inteligência geoespacial para uma operação mais informada.</p><a href="#inicio">Voltar ao início ↑</a></footer>
  </main>;
}
