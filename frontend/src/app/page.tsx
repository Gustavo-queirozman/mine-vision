"use client";

import { FormEvent, useState } from "react";
import { useRouter } from "next/navigation";
import { ApiError, api } from "@/lib/api";

type LoginResponse = { access_token: string; refresh_token: string; user: { name: string } };

const capabilities = [
  { number: "01", icon: "⌖", title: "Território e ANM", text: "Delimite áreas e cruze a operação com processos minerários, restrições e camadas oficiais." },
  { number: "02", icon: "◫", title: "Óptico e radar", text: "Combine Sentinel-1 e Sentinel-2 para acompanhar o território inclusive em períodos de nuvens." },
  { number: "03", icon: "⌁", title: "Mudanças classificadas", text: "Priorize mudanças de superfície com classes operacionais e indicação explícita de revisão humana." },
  { number: "04", icon: "✦", title: "Inteligência explicável", text: "Compare oportunidades por evidências espectrais, cobertura, campo e contexto regulatório." },
  { number: "05", icon: "◉", title: "Licenças e ativos", text: "Centralize licenças, barragens e levantamentos de drone com status, prazo e geometria." },
  { number: "06", icon: "↗", title: "Campo à gestão", text: "Conecte amostras, sondagens, alertas, inspeções, auditoria e relatórios executivos." },
];
const steps = [
  { title: "Configure a operação", text: "Cadastre a empresa, as minas, os usuários e os limites das áreas monitoradas." },
  { title: "Acompanhe as evidências", text: "Consulte radar, imagens ópticas, séries temporais, camadas oficiais e eventos no mapa." },
  { title: "Investigue e comunique", text: "Priorize alertas, registre campo, drone e sondagem; gere relatórios auditáveis." },
];
const audiences = [
  { title: "Operação", text: "Acompanhe o território, concentre evidências e mantenha as prioridades da equipe visíveis." },
  { title: "Meio ambiente e compliance", text: "Acompanhe camadas oficiais, licenças, barragens, prazos e a trilha de auditoria." },
  { title: "Gestão", text: "Tenha contexto para conversar sobre riscos, andamento das ações e próximos passos." },
];
const safeguards = [
  { title: "Contexto antes da decisão", text: "Área, período, imagem e ocorrência ficam conectados para facilitar a leitura técnica." },
  { title: "Rastreabilidade operacional", text: "Alertas, inspeções, ativos, registros de campo e auditoria registram o que foi observado e decidido." },
  { title: "Acesso por equipe", text: "Usuários e permissões podem ser organizados conforme a estrutura da sua operação." },
];
const questions = [
  { question: "O MineVision substitui a vistoria em campo?", answer: "Não. A plataforma ajuda a priorizar e documentar a investigação. A confirmação de uma situação continua dependendo de análise técnica e, quando aplicável, de verificação em campo." },
  { question: "Quais dados podem ser acompanhados?", answer: "O ambiente reúne áreas, imagens Sentinel-1 e Sentinel-2, índices, mudanças, camadas ANM, licenças, barragens, drones, amostras, sondagens, alertas, inspeções, auditoria e relatórios." },
  { question: "Como o sistema classifica mudanças?", answer: "A classificação atual é uma regra automatizada de triagem e cada resultado informa que exige revisão humana. Ela não substitui laudo técnico, vistoria ou confirmação de atividade." },
  { question: "A plataforma estima reservas ou teor mineral?", answer: "Não. Imagens e índices espectrais apoiam a observação da superfície e a comparação temporal; eles não confirmam veios, teor ou reservas minerais." },
  { question: "É possível começar com uma demonstração?", answer: "Sim. O acesso abaixo abre o ambiente demonstrativo para que sua equipe conheça os fluxos principais antes de estruturar uma operação." },
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
      <nav className="site-nav" aria-label="Navegação principal"><a href="#plataforma">Plataforma</a><a href="#como-funciona">Como funciona</a><a href="#governanca">Governança</a><a href="#perguntas">Perguntas</a></nav>
      <a className="header-cta" href="#acesso">Acessar plataforma <span aria-hidden="true">↗</span></a>
    </header>
    <section id="inicio" className="hero-section">
      <div className="hero-copy"><p className="eyebrow"><span className="eyebrow-line"/> INTELIGÊNCIA GEOESPACIAL PARA MINERAÇÃO</p><h1>Enxergue a operação <em>por inteiro.</em></h1><p className="hero-lead">Do radar e satélite ao drone, à sondagem e à conformidade, reúna evidências para acompanhar territórios minerários e agir com mais contexto.</p><div className="hero-actions"><a className="button-primary" href="#plataforma">Conhecer a plataforma <span aria-hidden="true">↗</span></a><a className="button-text" href="#acesso">Explorar demonstração <span aria-hidden="true">→</span></a></div><p className="hero-note">Uma visão integrada para equipes técnicas, compliance e gestão.</p></div>
      <div className="hero-visual" aria-label="Prévia ilustrativa do painel MineVision"><div className="visual-top"><span className="visual-live"><span/> PAINEL OPERACIONAL</span><span>MINAS GERAIS · BR</span></div><div className="visual-map"><div className="map-grid"/><div className="map-region region-one"/><div className="map-region region-two"/><div className="map-track"/><span className="map-pin pin-one">01</span><span className="map-pin pin-two">02</span><span className="map-pin pin-three">03</span><div className="map-caption"><span>ÁREA MONITORADA</span><strong>Uma visão de cada território.</strong></div></div><div className="visual-bottom"><div><span>01 / MAPA</span><strong>Localize</strong></div><div><span>02 / ANÁLISE</span><strong>Compare</strong></div><div><span>03 / CAMPO</span><strong>Investigue</strong></div></div></div>
    </section>
    <section className="trust-strip" aria-label="Fluxo da plataforma"><span>UM FLUXO CONECTADO</span><strong>Território</strong><i aria-hidden="true">→</i><strong>Satélite</strong><i aria-hidden="true">→</i><strong>Campo</strong><i aria-hidden="true">→</i><strong>Conformidade</strong><i aria-hidden="true">→</i><strong>Decisão</strong></section>
    <section id="plataforma" className="site-section capability-section"><div className="section-heading"><div><p className="eyebrow">A PLATAFORMA</p><h2>Informação que acompanha <em>cada etapa.</em></h2></div><p>O MineVision conecta dados espaciais e trabalho de campo em um único ambiente. Assim, sua equipe encontra a evidência, registra a decisão e acompanha o desdobramento.</p></div><div className="capability-grid">{capabilities.map(item=><article className="capability-card" key={item.number}><span className="card-number">{item.number} /</span><div className="card-symbol" aria-hidden="true">{item.icon}</div><h3>{item.title}</h3><p>{item.text}</p></article>)}</div></section>
    <section id="como-funciona" className="workflow-section"><div className="workflow-inner"><div className="workflow-intro"><p className="eyebrow">COMO FUNCIONA</p><h2>Da observação à <em>ação.</em></h2><p>Uma rotina simples para transformar dados em acompanhamento operacional.</p><a href="#acesso" className="button-light">Ver a demonstração <span aria-hidden="true">↗</span></a></div><div className="workflow-list">{steps.map((step,index)=><article key={step.title}><span>0{index+1}</span><div><h3>{step.title}</h3><p>{step.text}</p></div><span aria-hidden="true">↗</span></article>)}</div></div></section>
    <section id="entrega" className="site-section deliver-section"><div className="deliver-panel"><div><p className="eyebrow">O QUE VOCÊ CONTRATA</p><h2>Uma camada operacional entre o território e a decisão.</h2><p>O sistema reúne mapa, imagens e radar, inteligência explicável, fontes oficiais, ativos críticos, campo, relatórios, auditoria e API.</p></div><ul><li>Sincronização de processos e contexto regulatório</li><li>Sentinel-1, Sentinel-2, séries temporais e mudanças</li><li>Licenças, barragens e levantamentos de drone</li><li>Amostras, sondagens, inspeções e evidências</li><li>Benchmark interno, relatórios e trilha de auditoria</li></ul></div><div className="scope-note"><span aria-hidden="true">i</span><p><strong>O papel dos dados.</strong> Scores e classificações automatizadas priorizam investigação e sempre exigem revisão humana. Eles não confirmam atividade, veios, teor ou reservas minerais; a plataforma organiza evidências para análise técnica e verificação em campo.</p></div></section>
    <section className="audience-section"><div className="audience-inner"><div className="audience-heading"><p className="eyebrow">PARA QUEM É</p><h2>Uma mesma leitura para quem precisa <em>agir junto.</em></h2></div><div className="audience-grid">{audiences.map((audience, index)=><article key={audience.title}><span>0{index + 1}</span><h3>{audience.title}</h3><p>{audience.text}</p></article>)}</div></div></section>
    <section id="governanca" className="site-section governance-section"><div className="section-heading"><div><p className="eyebrow">GOVERNANÇA DA INFORMAÇÃO</p><h2>Mais clareza para trabalhar com <em>evidências.</em></h2></div><p>Monitorar é mais do que acumular dados. A plataforma foi desenhada para relacionar observações, análises e ações em uma rotina que a equipe consegue consultar e explicar.</p></div><div className="safeguard-grid">{safeguards.map((safeguard, index)=><article key={safeguard.title}><span className="safeguard-index">0{index + 1}</span><h3>{safeguard.title}</h3><p>{safeguard.text}</p></article>)}</div></section>
    <section id="perguntas" className="faq-section"><div className="faq-inner"><div><p className="eyebrow">PERGUNTAS FREQUENTES</p><h2>O essencial, antes de começar.</h2><p>Transparência sobre o papel da plataforma faz parte de uma implantação bem conduzida.</p></div><div className="faq-list">{questions.map(item=><details key={item.question}><summary>{item.question}<span aria-hidden="true">+</span></summary><p>{item.answer}</p></details>)}</div></div></section>
    <section id="acesso" className="access-section"><div className="access-intro"><p className="eyebrow">CONHEÇA NA PRÁTICA</p><h2>Entre no ambiente de demonstração.</h2><p>Explore os fluxos e visualize como as informações aparecem para a equipe. Os dados disponíveis neste ambiente são demonstrativos.</p><div className="access-detail"><span className="access-detail-icon" aria-hidden="true">↗</span><span>Do mapa aos relatórios, veja a operação conectada em uma única interface.</span></div></div><div className="login-card"><div className="login-card-heading"><span>ACESSO À PLATAFORMA</span><span aria-hidden="true">↗</span></div><h3>Bem-vindo de volta.</h3><p>Use suas credenciais para continuar.</p><form onSubmit={handleLogin}><label>E-mail<input type="email" autoComplete="email" value={email} onChange={event=>setEmail(event.target.value)} required /></label><label>Senha<input type="password" autoComplete="current-password" value={password} onChange={event=>setPassword(event.target.value)} required /></label>{error&&<p role="alert" className="login-error">{error}</p>}<button type="submit" disabled={loading}>Entrar</button></form><p className="demo-hint">Acesso demonstrativo: admin@minevision.local</p></div></section>
    <footer className="site-footer"><a className="brand" href="#inicio"><span className="brand-mark" aria-hidden="true">M</span><span>Mine<span className="brand-accent">Vision</span></span></a><p>Inteligência geoespacial para uma operação mais informada.</p><div className="footer-links"><a href="#governanca">Governança</a><a href="#perguntas">Perguntas</a><a href="#inicio">Voltar ao início ↑</a></div></footer>
  </main>;
}
