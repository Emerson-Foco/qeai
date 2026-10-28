# Deploy via Git na Hostinger — Site QEAI + Q One

Guia operacional para publicar e atualizar o **site institucional** e o **Q One**
em hospedagem compartilhada Hostinger, usando **Git + SSH**, com deploy keys
somente leitura.

> **Domínios usados neste guia**
> - Site: `qeai.com.br`
> - Q One: `one.qeai.com.br`
>
> O site deriva as URLs do host quando `site.url` está vazio. O domínio
> canônico pode ser gravado em `storage/config.json` sem alterar código.

---

## 1. Arquitetura do deploy

| Aplicação | Repositório | Branch | Document root |
|---|---|---|---|
| Site QEAI | `github.com/Emerson-Foco/qeai` | `main` | `~/domains/qeai.com.br/public_html` |
| Q One | `github.com/Emerson-Foco/QEAI-One` | `main` | `~/domains/one.qeai.com.br/qone/public` |

- O site é PHP puro; **não tem build** (sem Node/Vite).
- O Q One é Laravel; o docroot **precisa** ser a pasta `public/`.
- O `.git` do site fica fora do `public_html` e o código é aplicado com
  `git --work-tree`. O `.git` do Q One fica em `qone/.git`, acima de `public/`.
- Arquivos de configuração e runtime **nunca** entram no Git.

```
~/domains/qeai.com.br/public_html/          arquivos públicos do site (sem .git)
~/repos/qeai-site/.git                   repositório do site
~/domains/one.qeai.com.br/qone/          projeto Q One (.env, vendor, storage)
~/domains/one.qeai.com.br/qone/public/   docroot do Q One
~/backups/                               banco, arquivos e .env
```

### Regras de ouro

1. Nunca aponte o docroot do Q One para `qone/`; apenas para `qone/public`.
2. Nunca coloque `.git` dentro de `public_html`.
3. Nunca use `git clean -fdx` em produção.
4. Nunca versione `.env`, chaves, `vendor/`, `storage/` de runtime ou bancos.
5. Toda alteração entra por commit na `main`, testada localmente.

---

## 2. Pré-requisitos

No hPanel da Hostinger:

- [ ] Domínio `qeai.com.br` apontando para a Hostinger.
- [ ] Subdomínio `one.qeai.com.br` criado.
- [ ] SSL ativo nos dois domínios.
- [ ] PHP 8.3 (mínimo 8.2) com `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`,
      `xml`, `ctype`, `json`, `fileinfo` e `curl`.
- [ ] Banco MySQL exclusivo para o site/blog.
- [ ] Banco MySQL exclusivo para o Q One.
- [ ] Acesso SSH habilitado.
- [ ] Chave SSH local cadastrada no hPanel.
- [ ] Cron disponível.

Confirme no servidor:

```bash
php -v
php -m
which php
```

> Em alguns planos o PHP do terminal é diferente do PHP do site. Use o caminho
> indicado no hPanel (ex.: `/opt/alt/php83/usr/bin/php`).

---

## 3. GitHub e chaves SSH

### 3.1 Chave SSH da sua máquina (para push)

No Windows PowerShell:

```powershell
ssh-keygen -t ed25519 -C "seu-email@qeai.com.br"
Get-Content $env:USERPROFILE\.ssh\id_ed25519.pub | Set-Clipboard
ssh -T git@github.com
```

No GitHub: **Settings → SSH and GPG keys → New SSH key**. Cole a chave pública.

Configure os remotes para SSH:

```bash
git -C "C:/xampp/htdocs/qeai" remote set-url origin git@github.com:Emerson-Foco/qeai.git
git -C "E:/Developer/Web/qeai-one" remote set-url origin git@github.com:Emerson-Foco/QEAI-One.git
```

Envie a branch principal:

```bash
git -C "C:/xampp/htdocs/qeai" push -u origin main
git -C "E:/Developer/Web/qeai-one" push -u origin main
```

### 3.2 Chaves de deploy no servidor (somente leitura)

Acesse o servidor:

```bash
ssh -p PORTA uUSUARIO@SEU_HOST
```

Gere **uma chave por repositório**:

```bash
mkdir -p ~/.ssh && chmod 700 ~/.ssh

ssh-keygen -t ed25519 -C "deploy-qeai-site" -f ~/.ssh/qeai_site -N ""
ssh-keygen -t ed25519 -C "deploy-qeai-one" -f ~/.ssh/qeai_one -N ""

chmod 600 ~/.ssh/qeai_site ~/.ssh/qeai_one
cat ~/.ssh/qeai_site.pub
cat ~/.ssh/qeai_one.pub
```

No GitHub, em cada repositório: **Settings → Deploy keys → Add deploy key**.
Marque **Allow write access? Não**.

- `Emerson-Foco/qeai` → chave `qeai_site.pub`, título `Hostinger site`.
- `Emerson-Foco/QEAI-One` → chave `qeai_one.pub`, título `Hostinger Q One`.

Configure os aliases:

```bash
cat >> ~/.ssh/config <<'EOF'
Host github-qeai-site
    HostName github.com
    User git
    IdentityFile ~/.ssh/qeai_site
    IdentitiesOnly yes

Host github-qeai-one
    HostName github.com
    User git
    IdentityFile ~/.ssh/qeai_one
    IdentitiesOnly yes
EOF

chmod 600 ~/.ssh/config
ssh -T git@github-qeai-site
ssh -T git@github-qeai-one
```

O GitHub deve confirmar a autenticação. Se a porta 22 estiver bloqueada, use
`HostName ssh.github.com` e `Port 443` nos dois blocos.

---

## 4. Preparar o servidor

```bash
pwd
ls ~/domains

mkdir -p ~/backups && chmod 700 ~/backups

git config --global user.name "Deploy Hostinger"
git config --global user.email "deploy@qeai.com.br"
git config --global pull.ff only
```

Defina as variáveis da sessão:

```bash
export SITE_DOMAIN="qeai.com.br"
export APP_DOMAIN="one.qeai.com.br"

export SITE_ROOT="$HOME/domains/$SITE_DOMAIN/public_html"
export SITE_REPO="$HOME/repos/qeai-site"

export APP_ROOT="$HOME/domains/$APP_DOMAIN/qone"
```

> O site usa `qeai.com.br`; as URLs internas são derivadas do host ou de
> `storage/config.json`.

---

## 5. Primeiro deploy do site

### 5.1 Backup do que já existe

Se `public_html` já tiver arquivos:

```bash
tar -czf ~/backups/site-antes-$(date +%F-%H%M).tar.gz -C "$SITE_ROOT" .
```

### 5.2 Clonar sem `.git` no docroot

```bash
mkdir -p "$SITE_REPO" "$SITE_ROOT"

git clone --no-checkout git@github-qeai-site:Emerson-Foco/qeai.git "$SITE_REPO"
git -C "$SITE_REPO" --work-tree="$SITE_ROOT" checkout -f main
```

Isso copia apenas os arquivos versionados para `public_html`. O `.git` fica em
`~/repos/qeai-site/.git`.

### 5.3 Configuração privada

Crie as chaves (não substitua chaves existentes):

```bash
cd "$SITE_ROOT"

[ -s config/app.key ] || openssl rand -base64 48 > config/app.key
[ -s config/bootstrap.key ] || openssl rand -hex 32 > config/bootstrap.key
chmod 600 config/app.key config/bootstrap.key
```

Crie os diretórios de runtime:

```bash
mkdir -p storage/sessions storage/rate-limit storage/blog-cache storage/media
find storage -type d -exec chmod 775 {} \;
find storage -type f -exec chmod 644 {} \;
```

Não use `777`.

### 5.4 Banco do site

O caminho recomendado é abrir `https://qeai.com.br/workspace/` após o deploy.
O painel mostra **Conecte o banco do site**, testa a conexão, grava
`config/db.php` com permissão `600` e importa `app/blog_install.sql` sem
conteúdo de exemplo. Informe host, porta, banco, usuário, senha e a chave de
`config/bootstrap.key`.

Alternativa manual via SSH:

```bash
cd "$SITE_ROOT"
cp config/db.example.php config/db.php
nano config/db.php
chmod 600 config/db.php
mysql -h DB_HOST -u DB_USER -p DB_NAME < app/blog_install.sql
```

### 5.5 URL canônica do site

Depois da configuração inicial, ajuste a URL no JSON operacional:

```bash
cd "$SITE_ROOT"

php -r '
$f = "storage/config.json";
$c = is_file($f) ? json_decode(file_get_contents($f), true) : [];
$c["site"]["url"] = "https://qeai.com.br";
file_put_contents($f, json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
'

chmod 600 storage/config.json
```

O `robots.txt` é gerado dinamicamente por `robots.php` a partir de `site.url`.
Não edite `robots.txt` no servidor nem no repositório: ele não é mais um arquivo
estático. O sitemap também usa a URL canônica configurada.

### 5.6 Primeiro acesso ao painel do site

1. Abra `https://qeai.com.br/workspace/`.
2. Informe a chave lida de `config/bootstrap.key`:

   ```bash
   cat config/bootstrap.key
   ```

3. Crie o ADM com nome, e-mail e senha forte.
4. Configure SMTP, identidade visual, blog e S3 (se aplicável).
5. Confirme `/`, `/termos`, `/privacidade`, `/cookies`, `/blog` e `/sitemap.xml`.

### 5.7 HTTPS

No hPanel, ative **Forçar HTTPS** para o domínio. Confirme:

```bash
curl -I http://qeai.com.br
curl -I https://qeai.com.br
```

O primeiro deve redirecionar para HTTPS. O `.htaccess` do site já envia HSTS em
HTTPS.

---

## 6. Primeiro deploy do Q One

### 6.1 Clonar o projeto

```bash
mkdir -p "$APP_ROOT"
git clone git@github-qeai-one:Emerson-Foco/QEAI-One.git "$APP_ROOT"
cd "$APP_ROOT"
```

No hPanel, defina o **document root** do subdomínio como:

```
/home/uUSUARIO/domains/one.qeai.com.br/qone/public
```

Nunca aponte para `qone/` inteiro.

### 6.2 Criar o `.env`

```bash
cd "$APP_ROOT"
cp .env.example .env
nano .env
```

Use o modelo da seção 7. A `APP_KEY` será gerada no passo 6.4, depois do
Composer, porque `artisan` precisa de `vendor/autoload.php`.

### 6.3 Dependências

Se houver Composer no SSH:

```bash
composer install --no-dev --optimize-autoloader --no-interaction
```

Se não houver:

- use o recurso de Composer do hPanel, se disponível; ou
- rode `composer install --no-dev --optimize-autoloader` na sua máquina e envie
  a pasta `vendor/` por SFTP/rsync.

Nunca baixe instaladores de origem desconhecida.

### 6.4 Gerar a `APP_KEY`

```bash
php artisan key:generate --force
chmod 600 .env
```

Guarde a `APP_KEY` em local seguro. Trocar a chave depois invalida sessões,
dados criptografados e tokens.

### 6.5 Permissões

```bash
find storage bootstrap/cache -type d -exec chmod 775 {} \;
find storage bootstrap/cache -type f -exec chmod 644 {} \;
```

O `.env` deve ficar em `600`; arquivos em `644`; pastas em `755/775`; nunca `777`.

### 6.6 Banco e instalador

1. Crie banco e usuário MySQL exclusivos no hPanel.
2. Preencha `DB_*` no `.env`.
3. Abra `https://one.qeai.com.br/install`.
4. O instalador verifica requisitos, executa as migrations, cria o **ADM Root**
   e grava a identidade da plataforma.
5. Após concluir, `/install` retorna 404.
6. Entre com o ADM Root e conclua o 2FA.

### 6.7 Otimização

```bash
php artisan optimize
```

Se alterar o `.env` depois:

```bash
php artisan optimize:clear
php artisan optimize
```

### 6.8 Cron

No hPanel, crie uma tarefa **a cada minuto**:

```bash
/usr/bin/php /home/uUSUARIO/domains/one.qeai.com.br/qone/artisan schedule:run >> /dev/null 2>&1
```

Confirme o binário com `which php`. Em alguns planos:

```bash
/opt/alt/php83/usr/bin/php /home/uUSUARIO/domains/one.qeai.com.br/qone/artisan schedule:run >> /dev/null 2>&1
```

Valide:

```bash
php artisan schedule:list
```

### 6.9 SMTP, 2FA e termos

No painel Root:

1. Configure host, porta, criptografia, usuário e senha SMTP.
2. Salve e use **Verificação de entrega**.
3. Ative **2FA** no ADM Root.
4. Aceite os Termos de Uso no primeiro acesso.
5. Confirme `SITE_URL` apontando para o site (os links legais são derivados dele).

Sem SMTP configurado, convites e redefinições de senha não são enviados.

Configure SPF, DKIM e DMARC no DNS do domínio.

---

## 7. `.env` de produção do Q One

```env
APP_NAME="Q One"
APP_ENV=production
APP_KEY=base64:GERAR_CHAVE_UNICA
APP_DEBUG=false
APP_URL=https://one.qeai.com.br
APP_TIMEZONE=America/Sao_Paulo

APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=USUARIO_QONE
DB_USERNAME=USUARIO_QONE
DB_PASSWORD=SENHA_FORTE

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

CACHE_STORE=database
QUEUE_CONNECTION=sync

FILESYSTEM_DISK=local

MAIL_MAILER=log
MAIL_FROM_ADDRESS="nao-responda@qeai.com.br"
MAIL_FROM_NAME="Q One"

QEAI_MODULES=dashboard,leads,crm_fields,settings,logs

SITE_URL=https://qeai.com.br
LEGAL_TERMS_URL=
LEGAL_PRIVACY_URL=
LEGAL_REQUIRE_ACCEPTANCE=true
```

Observações:

- `APP_KEY` é gerada uma vez e guardada com backup.
- O SMTP real é configurado no painel Root, não no `.env`.
- `QUEUE_CONNECTION=sync` evita jobs pendentes sem worker na hospedagem
  compartilhada.
- `SESSION_ENCRYPT=true` criptografa o conteúdo da sessão no banco.
- `SITE_URL` é a base dos links de Termos e Privacidade. Se quiser URLs
  diferentes, preencha `LEGAL_TERMS_URL` e `LEGAL_PRIVACY_URL`.
- Ajuste o remetente conforme o domínio de e-mail.

---

## 8. Segurança do deploy

### 8.1 `.gitignore` e segredos

Confirme que estes caminhos **não** estão versionados:

```bash
# Q One
cd "$APP_ROOT"
git ls-files | grep -E '(^|/)\.env$|^vendor/|^storage/app/installed\.json$|^storage/logs/.*\.log$' || true
git check-ignore -v .env vendor storage/app/installed.json

# Site
cd "$SITE_ROOT"
git --git-dir="$SITE_REPO/.git" --work-tree="$SITE_ROOT" ls-files | \
  grep -E 'config/(app|bootstrap)\.key|config/db\.php|storage/config\.json|^media/' || true
git --git-dir="$SITE_REPO/.git" --work-tree="$SITE_ROOT" check-ignore -v \
  config/app.key config/bootstrap.key config/db.php storage/config.json media/
```

Os comandos devem retornar os caminhos ignorados, nunca arquivos sensíveis
versionados.

### 8.2 Checklist de segurança

- [ ] Deploy keys **read-only**, uma por repositório.
- [ ] 2FA na conta GitHub.
- [ ] Repositórios privados no GitHub.
- [ ] Branch `main` protegida contra force push.
- [ ] `.git` fora do docroot dos dois projetos.
- [ ] Docroot do Q One em `public/`.
- [ ] `.env` com permissão `600`.
- [ ] `APP_ENV=production` e `APP_DEBUG=false`.
- [ ] `APP_KEY` única e com backup.
- [ ] `SESSION_SECURE_COOKIE=true` e `SESSION_ENCRYPT=true`.
- [ ] HTTPS forçado e HSTS ativo.
- [ ] Banco com usuário de permissões mínimas.
- [ ] `storage/` e `bootstrap/cache/` graváveis sem `777`.
- [ ] `/install` bloqueado após a instalação.
- [ ] 2FA do ADM Root ativo.
- [ ] Termos e privacidade publicados.
- [ ] Cron do Q One funcionando.
- [ ] Logs fora do docroot.
- [ ] Backup de banco, `.env` e storage.
- [ ] Nenhum usuário/lead/dado de teste em produção.
- [ ] `/workspace/` do site com senha forte; opcionalmente proteja por IP ou
      autenticação básica no hPanel.
- [ ] Nenhum segredo em documento, issue, chat ou commit.

### 8.3 O que nunca fazer

```bash
# NÃO execute em produção
git clean -fdx
git checkout -- .
rm -rf storage
```

`git clean -fdx` apaga `.env`, `vendor/`, `storage/`, chaves e configurações não
versionadas. Para atualizar, use apenas `git fetch` + `git reset --hard`.

---

## 9. Atualizações de produção

### 9.1 Site

Backup antes:

```bash
tar -czf ~/backups/site-$(date +%F-%H%M).tar.gz -C "$SITE_ROOT" .
```

Aplicar a versão da `main`:

```bash
git -C "$SITE_REPO" fetch origin main
git -C "$SITE_REPO" --work-tree="$SITE_ROOT" reset --hard origin/main
```

Conferir:

```bash
git -C "$SITE_REPO" --work-tree="$SITE_ROOT" status --short
```

Não use `git clean`. Arquivos não versionados (`config/db.php`, chaves,
`storage/config.json`, `media/`) são preservados.

> `git reset --hard` não remove arquivos que deixaram de ser versionados. Se um
> arquivo público for removido do repositório, confira se ele ainda existe em
> `public_html` e remova manualmente. Nunca remova `config/`, `storage/` ou
> `media/` por engano.

Limpe o cache da Hostinger/CDN, se houver.

### 9.2 Q One

Backup do banco:

```bash
mysqldump --single-transaction --quick -h DB_HOST -u DB_USER -p DB_NAME \
  > ~/backups/qone-$(date +%F-%H%M).sql
```

Atualizar:

```bash
cd "$APP_ROOT"
php artisan down --secret="CODIGO_TEMPORARIO"

git fetch origin
git reset --hard origin/main

composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize

php artisan up
```

Se não houver Composer no servidor, envie o `vendor/` atualizado antes de
`php artisan optimize`.

Acesse `https://one.qeai.com.br/CODIGO_TEMPORARIO` para entrar durante a
manutenção.

---

## 10. Rollback

### 10.1 Site

```bash
git -C "$SITE_REPO" log --oneline -10
git -C "$SITE_REPO" --work-tree="$SITE_ROOT" reset --hard <commit-anterior>
```

Se a versão anterior exigir banco diferente, restaure o backup do banco do site.

### 10.2 Q One

```bash
cd "$APP_ROOT"
php artisan down

git log --oneline -10

# Se a migration precisar ser revertida, faça antes de trocar o código,
# enquanto o arquivo da migration ainda existe:
php artisan migrate:rollback --step=1

git reset --hard <commit-anterior>

composer install --no-dev --optimize-autoloader --no-interaction
php artisan optimize

php artisan up
```

Se o rollback da migration falhar, restaure o backup do banco e rode
`php artisan up`. Para migrations destrutivas, o backup do banco é o caminho
seguro.

---

## 11. Backups

### 11.1 Banco do site

```bash
mysqldump --single-transaction --quick -h DB_HOST -u DB_USER -p DB_NAME \
  > ~/backups/site-db-$(date +%F-%H%M).sql
```

### 11.2 Banco do Q One

```bash
mysqldump --single-transaction --quick -h DB_HOST -u DB_USER -p DB_NAME \
  > ~/backups/qone-db-$(date +%F-%H%M).sql
```

### 11.3 Arquivos e `.env`

```bash
tar -czf ~/backups/qone-files-$(date +%F-%H%M).tar.gz \
  --exclude='vendor' --exclude='node_modules' --exclude='.git' \
  -C "$APP_ROOT" .

cp "$APP_ROOT/.env" ~/backups/qone-env-$(date +%F).bak
chmod 600 ~/backups/*.bak ~/backups/*.sql ~/backups/*.tar.gz
```

Faça o mesmo para o site, incluindo `config/` e `storage/config.json`.

Mantenha cópias fora do servidor, criptografadas. Teste a restauração
periodicamente.

### 11.4 Restauração

```bash
mysql -h DB_HOST -u DB_USER -p DB_NAME < ~/backups/qone-db-AAAA-MM-DD-HHMM.sql
```

---

## 12. Novo ambiente de desenvolvimento

### 12.1 Site

```bash
git clone git@github.com:Emerson-Foco/qeai.git qeai-site
cd qeai-site

cp config/db.example.php config/db.php
# edite config/db.php

openssl rand -base64 48 > config/app.key
openssl rand -hex 32 > config/bootstrap.key
chmod 600 config/app.key config/bootstrap.key config/db.php

mkdir -p storage/sessions storage/rate-limit storage/blog-cache storage/media

mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS qeai_blog CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p qeai_blog < app/blog_install.sql
```

Sirva com Apache/XAMPP em `http://localhost/qeai-site/` e abra
`http://localhost/qeai-site/workspace/` para criar o ADM.

Valide:

```bash
php -l index.php
php tests/run.php
```

O servidor embutido do PHP não interpreta `.htaccess`; use Apache para testar
URLs limpas, bloqueios e headers.

### 12.2 Q One

```bash
git clone git@github.com:Emerson-Foco/QEAI-One.git qeai-one
cd qeai-one

cp .env.example .env
composer install
php artisan key:generate
```

Crie o banco `qeai_one` e ajuste `DB_*`:

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS qeai_one CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Para desenvolvimento:

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
SITE_URL=http://localhost/qeai
MAIL_MAILER=log
LEGAL_REQUIRE_ACCEPTANCE=false
```

Depois:

```bash
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

Abra `http://127.0.0.1:8000/install` na primeira vez.

Valide:

```bash
php artisan test
vendor/bin/pint --test
php artisan view:cache
php artisan view:clear
```

### 12.3 Documentação obrigatória

Antes de alterar cada aplicação:

- Site: `private-docs/UI-STANDARDS.md`, `private-docs/ARCHITECTURE.md`,
  `private-docs/INSTALL.md`, `private-docs/DEPLOY-GIT.md`.
- Q One: `docs/DESIGN-SYSTEM.md`, `docs/SECURITY.md`, `docs/DEPLOY-GIT.md`,
  `docs/CHECKLIST.md`, `AGENTS.md`.

Nunca use dados de produção no ambiente local. Nunca copie o `.env` de produção
para a máquina de desenvolvimento.

---

## 13. Checklist pós-deploy

- [ ] `https://qeai.com.br` abre com SSL válido.
- [ ] `/termos`, `/privacidade`, `/cookies`, `/blog` e `/sitemap.xml` abrem.
- [ ] Botão **Q One** abre `https://one.qeai.com.br`.
- [ ] `https://one.qeai.com.br/login` abre com SSL.
- [ ] `/install` do Q One retorna 404.
- [ ] Cadastro público cria organização e registra aceite dos termos.
- [ ] Login, 2FA e recuperação de senha funcionam.
- [ ] Convite por e-mail abre a tela de aceite.
- [ ] Organização, membros, grupos e permissões funcionam.
- [ ] Lead cria ID permanente e histórico.
- [ ] Campos personalizados criam, editam, desativam e reativam.
- [ ] Visões Tabela, Kanban, Calendário e Cronograma funcionam.
- [ ] Preferência de filtros salva por usuário/visão.
- [ ] API de leads responde com chave válida e rejeita sem chave.
- [ ] Webhooks de saída usam HTTPS e assinatura.
- [ ] SMTP testado (convite, reset e verificação).
- [ ] Cron executando sem erro.
- [ ] Backup do banco e do `.env` realizado.
- [ ] Nenhum dado de teste em produção.

---

## 14. Troubleshooting

### 500 no site

```bash
curl -I https://qeai.com.br
tail -n 80 ~/logs/*error* 2>/dev/null
```

Verifique:

- PHP 8.3 e extensões;
- `.htaccess` na raiz;
- permissões de `storage/`;
- `config/db.php` e `config/app.key` presentes;
- logs do hPanel.

### 500 no Q One

```bash
cd "$APP_ROOT"
tail -n 100 storage/logs/laravel.log
php artisan optimize:clear
php artisan about
```

Causas comuns: `APP_KEY` ausente, `.env` inválido, banco inacessível,
`storage/` sem escrita ou docroot apontando para a raiz do projeto.

### “No application encryption key has been specified”

```bash
php artisan key:generate --force
php artisan optimize:clear
php artisan optimize
```

### “could not find driver”

Ative `pdo_mysql` no PHP do hPanel e confirme o mesmo PHP no terminal.

### Composer sem memória

```bash
COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --optimize-autoloader --no-interaction
```

### Git: Permission denied (publickey)

```bash
ssh -T git@github-qeai-site
ssh -T git@github-qeai-one
```

Confirme a deploy key no GitHub, o `IdentityFile` e as permissões `600`.

### Git: detected dubious ownership

```bash
git config --global --add safe.directory "$SITE_REPO"
git config --global --add safe.directory "$APP_ROOT"
```

### Cron não executa

- Confirme o caminho do PHP com `which php`.
- Teste manualmente o comando do cron.
- Verifique permissões do `artisan`.
- Consulte os logs do hPanel.

### Sessão expira ou login não persiste

- `storage/framework/sessions` gravável no Q One.
- `storage/sessions` gravável no site.
- `SESSION_SECURE_COOKIE=true` somente com HTTPS.
- `APP_URL` correto.

### `robots.txt` com o domínio errado

O arquivo é gerado por `robots.php` a partir de `site.url`. Corrija
`storage/config.json` e limpe o cache; não crie um `robots.txt` estático.

### Site aponta para o domínio errado

Ajuste `storage/config.json`:

```bash
cd "$SITE_ROOT"
php -r '
$f = "storage/config.json";
$c = json_decode(file_get_contents($f), true);
$c["site"]["url"] = "https://qeai.com.br";
file_put_contents($f, json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
'
```

---

## 15. Comandos úteis

```bash
# Versões
php -v
composer --version
git --version

# Q One
cd "$APP_ROOT"
php artisan about
php artisan route:list
php artisan schedule:list
php artisan migrate:status
php artisan optimize:clear
php artisan optimize
tail -n 100 storage/logs/laravel.log

# Site
cd "$SITE_ROOT"
php -l index.php
# php tests/run.php   # somente em desenvolvimento, nunca em produção

# Git
git -C "$SITE_REPO" --work-tree="$SITE_ROOT" status --short
git -C "$SITE_REPO" --work-tree="$SITE_ROOT" log --oneline -5
git -C "$APP_ROOT" status --short
git -C "$APP_ROOT" log --oneline -5
```

---

## 16. Referências internas

- Site: `private-docs/INSTALL.md`, `private-docs/ARCHITECTURE.md`,
  `private-docs/UI-STANDARDS.md`.
- Q One: `docs/SECURITY.md`, `docs/DESIGN-SYSTEM.md`, `docs/CHECKLIST.md`,
  `docs/DEPLOY-HOSTINGER.md`.
