# Blog da QEAI — plano (landing page + blog + API para agente)

> Documento de planejamento. **Nenhum código alterado ainda.**
> Faz parte do site institucional (`qeai.com.br`), **separado** do Q One.
>
> **Documento histórico.** O `robots.txt` atual é gerado dinamicamente por
> `robots.php` a partir da URL canônica; não há mais arquivo estático.

Data: 28/09/2026

---

## 1. Decisões

| Tema | Decisão |
|---|---|
| Armazenamento do conteúdo | **MySQL**, com o **corpo do post em Markdown** |
| Renderização | Markdown → HTML, com **cache** de saída |
| Mídias (imagens/vídeos) | **Somente AWS S3** (nunca no servidor), via **upload direto com URL assinada** |
| Painel | **Agora** (B1 + B2), no `/workspace/` existente |
| API do agente | **Os dois modos**: token de **rascunho** e token **publicador** |
| Dependências | Sem Composer e sem Node: renderizador Markdown vendorizado + helper S3 (SigV4) em PHP puro |
| Armazenamento de mídia | **Abstração com drivers**: `local` (padrão, no servidor) quando o S3 não está configurado e `s3` quando estiver; ao configurar o S3, há **migração** da mídia local para o S3 |

---

## 2. Arquitetura

```
Navegador (painel) ──┐
                     ├──► Site PHP (Apache/Hostinger) ──► MySQL (posts, mídia, tokens)
Agente de IA ────────┘             │
                                    └── gera URL assinada (sigv4)
Navegador/Agente ──────────────────────► AWS S3 (upload/download direto)
```

- O **servidor não guarda mídia**: só a referência (chave + metadados) no banco.
- O **conteúdo** vive no MySQL; a **mídia** vive no S3.
- O site continua leve e o deploy simples (só código + schema).

---

## 3. Modelo de dados (MySQL)

- **blog_posts** — `id`, `slug` (único), `title`, `excerpt`, `body_md` (texto Markdown),
  `status` (`draft`/`scheduled`/`published`/`trash`), `published_at`, `updated_at`, `created_at`,
  `author_name`, `seo_title`, `seo_description`, `canonical_url`, `cover_media_id`,
  `featured` (bool), `reading_time`, `source` (`panel`/`api`), `api_token_id` (nulo),
  `deleted_at` (exclusão reversível).
- **blog_categories** — `id`, `name`, `slug`, `description`, `seo_title`, `seo_description`.
- **blog_tags** — `id`, `name`, `slug`.
- **blog_post_tags** — `post_id`, `tag_id`.
- **blog_media** — `id`, `driver` (`s3`), `bucket`, `key`, `mime`, `width`, `height`, `size`,
  `alt`, `title`, `created_at`, `uploaded_by`.
- **blog_revisions** — `id`, `post_id`, `title`, `body_md`, `changed_by`, `created_at`
  (histórico para editar/restaurar).
- **blog_api_tokens** — `id`, `name`, `token_hash`, `mode` (`draft`/`publisher`), `scopes` (json),
  `allowed_ips` (json, opcional), `last_used_at`, `expires_at`, `revoked_at`, `created_at`.
- **blog_audit_logs** — `id`, `actor_type` (`user`/`token`), `actor_id`, `action`,
  `entity_type`, `entity_id`, `ip`, `user_agent`, `meta` (json), `created_at`.
- **blog_settings** — chave/valor (credenciais S3 **cifradas**, autor padrão, título/descrição do blog).

---

## 4. Rotas públicas e SEO

**Rotas (URLs limpas):**
- `/blog` — lista paginada
- `/blog/{slug}` — post
- `/blog/categoria/{slug}` e `/blog/tag/{slug}`
- `/feed` — RSS/Atom
- `/sitemap.xml` — sitemap dinâmico (posts, categorias, tags, páginas)
- 404 dedicada

**SEO em cada página:**
- `<title>` e `meta description` por post (com fallback).
- `link rel=canonical`; `rel=prev`/`rel=next` na paginação.
- Open Graph + Twitter Card (capa como imagem).
- JSON-LD: `BlogPosting` + `BreadcrumbList` + `WebSite`/`Organization`.
- Breadcrumbs visíveis e semânticos.
- Imagens com `alt`, `width`/`height`, `srcset` responsivo e `loading="lazy"`.
- Vídeo: YouTube/Vimeo em modo privacidade ou `<video>` do S3; dados estruturados de vídeo.
- `robots.txt` com `Sitemap: https://qeai.com.br/sitemap.xml` e liberação de `/blog`.
- Performance: HTML renderizado e **cacheado** (sem consulta pesada por acesso).

---

## 5. Painel (B2) — no `/workspace/`

Reaproveita o login, o CSRF e o rate limit já existentes. Continua sendo **um admin**
(multiusuário e RBAC são responsabilidade do Q One, não do site).

- Lista de posts com filtros (status, categoria, tag, busca) e ações em lote.
- Editor: título, slug (gerado do título, editável), resumo, **corpo em Markdown com
  pré-visualização**, capa, categorias, tags, campos de SEO, status e **agendamento**.
- **Revisões**: ver histórico e restaurar.
- **Lixeira** (exclusão reversível).
- **Biblioteca de mídia**: listar, enviar (direto ao S3), editar `alt`, excluir.
- **Tokens de API**: criar, ver escopos/modo, revogar, ver último uso.
- **Configurações**: credenciais do S3 (cifradas), dados do blog, autor padrão.

---

## 6. API do agente (B3)

Autenticação por **token** (`Authorization: Bearer ...`), com hash no banco e **escopos**:

| Escopo | Permite |
|---|---|
| `posts:read` | listar/ler posts |
| `drafts:write` | criar/editar rascunhos |
| `posts:publish` | publicar/despublicar |
| `posts:delete` | excluir (reversível) |
| `media:write` | gerar upload e registrar mídia |

**Dois modos prontos:**
- **Rascunho** — `posts:read`, `drafts:write`, `media:write`. O agente nunca publica; você aprova.
- **Publicador** — todos os escopos. O agente publica direto.

**Endpoints (exemplos):**
- `GET /blog/api/posts`, `POST /blog/api/posts`, `PATCH /blog/api/posts/{id}`,
  `DELETE /blog/api/posts/{id}`
- `POST /blog/api/posts/{id}/publish`
- `POST /blog/api/media/presign` e `POST /blog/api/media/confirm`

**Segurança:** rate limit por token, IPs permitidos (opcional), expiração, revogação imediata e
**auditoria de toda chamada** (`blog_audit_logs`), com qual token/agente fez o quê.

---

## 7. Upload direto para o S3

1. O painel (ou o agente) pede `POST /blog/api/media/presign` informando nome, tipo e tamanho.
2. O servidor valida permissão/tipo/tamanho, gera uma **chave única** e devolve uma
   **URL assinada (PUT)** — o segredo da AWS **nunca** vai para o cliente.
3. O arquivo é enviado **direto ao S3**.
4. `POST /blog/api/media/confirm` registra a mídia no banco (com `alt`, dimensões etc.).
5. Emissão por URL pública do bucket (necessário para SEO de imagens); CDN (CloudFront) como
   melhoria futura.

**Configuração do bucket (você faz no console AWS):** bucket dedicado, bloqueio de acesso público
parcial apenas para leitura de objetos, CORS liberando o domínio `qeai.com.br`, e um **usuário IAM
com permissão mínima** (PutObject/GetObject/DeleteObject/ListBucket no bucket). As credenciais
ficam cifradas no `blog_settings`.

> **Nota técnica:** o helper S3 será implementado em **PHP puro (assinatura SigV4)** e coberto por
> testes, para não adicionar o SDK/Composer. Se preferirmos robustez máxima depois, troca-se pelo
> SDK oficial sem mudar o restante.

---

## 8. Renderização e cache

- **Markdown:** Parsedown (arquivo único, MIT) vendorizado em `app/vendor/parsedown/`; saída
  sanitizada.
- **Cache:** HTML final por post em `storage/blog-cache/`, chaveado por `id` + `updated_at`;
  invalidado ao salvar/publicar. Listas e sitemap com cache curto.
- **Imagens:** `srcset` gerado a partir das dimensões conhecidas; sem processamento pesado no
  servidor (a imagem já chega pronta ao S3). Processamento opcional com GD.

---

## 9. Segurança e LGPD

- Credenciais do S3 **cifradas** (AES-256-GCM, como já se faz no site).
- Tokens de API guardados **com hash**; mostrados uma única vez.
- Painel protegido pelo login atual + CSRF + rate limit.
- Validação de tipo/tamanho de arquivo; varredura de MIME real (`fileinfo`).
- Auditoria de ações do painel e da API.
- Se houver comentários/newsletter no futuro: base legal, consentimento e moderação.
- Conteúdo gerado por IA: recomendação de **revisão humana** (qualidade, E-E-A-T) e checagem de
  licença de imagens.

---

## 10. Impacto nos arquivos existentes

- `.htaccess`: novas rotas (`/blog`, `/blog/{slug}`, categoria/tag, `/feed`, `/sitemap.xml`,
  `/blog/api/*`) e redirecionamentos.
- `robots.txt`: incluir o sitemap e liberar `/blog`.
- Menu/rodapé: link para o blog.
- `private-docs/build-package.ps1`: incluir os novos arquivos (`app/blog*`, `blog.php`,
  `sitemap.php`, `feed.php`, `app/vendor/parsedown/`).
- `private-docs/INSTALL.md`: passos de criação do banco e configuração do S3.
- Novo: `config/db.php`/`.env` local fora da web para credenciais do MySQL.

---

## 11. Etapas

1. **B1 — Fundação:** schema MySQL, conexão segura, rotas públicas, parser Markdown + cache,
   listagem/post/categoria/tag, sitemap, RSS, 404, SEO completo.
2. **B2 — Painel:** CRUD, editor Markdown com preview, revisões, lixeira, agendamento,
   biblioteca de mídia e tokens.
3. **B3 — API do agente:** tokens/escopos, modos rascunho e publicador, upload direto ao S3,
   auditoria e rate limit.

---

## 12. Status de implementação

**B1 — Fundação: concluída e testada.**

- Banco `qeai_blog` com schema completo (`app/blog_install.sql`) e 3 posts de exemplo.
- Conexão PDO segura (`app/db.php` + `config/db.php`, privado).
- Leitura do blog (`app/blog.php`), Markdown com Parsedown em safe mode e cache em `storage/blog-cache/`.
- Páginas públicas `/blog`, `/blog/{slug}`, `/blog/categoria/{slug}`, `/blog/tag/{slug}`, busca, 404.
- SEO: canonical, meta/OG/Twitter, JSON-LD (BlogPosting e BreadcrumbList), breadcrumbs, paginação com `rel`.
- `/sitemap.xml` (15 URLs) e `/feed` (RSS) gerados.
- Navegação e rodapé com link para o blog; links e ativos corrigidos para subpáginas (funciona em produção na raiz e local em `/qeai/`).
- `.htaccess`, `robots.txt` e `build-package.ps1` atualizados.
- Validado: suíte existente 27/27 e testes HTTP no Apache/XAMPP.

**B2 — Painel: concluída e testada.**

- Painel em `workspace/blog.php`: lista com filtros, editor Markdown com pré-visualização servidor-side, revisões com restauração, lixeira com restauração e exclusão definitiva, agendamento e publicação.
- Biblioteca de mídia com upload e metadados (`alt`, `title`).
- Armazenamento com abstração de driver: **local** por padrão (`media/blog/`) e **S3** quando configurado, com **migração** da mídia local para o S3.
- Tela de configurações (identidade do blog + credenciais S3 cifradas) e de **tokens de API** (rascunho/publicador, escopos, revogação).
- Log de **auditoria** das ações do painel.
- Validado: suíte HTTP isolada (22 verificações) e suíte original (27/27). O driver S3 foi implementado com assinatura SigV4, mas ainda **não testado com credenciais reais** — será validado quando você configurar o bucket.

**B3 — API do agente: concluída e testada (pronta para uso, ainda sem agente conectado).**

- Endpoints REST em `/blog/api`: listar/ler/criar/editar/excluir posts, publicar e mídia.
- Autenticação por token (`X-Api-Key` ou `Authorization: Bearer`), com **escopos**, **modo rascunho/publicador**, IPs permitidos, expiração e revogação.
- Rate limit (120/min por token) e auditoria de toda chamada (`actor_type = token`).
- Mídia: `POST /media` no driver local; `presign` + `confirm` para upload direto no S3.
- Referência em `private-docs/API-BLOG.md`.
- Validado por suíte HTTP isolada (18 verificações), roteamento real no Apache e suíte original 27/27.

**Observação:** o fluxo S3 (`presign`/`confirm`) está implementado, mas ainda não testado com credenciais reais.

## 13. Melhoria — editor visual (WYSIWYG)

Para melhorar a experiência, o campo de conteúdo do painel passou a ter um **editor visual tipo Word**, que grava e lê **Markdown** por trás (o formato já usado pelo blog).

- Biblioteca **Toast UI Editor** (MIT), servida localmente em `assets/vendor/toastui/` — sem CDN e sem Node.
- Aba visual como padrão, com **aba Markdown** para quem preferir o texto puro.
- Formatação: títulos, negrito, itálico, riscado, linha, citação, listas, tabela, link, imagem, código.
- **Upload de imagem dentro do editor** (endpoint AJAX `upload_inline`, autenticado por sessão + CSRF), gravando no driver de mídia ativo (local ou S3).
- Com JavaScript desabilitado, o campo de texto simples continua funcionando (degradação graciosa).
- Os assets do editor só carregam nas telas de novo/editar post (a lista não os baixa).
- A saída continua em Markdown e continua sendo renderizada com Parsedown em modo seguro.

### Rascunho automático (autosave)

- Enquanto a pessoa escreve, o editor salva sozinho um **rascunho** (2,5 s após parar de digitar e, no máximo, a cada 30 s).
- O autosave **nunca publica**: preserva o status atual e cria como rascunho quando o post é novo.
- Mostra o estado no editor (alterações não salvas / salvando / salvo às HH:MM).
- Avisa antes de sair da página com alterações pendentes.
- Exige permissão de criar/editar e é protegido por CSRF (retorna 403 caso contrário).

### Usuários e grupos de acesso

- Login do painel por **e-mail** (não mais usuário).
- O **ADM** gerencia pessoas em `workspace/users.php` e cria/edita **grupos de acesso** com permissões configuráveis.
- Grupos padrão: **ADM** (total), **Desenvolvedor**, **Escritor** e **Convidado**, com as permissões ajustáveis.
- **Convite por link** (padrão): a pessoa define a própria senha por um link válido por 7 dias. Alternativa de **senha provisória** exibida uma única vez, com troca obrigatória no primeiro acesso.
- **Recuperação de senha por e-mail** ("Esqueci minha senha"), usando o SMTP configurado; regrava a senha por link válido por 1 hora. Os links ficam guardados como hash na tabela `user_tokens`.
- As telas e ações do painel (site, blog, mídia, tokens, auditoria, usuários) respeitam as permissões do grupo.

### Pré-visualização privada

- Botão **Pré-visualizar** no editor abre uma **nova aba** com o post renderizado como ele ficará no blog.
- Funciona para **rascunho, agendado ou publicado**, e até para **conteúdo ainda não salvo** (envia o formulário sem gravar).
- Mostra também as **configurações do post** (status, slug, publicação, categoria, tags, SEO, canônica, capa).
- É **privada**: exige login, não é pública e envia `noindex` (cabeçalho `X-Robots-Tag` e meta), então não aparece nos buscadores.
- Usa `formtarget="_blank"` + `formaction`, funcionando sem JavaScript.

## 13. Setup necessário e pendências

- Criar **bucket S3** e **usuário IAM** com permissão mínima; informar região e bucket.
  As chaves serão guardadas cifradas (não envia segredo por chat; cadastra no painel).
- Criar o **banco MySQL** do site (local usa o do XAMPP; produção na Hostinger).
- Definir nome/descrição do blog e categorias iniciais.
- Decidir se o bucket será leitura pública dos objetos (recomendado para SEO) ou URLs assinadas.
- Definir se queremos **CloudFront** já no início (opcional).
