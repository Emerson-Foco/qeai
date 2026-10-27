# Fase 0 — Q One: fundação

> Documento de detalhamento da **Fase 0** (base da plataforma). Ainda **sem código**.
> É o mapa que orienta a implementação: instalação, contas, organizações, papéis, auditoria,
> planos dinâmicos, configurações, deploy e segurança.

Data: 28/09/2026
Repositório previsto: `https://github.com/Emerson-Foco` (novo repositório, ex.: `qeai-one`)

---

## 0. Objetivo da Fase 0

Entregar a **fundação multi-tenant segura** do Q One, sem CRM ainda (isso é a Fase 1). Ao final
da Fase 0 deve ser possível:

- Instalar o sistema do zero, de forma guiada, criando o **ADM Root**.
- Criar organizações, convidar usuários e atribuir papéis.
- Ter planos dinâmicos (com apenas o **free** ativo).
- Ter auditoria em dois escopos (organização e plataforma).
- Configurar a identidade da plataforma (white label) e o SMTP transacional.
- Fazer deploy por SSH no compartilhado, com os dois repositórios Git convivendo sem conflito.

---

## 1. Instalação guiada (estilo WordPress)

### 1.1 Princípio
No primeiro acesso, se o sistema não estiver instalado, o visitante é levado a `/install`.
O assistente só funciona **enquanto não instalado** e exige a **chave de instalação**.

### 1.2 Etapas do assistente

| # | Etapa | O que faz |
|---|---|---|
| 1 | **Requisitos** | Verifica PHP ≥ 8.2, extensões (`pdo_mysql`, `openssl`, `mbstring`, `curl`, `fileinfo`, `json`, `gd`; `intl` opcional) e escrita em `storage/`, `bootstrap/cache/` |
| 2 | **Chave de instalação** | Valida contra `config/bootstrap.key` (gerada no deploy). Sem a chave, não avança |
| 3 | **Banco de dados** | Host, porta, nome, usuário, senha → testa a conexão → grava o `.env` |
| 4 | **Estrutura + seed** | Roda as migrations e o seed (papéis, permissões e plano free) |
| 5 | **ADM Root** | Nome, e-mail e senha (mín. 10; hash **Argon2id**) do primeiro usuário da plataforma |
| 6 | **Identidade (white label)** | Nome, razão social, CNPJ, e-mail de contato, logo e cores |
| 7 | **SMTP da plataforma** | Host, porta, criptografia, usuário, senha e remetente (senha cifrada). **Pode ser pulado** |
| 8 | **Conclusão** | Cria `storage/installed.lock`, bloqueia o instalador, gera caches e redireciona ao login |

### 1.3 Segurança do instalador
- Protegido por **chave de instalação** (arquivo, fora da web).
- **Rate limit** e sem cache.
- Depois de instalado, `/install` responde **404** e não permite reinstalação.
- Nunca imprime credenciais em tela; erros vão para o log.
- O `.env` fica fora do docroot, com permissão restrita.

> Como há SSH, o instalador é um **facilitador** (experiência WordPress). O mesmo resultado pode ser
> obtido por `composer install`, `artisan migrate` e `artisan db:seed`, e ele deve reaproveitar esses
> comandos internamente.

---

## 2. Configuração em dois níveis

| Nível | Quem configura | O que configura |
|---|---|---|
| **Plataforma** | ADM Root | Marca/white label, SMTP transacional, provedor de pagamento (Asaas), planos, organizações, logs da plataforma |
| **Organização** | ADM da organização | Perfil, membros e papéis, canais (SMTP de conversa com leads), leads, automações, chaves de API, logs da organização |

Implementação: tabela única `settings` com `scope` (`platform`/`organization`), `scope_id`,
`key`, `value` e flag `encrypted`. Segredos (SMTP, credenciais) cifrados com a chave da aplicação.

**E-mails (dois usos distintos):**
1. **Plataforma → usuário** (reset de senha, verificação): SMTP único configurado pelo ADM Root.
2. **Organização → lead** (conversas): SMTP **próprio de cada organização**, cadastrado como canal.
   Provedores alternativos entram depois.

> **Limitação a registrar:** o SMTP da Hostinger tem limite de envio (~10/min e 100/dia). Suficiente
> para reset/verificação no início; para volume maior, trocar por provedor transacional. A abstração
> de e-mail deve permitir trocar o driver sem mudar o resto (requisito de migração futura).

---

## 3. Papéis e permissões (RBAC)

### 3.1 Escopos
- **Plataforma:** `root_admin` (ADM Root). Não é membro implícito de nenhuma organização.
- **Organização:** papéis seed `owner`, `admin`, `manager`, `agent` (editáveis depois).

### 3.2 Matriz de permissões (resumo)

| Permissão | Root | Owner | Admin | Manager | Agent |
|---|:--:|:--:|:--:|:--:|:--:|
| `platform.settings.manage` | ✔ | | | | |
| `platform.plans.manage` | ✔ | | | | |
| `platform.organizations.view` | ✔ | | | | |
| `platform.logs.view` | ✔ | | | | |
| `platform.billing.manage` | ✔ | | | | |
| `platform.support.access` (requer concessão da org) | ✔ | | | | |
| `org.settings.manage` | | ✔ | ✔ | | |
| `org.members.manage` | | ✔ | ✔ | | |
| `org.billing.manage` | | ✔ | ✔ | | |
| `org.logs.view` | | ✔ | ✔ | ✔ | |
| `org.channels.manage` | | ✔ | ✔ | | |
| `org.leads.view` / `org.leads.manage` | | ✔ | ✔ | ✔ | ver |
| `org.conversations.view` / `assign` / `manage` | | ✔ | ✔ | ✔ | ✔ |
| `org.automations.manage` | | ✔ | ✔ | ✔ | |
| `org.reports.view` | | ✔ | ✔ | ✔ | ✔ |
| `org.api_keys.manage` | | ✔ | ✔ | | |

### 3.3 Governança (privacidade entre plataforma e organização)
- O **ADM Root não tem membership implícito** em nenhuma organização.
- Para acessar dados de negócio de uma organização, o usuário da plataforma precisa ser **membro
  com papel** — ou receber uma **concessão temporária de suporte** feita pelo owner/admin da org,
  com escopo e prazo, **sempre auditada**.
- O painel do ADM Root enxerga **metadados e logs de segurança/abuso**, não o conteúdo de
  conversas/leads.
- Toda impersonação/acesso de suporte é registrado em `audit_logs` com ator, motivo e duração.

---

## 4. Logs e auditoria (dois escopos)

- `audit_logs` **append-only** com encadeamento de hash (`hash_prev`, `hash_self`) para
  impedir alteração retroativa.
- Campos: `scope` (`platform`/`organization`), `organization_id` (nulo quando plataforma),
  `actor_user_id`, `action`, `entity_type`, `entity_id`, `before` (json), `after` (json),
  `ip`, `user_agent`, `created_at`.
- **Visibilidade:** organização vê os logs do seu escopo (`org.logs.view`); o ADM Root vê os logs
  de plataforma.
- **Eventos de segurança** (vão para o escopo plataforma): login falho, rate limit atingido,
  webhook inválido, tentativa de acesso cross-tenant, erro de integração.
- Retenção configurável e **exportação** (prova em processo).

---

## 5. Planos dinâmicos e cobrança

- Tabelas: `plans`, `plan_features`, `subscriptions`, `feature_overrides`.
- O ADM Root cria/edita/remove planos e define, por plano, os **recursos** e **limites**
  (ex.: usuários, contatos, canais, IA, storage).
- **Seed:** apenas o plano **free**.
- Resolução de feature/limite de uma organização: **override da organização > plano > padrão**.
- Cobrança: **Asaas**, com uma **abstração de provedor de pagamento** (`payment_provider`) criada
  agora com um *stub*; a integração real entra quando você configurar as credenciais.
- Nenhuma tela de cobrança obrigatória na primeira etapa; assinaturas ficam no free até a integração.

---

## 6. Esquema SQL inicial

> Laravel 12 (ou estável vigente) + MySQL/MariaDB. Banco único, isolamento por `organization_id`.
> Convenções: `id` (bigint), `created_at`, `updated_at`; soft delete onde fizer sentido.

### Núcleo
- **users** — `name`, `email` (único), `password_hash`, `email_verified_at`, `two_factor_secret`,
  `status`, `locale`, `timezone`, `is_root_admin`, `last_login_at`, timestamps.
- **organizations** — `name`, `slug` (único), `document_type`, `document`, `country`, `status`,
  `plan_id`, `trial_ends_at`, `owner_user_id`, `settings` (json), timestamps.
- **memberships** — `organization_id`, `user_id`, `role_id`, `status`, `invited_by`, `joined_at`;
  único (`organization_id`, `user_id`).
- **roles** — `organization_id` (nulo = papel de plataforma), `name`, `slug`, `permissions` (json),
  `is_system`, timestamps.
- **invitations** — `organization_id`, `email`, `role_id`, `token_hash`, `expires_at`,
  `invited_by`, `accepted_at`, timestamps.
- **settings** — `scope`, `scope_id` (nulo = plataforma), `key`, `value`, `encrypted` (bool);
  único (`scope`, `scope_id`, `key`).
- **audit_logs** — conforme seção 4 (append-only).
- **plans** — `name`, `slug`, `description`, `price_cents`, `currency`, `interval`,
  `is_active`, `is_public`, `sort`, timestamps.
- **plan_features** — `plan_id`, `feature_key`, `enabled` (bool), `limit_value` (int, nulo = ilimitado).
- **feature_overrides** — `organization_id`, `feature_key`, `enabled`, `limit_value`, `expires_at`.
- **subscriptions** — `organization_id`, `plan_id`, `status`, `started_at`, `ends_at`,
  `provider`, `provider_ref`, `metadata` (json).

### Infraestrutura Laravel
- `migrations`, `jobs`, `failed_jobs`, `job_batches`, `sessions`, `cache`, `cache_locks`,
  `password_reset_tokens`.

### Fases seguintes (criadas depois, já previstas)
- Fase 1: `contacts`, `companies`, `pipelines`, `stages`, `deals`, `tasks`, `tags`, `notes`,
  `channels`, `channel_accounts`, `conversations`, `messages`, `queues`, `forms`,
  `form_submissions`, `api_keys`, `webhook_endpoints`, `webhook_deliveries`,
  `consent_records`, `data_subject_requests`.

---

## 7. Contratos dos módulos (peças encaixáveis)

Para que os módulos não fiquem acoplados:

- **Interface de canal** (`ChannelDriver`): `capabilities()`, `send(Message)`,
  `verifyWebhook(Request)`, `parseInbound(Request)`, `healthCheck()`.
  Implementações: `EmailSmtpDriver`, `WebChatDriver` (Fase 1), `WhatsAppCloudDriver` (Fase 2),
  `SmsDriver`, `VoiceDriver` (Fase 4).
- **Eventos** (comunicação entre módulos, sem chamada direta):
  `UserInvited`, `MemberJoined`, `LeadCreated`, `FormSubmitted`, `MessageReceived`,
  `ConversationAssigned`, `ConversationClosed`, `PlanChanged`, `ChannelConnected`.
- **Registro de módulo:** cada módulo declara rotas, permissões, itens de menu, telas de
  configuração e migrations em um único ponto, permitindo ligar/desligar por plano.

---

## 8. Telas da opera��o principal

**Instalação e acesso**
- Assistente de instalação (8 etapas).
- Login, esqueci minha senha, redefinição, verificação de e-mail, 2FA (TOTP).

**Plataforma (ADM Root)**
- Dashboard da plataforma.
- Organizações: listar, detalhar, suspender/reativar.
- Planos: criar/editar/remover, com recursos e limites.
- Configurações: identidade (white label), SMTP, pagamento (Asaas), segurança.
- Logs da plataforma (segurança/abuso), com filtros e exportação.

**Organização**
- Onboarding (dados da empresa, primeiro canal, convite de equipe).
- Membros: listar, convidar, alterar papel, remover.
- Configurações da organização (perfil, marca).
- Canais: conectar SMTP de conversa (e, depois, WhatsApp etc.).
- Logs da organização.

**Perfil do usuário**
- Dados pessoais, senha, 2FA, sessões ativas.

**Fase 1 (CRM/inbox, já desenhadas)**
- Leads (lista + kanban), Inbox (e-mail + chat), Formulários, Chaves de API de captação.

---

## 9. Fluxos principais

1. **Instalação:** primeiro acesso → assistente → banco + ADM Root + identidade + SMTP → login.
2. **Login/segurança:** e-mail + senha → 2FA (se ativo) → sessão; reset e verificação por e-mail.
3. **Criação de organização:** ADM Root ou auto-cadastro B2B → cria org + membership `owner` →
   plano free → onboarding.
4. **Convite de membro:** owner/admin convida por e-mail → link assinado com validade → aceita →
   membership com papel.
5. **Configuração de canal de e-mail:** org informa SMTP → testa envio → sincroniza (IMAP ou
   provedor) → passa a receber/enviar conversas (Fase 1).
6. **Concessão de suporte (governança):** owner concede acesso temporário a um usuário da
   plataforma → acesso registrado → expira automaticamente.

> Sobre **recebimento** de e-mail de conversa: como o SMTP só envia, a leitura de respostas exige
> **IMAP por cron** (latência de ~1 min) ou provedor com webhook de entrada. Fica na Fase 1, com a
> limitação registrada.

---

## 10. Segurança e LGPD — checklist da Fase 0

- Hash de senha **Argon2id**; senha mínima e verificação de e-mail.
- **2FA (TOTP)** disponível desde já; obrigatório para o ADM Root.
- Sessões em banco; cookie `HttpOnly`, `SameSite=Lax`, `Secure` em HTTPS.
- CSRF, rate limit, validação de entrada e escape de saída.
- Segredos cifrados em repouso; `.env` fora da web.
- Headers: CSP, HSTS, `X-Content-Type-Options`, `Referrer-Policy`.
- **Testes de isolamento entre tenants** na suíte automatizada.
- **Auditoria append-only** com hash encadeado + exportação.
- **Não-reinstalação** e bloqueio do instalador após concluir.
- Backup e restauração testados; retenção definida.
- Documentos: Termos de Uso, Privacidade, Cookies, **DPA**, lista de subprocessadores e
  **Encarregado (DPO)** com contato público.
- White label: empresa, razão social e CNPJ editáveis (padrão QEAI: `60.357.082/0001-58`).

---

## 11. Deploy e operação (Hostinger compartilhada, com SSH)

**Repositórios (irmãos, nunca aninhados):**

| | Site | Plataforma |
|---|---|---|
| Local | `C:\xampp\htdocs\qeai` | `C:\xampp\htdocs\qeai-one` |
| Servidor | `/home/uXXXXXX/public_html/` | `/home/uXXXXXX/qeai-one/` (fora da web) |
| `.gitignore` | inclui `/one` (o link) | ignora `.env`, `storage/`, `vendor/`, `node_modules/`, builds |

**Primeiro deploy da plataforma:**
1. `ssh` no servidor → `git clone` do repositório em `/home/uXXXXXX/qeai-one`.
2. `composer install --no-dev --optimize-autoloader`.
3. Criar `.env` (a partir do assistente de instalação ou manualmente) e `php artisan key:generate`.
4. `php artisan migrate --seed`.
5. Criar o link simbólico: remover a pasta `public_html/one` e
   `ln -s /home/uXXXXXX/qeai-one/public /home/uXXXXXX/public_html/one`.
6. Permissões: `storage/` e `bootstrap/cache/` graváveis; nada de `777`.
7. Cron: `* * * * * php /home/uXXXXXX/qeai-one/artisan schedule:run >> /dev/null 2>&1`.
8. Emitir/validar SSL de `one.qeai.com.br`.
9. Confirmar que `/.env`, `/.git` e `app/` **não** respondem pela web.

**Atualizações:** `git pull` + `composer install` + `php artisan migrate` + limpar caches.
Homologação (`homolog.qeai.com.br`) com banco separado antes da produção.

---

## 12. Ordem de implementação (checklist da Fase 0)

1. Esqueleto Laravel + MySQL no repositório `Emerson-Foco/qeai-one`; `.env.example`; `.gitignore`.
2. Migrations do núcleo (users, organizations, memberships, roles, invitations, settings, plans,
   plan_features, feature_overrides, subscriptions, audit_logs).
3. Serviço de **auditoria** (hash encadeado, dois escopos).
4. **Assistente de instalação** (8 etapas) + bloqueio e chave.
5. **Autenticação** (login, verificação, reset, 2FA) e sessões em banco.
6. **RBAC** + middleware de tenant + testes de isolamento.
7. **Painel Root**: organizações, planos, configurações (identidade, SMTP, pagamento), logs.
8. **Onboarding de organização** + membros + convites.
9. **White label** (identidade aplicada ao layout e aos e-mails).
10. Deploy no `one.qeai.com.br` com link simbólico + cron; validar SSL e bloqueios.
11. Documentos legais e DPA (termos iniciais).

---

## 12.1 Status de implementação (ambiente pronto)

- Projeto Laravel **12.69.2** criado em `E:\Developer\Web\qeai-one` (fora do XAMPP), branch `main`, primeiro commit feito.
- **Composer** instalado localmente em `E:\Developer\Tools\composer.phar` (executado com `php -d extension=zip composer.phar`).
- Banco local **MySQL** `qeai_one` criado; `.env` aponta para MySQL, `APP_NAME="Q One"`, locale `pt_BR`, fuso `America/Sao_Paulo`. Migrações iniciais aplicadas.
- Aplicação validada localmente (`php -S` em `public/`) com HTTP 200.
- Frontend será **HTML/CSS/JS puro** (sem Vite/Node) — os assets ficam em `public/` e são referenciados diretamente nas views.
- Pendente: criar o repositório no GitHub (`Emerson-Foco`) e adicionar o remote; e iniciar o núcleo da Fase 0 (instalador, auth, tenancy, RBAC, auditoria).

## 13. Pendências e melhorias futuras (limitações da hospedagem)

- **Asaas:** integrar quando você fornecer as credenciais (free no início).
- **Provedor de e-mail transacional:** avaliar se o limite do SMTP Hostinger passa a ser
  insuficiente; a abstração permite trocar sem reescrever.
- **E-mail de entrada (IMAP/webhook):** definir na Fase 1.
- **Tempo real no inbox:** polling no compartilhado; WebSocket em VPS futura.
- **RIPD/DPIA, pentest e plano de incidentes:** Fase 5.
- **Blog institucional:** permanece no repositório do site, sem impacto no Q One.
