# QEAI One — Plano da plataforma (análise e planejamento)

> Documento de planejamento. **Nenhum código foi alterado.** Este arquivo é o registro das
> decisões, do escopo e — principalmente — das **limitações da hospedagem compartilhada** que
> viram requisitos para uma futura migração para VPS.

Data: 28/09/2026

---

## 1. Decisões já tomadas

| Tema | Decisão |
|---|---|
| Nome da plataforma | **QEAI One** |
| Domínio | `one.qeai.com.br` (subdomínio independente do site institucional) |
| Instalação | **Do zero** (instalação limpa; produção atual é apenas uma página e pode ser substituída) |
| Infraestrutura | **Hostinger compartilhada** (sem VPS). Rodar no que o plano PHP permitir |
| Limitações de infra | Devem ser registradas como observação no projeto, com requisitos para migração futura a VPS |
| Público | **Somente B2B** (empresas/CNPJ) no lançamento |
| Mercado | **Brasil no MVP**, mas arquitetura preparada para internacionalização |
| 1º canal de atendimento | **E-mail + chat no site** |
| Canais futuros | WhatsApp e formulários/anúncios via API — **credenciais são do cliente** (a plataforma oferece a integração, mas a key, o número e as contas pertencem à organização) |
| Cobrança | **Asaas** (Pix + cartão + boleto, recorrência) — integração **adiada**; no início só o plano **free** |
| Planos | **Dinâmicos**, geridos pelo ADM Root (criar/editar/remover, recursos e limites); seed inicial: free |
| Instalação | **Guiada, estilo WordPress**: assistente no primeiro acesso cria o ADM Root e configura banco e SMTP |
| E-mail da plataforma | SMTP Hostinger configurável pelo ADM Root (reset de senha, verificação). Limite do servidor anotado |
| E-mail das organizações | SMTP próprio de cada organização (canal de conversa com leads); outros provedores depois |
| Governança de logs | Logs da **organização** separados dos logs da **plataforma** (ADM Root: abuso/segurança). O Root só acessa dados de organização como membro autorizado |
| White label | Empresa, razão social, CNPJ, marca e cores configuráveis; padrão oficial QEAI: CNPJ 60.357.082/0001-58 |
| GitHub | Conta **Emerson-Foco**; repositório novo para o QEAI One |
| Site institucional | Permanece separado, em `qeai.com.br`, sem risco pela plataforma |

---

## 2. O que é o QEAI One

Não é "só um CRM". É uma **plataforma de operação de marketing e atendimento**, composta por
módulos independentes que se encaixam. O CRM/leads é o núcleo, mas o valor está no conjunto:

- Captação de leads (formulários, API pública, integrações de anúncios)
- CRM (contatos, empresas, pipelines, tarefas)
- Atendimento omnichannel (e-mail, chat, WhatsApp, SMS, voz) com inbox único
- Automações e chatbot (fluxos sem IA e, depois, com IA e base de conhecimento)
- Publicação em redes sociais e gestão de anúncios
- Relatórios

Posicionamento (analogia Agilize): a Agilize é "contabilidade digital"; o **QEAI One é a
operação de marketing e atendimento da empresa**, operável pelo próprio cliente — e operável
pela QEAI quando o cliente não quiser fazer (ver "modo agência", seção 6).

### 2.1 Princípio de modularidade

Cada módulo é uma peça encaixável: liga/desliga por plano e por organização (feature flags),
tem suas próprias tabelas, rotas, permissões e telas, e **não chama outro módulo diretamente**
— comunica por eventos e interfaces. Canais de atendimento são *drivers* com interface única
(`send`, `receive`, `verifyWebhook`), então WhatsApp, e-mail, SMS, voz e web chat são plugáveis.

---

## 3. Público e internacionalização

- **B2B apenas** no lançamento (CNPJ). Evita CDC e reduz risco com dados de menores.
- Dois papéis jurídicos da QEAI:
  1. **Controladora** dos dados da conta/cobrança do cliente.
  2. **Operadora** dos dados de leads/clientes que o cliente insere (processa sob instrução do
     cliente, que é o controlador).
  Isso precisa constar no contrato (Termo de Tratamento de Dados / DPA) e nas políticas.
- **Preparado para fora do Brasil, sem abrir agora:**
  - i18n (catálogo de mensagens), locale, fuso horário, moeda e formatos de data.
  - Documento de identificação **plugável** (CPF/CNPJ agora; VAT/outros depois).
  - **Provedor de pagamento plugável** (Asaas/Mercado Pago no Brasil; Stripe no exterior).
  - Cadastro com **allowlist de país** (Brasil no MVP).
  - Expansão futura exige: GDPR (se mirar UE), termos por jurisdição, transferência
    internacional de dados, impostos e residência de dados.

---

## 4. Arquitetura

### 4.1 Domínios

| Domínio | Papel |
|---|---|
| `qeai.com.br` | Site institucional (mantém-se como está) |
| `one.qeai.com.br` | **Plataforma QEAI One** |
| `homolog.qeai.com.br` | Homologação/staging (recomendado) |
| `api.qeai.com.br` ou `hooks.qeai.com.br` | Webhooks e API pública de captação (opcional; pode começar em `one.qeai.com.br/api`) |

**Subdomínio na Hostinger:** hPanel → Websites → Dashboard do domínio → Domains → Subdomains.
Criar `one` como **website independente** para ter Git/deploy/SSL próprios. Apontar o document
root para a pasta `public/` do app; se não for possível, usar `.htaccess` de roteamento.
Recursos (PHP workers, memória, banco) são compartilhados com o site institucional.

### 4.2 Estrutura interna

```
core/        tenancy, auth, RBAC, auditoria, planos, feature flags, arquivos, notificações
modules/
  leads/     contatos, empresas, pipelines, etapas, negócios, tarefas, campos customizados
  inbox/     conversas, filas, canais (drivers)
  intake/    formulários, API pública, webhooks (ex.: Meta Lead Ads)
  flows/     automações (gatilho → condição → ação)
  bot/       chatbot (fluxo sem IA) + IA (base de conhecimento / RAG)
  social/    agendamento e publicação
  ads/       gestão e relatórios de anúncios
  reports/   dashboards e exportações
  agency/    QEAI atuando dentro da conta do cliente
```

### 4.3 Stack recomendada

- **Laravel + MySQL/MariaDB** para a plataforma. Justificativa: entrega pronta e auditável de
  autenticação, autorização, proteção de rotas, CSRF, hash de senha, ORM com consultas
  parametrizadas, validação, filas, agendador, storage e e-mails — exatamente os pontos que
  seriam arriscados de reimplementar à mão em PHP puro.
- Frontend: Blade + Tailwind + Alpine.js (e Livewire onde facilitar). Assets compilados
  **localmente** e versionados (o servidor compartilhado não deve rodar build de Node).
- Fila: driver `database` + cron `schedule:run` a cada minuto.
- Cache e sessão: driver `database` (não há Redis).
- Arquivos: disco local no MVP; depois Cloudflare R2 / S3 (Flysystem) sem mudar o domínio da aplicação.
- E-mail transacional: **provedor externo** (Resend, Amazon SES, SendGrid ou Postmark) — o SMTP
  da hospedagem (10/min e 100/dia) não atende a plataforma.

> **Alternativa considerada e descartada no MVP:** PHP puro com kernel próprio. Dá controle
> total e deploy ZIP, mas exigiria reimplementar auth, RBAC, filas, migrations, cobrança e
> multi-tenancy — mais esforço e mais risco de segurança. Reavaliar somente se houver
> impedimento técnico para usar Composer/Laravel no plano contratado.

### 4.4 Publicação (Git, SSH e staging)

- **SSH disponível no plano atual**: o deploy do QEAI One pode rodar `git pull`,
  `composer install`, `php artisan migrate` e `npm run build` direto no servidor — sem instalador
  web improvisado.
- Repositório **separado** para o QEAI One (não misturar com o site institucional).
- `homolog.qeai.com.br` com banco separado antes de produção.
- Cron do agendador: `php /home/uXXXXXX/qeai-one/artisan schedule:run` a cada minuto; a fila usa
  o driver `database` e é processada pelo agendador.

### 4.5 Estrutura de diretórios e separação dos repositórios Git

A plataforma fica **fora de `public_html`**; só os arquivos públicos aparecem no docroot do
subdomínio, via **link simbólico**. Assim `.git` e `.env` nunca são acessíveis pela web.

```
/home/uXXXXXX/
├── qeai-one/                 ← app Laravel + repositório Git da PLATAFORMA (fora da web)
│   ├── .git/  .env  app/  bootstrap/  config/  database/  storage/  vendor/
│   └── public/               ← único diretório exposto
└── public_html/
    ├── ... (site institucional) + repositório Git do SITE
    └── one -> /home/uXXXXXX/qeai-one/public   ← link simbólico (docroot do subdomínio)
```

Regras para os dois Git **não brigarem**:
- Repositório do **site**: raiz `public_html/`; criar `public_html/.gitignore` com `/one` (o link),
  para o site nunca versionar nem sobrescrever os arquivos da plataforma.
- Repositório da **plataforma**: raiz `/home/uXXXXXX/qeai-one/`; ignora seus próprios artefatos
  (`.env`, `storage/`, `vendor/`, `node_modules/`, builds).
- Os dois repositórios são **irmãos**, nunca aninhados. Não rodar `git` de um diretório que
  contenha os dois.
- O deploy de cada app mexe **apenas** na sua própria raiz. O único ponto de contato é o link
  `public_html/one`, que pertence à plataforma e fica ignorado pelo Git do site.
- Trabalhar no site (ex.: futuro blog) não afeta a plataforma, e vice-versa.

> **Recomendação:** configurar o docroot do subdomínio para um link simbólico exige acesso SSH
> (`ln -s`). Se preferir não usar link, a alternativa é copiar `public/` via script de deploy,
> mantendo `public_html/one` sempre no `.gitignore` do site.

---

## 5. Modelo de dados (esboço)

- **Núcleo/contas:** `organizations`, `users`, `memberships` (user↔org↔role), `invitations`,
  `plans`, `subscriptions`, `feature_flags`, `audit_logs`, `consent_records`,
  `data_subject_requests`, `api_keys`, `webhook_endpoints`, `webhook_deliveries`, `files`
- **CRM:** `contacts`, `companies`, `pipelines`, `stages`, `deals`, `tasks`, `tags`,
  `custom_fields`, `notes`
- **Atendimento:** `channels`, `channel_accounts`, `conversations`, `messages`, `queues`
- **Captação:** `forms`, `form_submissions`
- **Automação/Bot:** `workflows`, `workflow_runs`, `bots`, `bot_knowledge`, `bot_sessions`,
  `bot_handoffs`
- **Social/Ads:** `social_accounts`, `posts`, `post_targets`, `ad_accounts`, `ad_metrics`

**Multi-tenancy:** banco único com `organization_id` em toda tabela de negócio, acesso sempre
escopado por middleware + global scope. Testes automatizados de **isolamento entre tenants** são
obrigatórios (vazamento entre organizações é o pior cenário jurídico).

---

## 6. Módulos e fases

| Fase | Entrega | Marco |
|---|---|---|
| **0. Fundação** | Subdomínio, repositório, banco, migrations, tenancy, auth, RBAC, convites, auditoria, feature flags, planos, storage, e-mail transacional, documentos legais (Termos, Privacidade, Cookies, DPA, subprocessadores) | Base segura para receber usuários |
| **1. MVP operacional** | Leads/CRM + Inbox (e-mail + chat no site) + formulários embutidos + **API pública de captação** + notificações + gating por plano + onboarding + cobrança | **Aceitar usuários reais e receber leads** |
| **2. Chatbot + WhatsApp** | Construtor de fluxo (sem IA) → base de conhecimento + IA opcional → WhatsApp Cloud API (credenciais do cliente) → transbordo humano | Atendimento automatizado |
| **3. Social + Ads** | Publicação nas redes (APIs oficiais) + relatórios/gestão de anúncios + webhooks de anúncios | Fecha o ciclo de marketing |
| **4. Canais extras + agência** | SMS, voz, modo agência multi-cliente, analytics avançado, app mobile | Escala |
| **5. Endurecimento** | Pentest, RIPD/DPIA, plano de incidentes, otimização, migração de escala (VPS) | Pronto para crescer |

**MVP = Fase 1.** Os módulos de chatbot/WhatsApp/social/anúncios vêm depois justamente porque
são independentes e dependem de aprovações/credenciais externas (Meta, provedores), que são da
organização cliente.

---

## 7. Planos e cobrança

Planos **dinâmicos**: o ADM Root cria, edita e remove planos, definindo recursos e limites. Um plano
**free** é criado no seed. A cobrança via **Asaas** entra depois; até lá, só o free fica ativo.
Gating por **limites + módulos**, não só por preço.

| | Free | Plus | Pro |
|---|---|---|---|
| Usuários | 1–2 | até 5 | até 15+ |
| Contatos | ~500 | ~5.000 | ilimitado* |
| Canais | E-mail + chat | + WhatsApp | + SMS/voz |
| Chatbot/IA | — | fluxo sem IA | IA + base de conhecimento |
| Social/Ads | — | relatórios | publicação + gestão |
| Modo agência | — | — | sim |

*Cobrança: **Asaas** ou **Mercado Pago** (Pix + cartão + boleto, recorrência). No início,
ativação manual é aceitável até validar preço e demanda.

---

## 8. Segurança e auditoria (o que sustenta a defesa em processo)

- **Trilha de auditoria imutável** (quem, o quê, quando, IP, antes/depois), *append-only* com
  encadeamento de hash e exportação periódica.
- **RBAC** com menor privilégio + **2FA (TOTP)** para administradores; listagem/encerramento de sessões.
- **Criptografia**: TLS ponta a ponta; segredos em repouso (AES-GCM, como já se faz hoje);
  hash de senha **Argon2id**.
- **Isolamento por tenant** testado e *fail closed*.
- **Governança de acesso**: o ADM Root da plataforma **não acessa dados de negócio** de uma
  organização sem ser membro com permissão; acesso de suporte exige concessão explícita e é auditado.
- **Registro de consentimento de marketing** por lead: fonte, data/hora, IP, user-agent, texto e
  versão do formulário — prova de opt-in.
- **Direitos do titular**: exportação, anonimização e exclusão com SLA e trilha.
- **Backups** testados + política de retenção.
- **Plano de resposta a incidentes** — ANPD exige comunicação em **3 dias úteis**; registro de
  incidentes guardado por **5 anos** (Res. CD/ANPD 15/2024).
- **Aceite versionado** dos Termos; **ROPA**; **Encarregado (DPO)** com contato público.
- Headers de segurança (CSP, HSTS), rate limit e anti-abuso no free (verificação de e-mail/domínio,
  Cloudflare Turnstile).

---

## 9. LGPD e documentos

Além de Termos de Uso e Política de Privacidade/Cookies (reaproveitáveis do site):
- **DPA / Termo de Tratamento de Dados** (controlador = cliente; operador = QEAI).
- **Lista pública de subprocessadores** (hospedagem, e-mail, SMS, WhatsApp/Meta, LLM, pagamento).
- **Política de retenção** por tipo de dado.
- **IA/LLM** (fase 2): transferência internacional, base legal, contrato com fornecedor e opção de
  desligar treinamento.
- **Marketing**: opt-in, opt-out e descadastro em todo canal (maior fonte de litígio por spam).

---

## 10. Requisitos de hospedagem: compartilhada (agora) × VPS (futuro)

> Esta seção é o registro pedido: o que o sistema exige hoje no compartilhado e o que fica
> pendente para quando houver VPS. Os números variam conforme o plano da Hostinger.

| Capacidade | Compartilhada (agora) | Contorno no MVP | VPS (melhoria futura) |
|---|---|---|---|
| WebSocket / tempo real | Indisponível nativo | Polling (5–15 s) ou SSE; Pusher/Ably externo se necessário | Servidor WebSocket nativo (Reverb) |
| Worker de fila contínuo | Não há | Fila em banco + cron 1/min | Redis + Horizon + Supervisor |
| Redis / Memcached | Normalmente não | Cache/sessão/fila em banco | Redis |
| Cron abaixo de 1 min | Não | Agrupar tarefas por minuto | Cron a cada poucos segundos |
| Workers PHP concorrentes | Limitado (conforme plano) | Otimizar; jobs curtos e em lote | Dezenas/centenas |
| E-mail do servidor | ~10/min e 100/dia | Provedor transacional externo | SMTP dedicado |
| Tempo de execução PHP | ~360–480 s | Processar em lotes | Sem limite prático |
| Memória PHP | 1–2 GB | Evitar cargas grandes | Configurável/maior |
| Storage de objetos | Disco local | Local; depois R2/S3 | Objetos + backups |
| SSH / Composer | **Disponível no plano atual** | Deploy pelo servidor: `git pull` + `composer` + `artisan` | SSH + Composer + CI/CD |
| Conexões de banco | 25–75 por usuário | Reúso de conexão | Tuning e pool |
| Build de assets (Node) | Não indicado no servidor | Build local e versionado | Build no CI |

**Consequências que viram requisito de VPS quando escalar:** inbox em tempo real, fila com
throughput alto, envio massivo de e-mail, backups automatizados, CI/CD completo e isolamento de
recursos por cliente.

---

## 11. Riscos e itens em aberto

1. **Escala no compartilhado** — funciona no MVP; webhooks + filas + concorrência pedem VPS.
2. **E-mail transacional** — trocar desde o começo (limite de 100/dia inviabiliza a plataforma).
3. **WhatsApp/Meta** — aprovação de app e custo por mensagem; por isso os canais são por organização.
4. **Vazamento entre tenants** — exige testes dedicados de isolamento.
5. **Abuso do free** — verificação e limites rígidos.
6. **Deploy manual** — staging real e Git para não quebrar produção.
7. **Dois repositórios Git** — exigem que o `.gitignore` do site ignore `/one`; regra registrada
   na seção 4.5.

### Decisões e dependências

- ~~Subdomínio~~ **decidido:** mantido em `public_html/one`, com o app fora da web (seção 4.5).
- ~~SSH/Composer~~ **confirmado: disponível**.
- ~~Git~~ **confirmado:** o responsável já trabalha com Git/Hostinger em outros projetos.
- **E-mail da plataforma:** SMTP Hostinger configurável pelo ADM Root (decidido).
- **E-mail das organizações:** SMTP próprio por organização (decidido).
- **Cobrança:** Asaas (decidido), integração adiada; free no início.
- **GitHub:** conta `Emerson-Foco`, repositório novo (decidido).
- **CNPJ/razão social:** configuráveis (white label); oficial QEAI preenchido por padrão.
- Detalhamento completo da fundação em `private-docs/FASE-0-QEAI-ONE.md`.

---

## 12. Próximos passos (checklist da Fase 0)

1. Subdomínio `one.qeai.com.br` criado + SSL (feito); criar o link simbólico do docroot
   `public_html/one` para `qeai-one/public` e ajustar o PHP para 8.3.
2. Criar repositório GitHub do QEAI One, instalar Composer local, esqueleto Laravel + MySQL e
   criar `public_html/.gitignore` com `/one` (repositório do site).
3. Definir banco, migrations e o kernel: tenancy, auth, RBAC, convites, auditoria, feature flags.
4. Criar `homolog.qeai.com.br` com banco separado e deploy via Git.
5. Configurar e-mail transacional e provider de pagamento (abstração + Asaas/Mercado Pago).
6. Redigir/minutar Termos, Privacidade, Cookies, DPA e lista de subprocessadores.
7. Só então iniciar a Fase 1 (Leads/CRM + Inbox e-mail/chat + captação).
