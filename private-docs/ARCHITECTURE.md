# QEAI Landing — Arquitetura

## Visão geral
Landing page PHP para hospedagem compartilhada, sem banco de dados obrigatório. A configuração operacional é persistida em JSON e administrada por uma área autenticada que não aparece na navegação pública.

## Estrutura
- `index.php`: landing page.
- `contact.php`: recebimento e validação do formulário.
- `terms.php` / `privacy.php` / `cookies.php`: páginas legais.
- `blog.php`: blog (rotas limpas `/blog`, `/blog/{slug}`, categoria e tag).
- `sitemap.php` / `feed.php`: sitemap XML e RSS para SEO.
- `workspace/blog.php`: painel do blog (CRUD, revisões, lixeira, mídia, tokens de API, configurações) com editor visual e rascunho automático.
- `workspace/users.php`: usuários e grupos de acesso (permissões configuráveis).
- `app/auth.php`: autenticação por e-mail e autorização por permissões (papéis).
- `assets/vendor/toastui/`: Toast UI Editor (MIT) para a edição visual que grava Markdown; `assets/js/blog-editor.js` faz a ponte.
- `blog_api.php`: API do blog para agentes/automações (tokens com escopo, auditoria e rate limit).
- `workspace/`: painel privado.
- `app/`: bootstrap, segurança, SMTP e persistência.
- `assets/`: CSS, JavaScript e SVGs.
- `storage/`: configuração e rate limits; acesso web negado.
- `config/`: chaves privadas; acesso web negado.
- `private-docs/`: documentação; acesso web negado.

## Persistência
`storage/config.json` é suficiente para o site institucional atual. A gravação é atômica e a senha SMTP é criptografada com AES-256-GCM usando `config/app.key`.

O blog usa um banco MySQL próprio (`config/db.php`, privado e fora do Git). O corpo dos posts é Markdown, renderizado com Parsedown (safe mode) e cacheado em `storage/blog-cache/`. As consultas usam PDO com prepared statements.

## Deploy

O código é publicado por Git + SSH na branch `main`. O repositório fica fora de
`public_html` e é aplicado com `git --work-tree`, sem expor `.git`. O docroot do
Q One aponta para a pasta `public/` do projeto Laravel.

- Guia completo: `private-docs/DEPLOY-GIT.md`.
- Chaves, banco e configuração operacional ficam fora do Git.
- Nunca usar `git clean -fdx` em produção.

## URL do Q One

O site inclui um botão de acesso ao web app em `site.app_url` (padrão
`https://one.qeai.com.br`). Ele aparece no header, no hero, na seção de produto e no
rodapé. Para alterar, edite `storage/config.json` (ou o padrão em
`app/config_store.php`).

## Padrão visual

A camada premium fica em `assets/css/site-premium.css` e `assets/js/site-premium.js`,
carregada depois dos arquivos base. O padrão completo, componentes, animações e regras
de segurança estão em `private-docs/UI-STANDARDS.md`.

## Segurança
- Senha do painel com `password_hash()` e `password_verify()`.
- Regeneração de ID de sessão após login.
- Cookie de sessão HttpOnly, SameSite=Lax e Secure em HTTPS.
- CSRF em formulário público e painel.
- Rate limit de contato e login.
- Honeypot no formulário.
- Escape de saída em HTML.
- SMTP autenticado com remetente fixo e visitante somente em `Reply-To`.
- Diretórios internos bloqueados por `.htaccess`.
- `X-Robots-Tag` e `noindex` na área privada.

## Privacidade / cookies
Nenhum analytics ou pixel de marketing está incluído. O gestor de consentimento mantém opcionais desligados por padrão e o site continua funcional quando o usuário recusa.

Integrações futuras devem verificar:
```js
window.QEAIConsent.can('analytics')
window.QEAIConsent.can('marketing')
```
O evento `qeai:consent` é disparado quando a escolha muda.

## SMTP
Cliente SMTP implementado em PHP com suporte a SMTPS (porta 465) e STARTTLS (porta 587), autenticação LOGIN e validação TLS do certificado.

## Evolução
Ao adicionar CRM, leads persistentes, campanhas ou múltiplos usuários, migrar configuração/dados operacionais para MySQL/MariaDB e adicionar auditoria, papéis, 2FA e política formal de retenção.

## Revisão de setembro de 2026

- Layout responsivo, menu móvel e checkbox nativo com dimensões fixas.
- Conteúdo visível mesmo sem JavaScript; navegação e formulários continuam disponíveis.
- Feedback do formulário em sessão, com redirecionamento 303, mensagens por campo e recuperação dos valores após erro. Sem incluir dados pessoais na URL.
- Sessões em `storage/sessions`, com criação automática dos diretórios e Cache-Control no-store.
- Configuração inválida interrompe a operação, sem permitir uma nova instalação por falha de leitura.
- Gravação da configuração em arquivo temporário exclusivo; limite de tentativas protegido por bloqueio durante leitura e escrita.
- Instalação com chave não vazia, limite de tentativas e bloqueio de concorrência. O hash salvo do administrador determina se a instalação foi concluída.
- Saída do painel por POST com CSRF; alteração de senha exige a senha atual.
- SMTP exige TLS e não transforma falha de encerramento em falha de entrega após a aceitação da mensagem pelo servidor.
- Preferências de cookies têm controle de foco e Escape. LocalStorage indisponível não impede o funcionamento da página. Não há pixels ou analytics instalados.
- `.user.ini` desativa a exibição pública de erros e ativa logs. `.htaccess` não exige overrides de Options ou ServerSignature.
- `private-docs/build-package.ps1`: gera o ZIP de atualização sem as chaves e a configuração do servidor.

## URLs públicas

As rotas públicas são `/`, `/termos`, `/privacidade`, `/cookies` e `/contato`. O Apache/LiteSpeed encaminha internamente essas rotas para os arquivos PHP. Requisições antigas para arquivos `.php` recebem redirecionamento 301 para o endereço limpo, inclusive `/workspace/index.php` para `/workspace/`. As páginas públicas também publicam uma URL canônica sem extensão.
