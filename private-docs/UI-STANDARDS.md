# QEAI — Padrão de Interface e Código

Documento de referência para desenvolvedores e agentes de IA que atuam no site
institucional e no QEAI One. **Leia antes de alterar layout, componentes ou fluxos.**

---

## 1. Decisão de stack

- **Não usamos Bootstrap.** O projeto tem um design system próprio, leve e sem
  dependências de CDN. Adicionar Bootstrap criaria conflito de estilos, peso extra
  e inconsistência entre site e produto.
- Site institucional: PHP + HTML + CSS + JavaScript puro.
- QEAI One: Laravel + Blade + CSS + JavaScript puro.
- Não introduzir Node, Vite, Tailwind ou frameworks de UI sem decisão explícita.
- Ícones do QEAI One: componente Blade `<x-icon name="..." />`.
- Ícones do site: SVGs inline ou caracteres tipográficos já usados no projeto.

---

## 2. Identidade visual

### Cores

| Token | Uso |
|---|---|
| `--primary` / `#6D3DF5` | Ações, links, destaques |
| `--blue` / `#2F80FF` | Gradientes e apoio |
| `--cyan` / `#18C7D9` | Acento tecnológico |
| `--accent` / `#FF7048` | Ênfase pontual |
| `--navy` / `#0A1022` | Hero escuro, produto, rodapé |
| `--ink` / `#24253B` | Texto |
| `--muted` / `#66677A` | Texto secundário |
| `--soft` / `#F8F7FC` | Fundos alternativos |
| `--border` / `#E6E4EE` | Bordas |

### Tipografia

- Fonte: `Inter`, com fallback para `system-ui`.
- Títulos: peso 750/800, `letter-spacing` negativo (`-.03em` a `-.055em`).
- Corpo: 15–16px, linha 1.65–1.8.
- Rótulos: maiúsculas, 10–11px, `letter-spacing` `.1em`.
- Evitar mais de dois pesos por bloco.

### Espaçamento e forma

- Escala: 4, 8, 12, 16, 24, 32, 48, 64, 96.
- Cards: raio 16–32px; inputs/botões: 9–12px.
- Sombras suaves e difusas; nunca sombras duras.
- Container do site: `1180px`; do app: `1480px`.

---

## 3. Direção visual

- **Hero premium:** fundo navy com gradientes radiais, grid sutil, orbes desfocados
  e movimento lento (24s+). Texto branco com destaque em gradiente.
- **Seções claras:** fundo branco ou `--soft`, muito espaço em branco, títulos fortes.
- **Produto QEAI One:** bloco escuro em gradiente, com uma janela de produto clara
  e KPIs/barras. É o ponto de conversão para o web app.
- **Cards:** borda fina, hover com elevação discreta e ícone em quadrado colorido.
- **Rodapé:** limpo, com links institucionais e acesso ao QEAI One.

---

## 4. Componentes

### Botões

- Primário: fundo `--primary`, texto branco, seta quando ação de avanço.
- Secundário/ghost: fundo translúcido ou branco, borda fina.
- `QEAI One`: botão de contorno no header, com seta externa.
- Altura mínima de toque: 44px.
- Sempre verbo + objeto (“Acessar QEAI One”, “Vamos conversar”).

### Navegação

- Sticky, com fundo translúcido e blur.
- Ao rolar, adicionar `.is-scrolled` (sombra suave).
- No mobile, menu recolhido por botão; manter CTA do QEAI One visível.
- Links internos usam âncoras; links externos usam `rel="noopener noreferrer"`.

### Hero

- Conteúdo em duas colunas no desktop; uma no mobile.
- Elementos de fundo sempre `aria-hidden="true"` e `pointer-events:none`.
- Animação de entrada apenas uma vez; respeitar `prefers-reduced-motion`.

### Produto

- `.product-card` com copy à esquerda e mockup à direita.
- O mockup é decorativo (`aria-hidden`), não substitui informação textual.
- CTA principal leva ao QEAI One em nova aba.

### Formulários

- Labels claros, placeholders de exemplo, erros por campo.
- Consentimento LGPD explícito.
- Honeypot e CSRF no backend.
- Nunca exibir dados sensíveis na URL.

### Motion

- Duração: 200–400ms para interações; 700–1000ms para entrada de seções.
- Movimento sutil: `translateY(12–20px)`, opacidade, escala discreta.
- Sem parallax pesado, sem looping agressivo.
- `prefers-reduced-motion` desliga todas as animações.

---

## 5. Conteúdo público

- Linguagem profissional, direta e em pt-BR.
- Não publicar textos de teste, demonstração, “em breve”, “lorem ipsum” ou
  instruções internas.
- Não mencionar testes automatizados, ambientes de QA, seeds ou ferramentas de
  desenvolvimento nas páginas públicas.
- Não usar textos que sugiram protótipo ou “vibe coding”. O site deve parecer
  produto final.
- IA pode ser citada como serviço de marketing, mas não como forma de construção
  do produto.
- Toda promessa deve ser verdadeira e verificável.

---

## 6. Segurança e backend

Regras obrigatórias:

1. **Autorização no backend**, nunca apenas escondendo botões no front.
2. Toda consulta de dados de negócio deve ser filtrada por `organization_id`
   (ou pelo vínculo equivalente).
3. Nunca confiar em `user_id`, `organization_id`, `role` ou `permission` enviados
   pelo navegador.
4. CSRF em todos os formulários web; exceções apenas para API/webhooks com
   autenticação própria.
5. Escapar saída HTML; usar `{!! !!}` apenas com conteúdo sanitizado.
6. Validar uploads: extensão, MIME real, tamanho e nome.
7. Segredos somente no servidor (`.env`, `config/`), nunca no repositório.
8. Rate limit em login, cadastro, recuperação, convites, formulários e API.
9. Aplicar headers de segurança (CSP, nosniff, frame-ancestors, referrer).
10. Sessões: HttpOnly, SameSite=Lax, Secure em HTTPS.
11. Logs de auditoria para ações sensíveis.
12. Exportações CSV devem neutralizar fórmulas (`=`, `+`, `-`, `@`).

---

## 7. Organização de arquivos

### Site

```
index.php, contact.php, blog.php, ...
app/                 bootstrap, segurança, banco, SMTP
assets/css/          site.css + site-premium.css
assets/js/           site.js + site-premium.js
workspace/           painel privado
private-docs/        documentação interna (bloqueada na web)
storage/             configuração e cache (bloqueado na web)
tests/               verificações internas (bloqueado na web)
```

- Novos estilos: preferir `site-premium.css` ou um arquivo específico, sempre
  carregado depois de `site.css`.
- Novos scripts: `site-premium.js` ou arquivo nomeado pelo recurso.
- Nunca adicionar CSS/JS inline extenso em páginas.

### QEAI One

```
app/Http/Controllers     controllers por domínio
app/Models               modelos e relações
app/Support              regras de negócio reutilizáveis
resources/views          Blade (layouts, parciais, views)
public/css/app.css       design system do app
public/js/app.js         interações leves
docs/                    design system, checklist e segurança
tests/Feature            testes de comportamento e segurança
```

- Regras de negócio em `App\Support` ou controllers; nunca no Blade.
- Componentes visuais reutilizáveis em `resources/views/components`.
- Views longas devem ser divididas em parciais.

---

## 8. Checklist antes de publicar

- [ ] Nenhum texto de teste/demo/placeholder em páginas públicas.
- [ ] Nenhuma instrução interna, segredo ou chave no front-end.
- [ ] Rotas protegidas por autenticação e autorização no backend.
- [ ] Consultas escopadas por organização.
- [ ] CSRF ativo em formulários web.
- [ ] Headers de segurança presentes.
- [ ] Formulários com validação server-side e mensagens claras.
- [ ] Responsivo em 360, 768, 1024, 1366 e 1920.
- [ ] `prefers-reduced-motion` respeitado.
- [ ] Contraste e foco visível.
- [ ] Links externos com `noopener noreferrer`.
- [ ] `php -l` nos arquivos alterados.
- [ ] Testes de segurança e funcionalidades executados.
- [ ] Cache de CSS/JS versionado por `asset_version()`.

---

## 9. Instruções para agentes de IA

- Leia este documento e `ARCHITECTURE.md` antes de editar.
- Preserve o estilo existente; não redesenhe telas sem pedido explícito.
- Não introduza dependências, frameworks ou CDNs sem justificativa.
- Não altere regras de negócio no front-end.
- Não remova validações, autorização, auditoria ou headers de segurança.
- Não publique conteúdo de teste, mensagens de debug ou comentários internos.
- Rode lint/testes após alterações e descreva o que foi validado.
- Em caso de dúvida sobre escopo, prefira a solução mais simples e consistente.
