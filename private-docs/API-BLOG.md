# API do Blog QEAI

> Referência para integração de agentes/automações. Base: `https://qeai.com.br/blog/api`.
> Os tokens são criados no painel: **Gerenciar blog → API**.

## Autenticação

Envie o token em um dos cabeçalhos:

```
X-Api-Key: qeai_blog_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

ou

```
Authorization: Bearer qeai_blog_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

Sem token válido → `401`. Token de IP não autorizado → `403`. Limite: **120 requisições/minuto por token**.

## Escopos e modos

Escopos: `posts:read`, `drafts:write`, `posts:publish`, `posts:delete`, `media:write`.

| Modo | O que faz |
|---|---|
| `draft` | Cria/edita **rascunhos**. Não publica. |
| `publisher` | Todos os escopos concedidos; publica direto. |

Regra: pedir `status` `published`/`scheduled` exige token **publicador** com `posts:publish`; caso contrário, `403` (nada é rebaixado silenciosamente).

## Endpoints

| Método | Rota | Escopo | Descrição |
|---|---|---|---|
| GET | `/posts` | `posts:read` | Lista (filtros: `status`, `q`) |
| POST | `/posts` | `drafts:write` ou `posts:publish` | Cria post |
| GET | `/posts/{id}` | `posts:read` | Lê (inclui `body_md` e `tags`) |
| PATCH | `/posts/{id}` | `drafts:write` ou `posts:publish` | Atualiza |
| DELETE | `/posts/{id}` | `posts:delete` | Move para lixeira (`?permanent=1` exclui de vez) |
| POST | `/posts/{id}/publish` | `posts:publish` (modo publicador) | Publica |
| POST | `/media` | `media:write` | Upload de arquivo (driver **local**, multipart `file`) |
| POST | `/media/presign` | `media:write` | URL assinada para upload direto (driver **S3**) |
| POST | `/media/confirm` | `media:write` | Registra objeto já enviado ao S3 |

### Criar post

```bash
curl -X POST https://qeai.com.br/blog/api/posts \
  -H "X-Api-Key: TOKEN" -H "Content-Type: application/json" \
  -d '{"title":"Título","body_md":"## Seção\n\nTexto.","tags":["IA","SEO"],"excerpt":"Resumo"}'
```

Campos aceitos: `title`, `slug`, `excerpt`, `body_md`, `status`, `published_at`, `category_id`, `cover_media_id`, `featured`, `tags`, `seo_title`, `seo_description`, `canonical_url`.

### Mídia via S3 (upload direto)

1. `POST /media/presign` com `{"extension":"jpg"}` → `{ upload_url, key, public_url }`.
2. Envie o arquivo com `PUT` para `upload_url`.
3. `POST /media/confirm` com `{"key":"...","mime":"image/jpeg","size":123,"width":1200,"height":800,"alt":"..."}`.

No driver **local** (sem S3), use `POST /media` com `multipart/form-data` no campo `file`; `/media/presign` responde `409`.

## Respostas e erros

Sucesso: `{ "data": ... }` (criação → `201`).
Erro: `{ "error": { "code": "...", "message": "..." } }` com `400/401/403/404/405/409/422/429`.

## Auditoria

Toda chamada é registrada em `blog_audit_logs` com `actor_type = token` e o id do token — visível em **Gerenciar blog → Auditoria**. Ações da API marcam a origem do post (`source = api`).

## Boas práticas ao plugar um agente

- Comece com um token de **rascunho** e aprove no painel antes de habilitar publicação.
- Prefira um token por agente, com escopos mínimos e expiração.
- Use IPs permitidos quando o agente tiver IP fixo.
- Para imagens, prefira o fluxo de upload direto ao S3 (o servidor compartilhado não recebe o arquivo).
