# Padrão de deploy e instalação

Este é o fluxo oficial. Não use `mysql` no terminal, não edite `config/db.php`
manualmente e não configure Remote MySQL para o mesmo servidor.

## 1. Atualizar o site via Git

No SSH, apenas para deploy:

```bash
SITE_ROOT="$HOME/domains/qeai.com.br/public_html"
SITE_REPO="$HOME/repos/qeai-site"

git -C "$SITE_REPO" fetch origin main
git -C "$SITE_REPO" --work-tree="$SITE_ROOT" reset --hard origin/main

# Remove uma configuração antiga/inválida para o painel começar limpo.
rm -f "$SITE_ROOT/config/db.php"
```

## 2. Criar o banco no hPanel

1. Abra o hPanel.
2. Entre em **Websites** → `qeai.com.br` → **Manage**.
3. No menu, abra **Databases** → **MySQL Databases**.
4. Clique em **Create database**, informe um nome e confirme.
5. A Hostinger cria o banco e um usuário automaticamente.
6. Anote o **nome do banco** e o **usuário**. Ambos normalmente começam com
   `u474596289_`.
7. Para definir uma senha conhecida: **Databases** → **MySQL Users** →
   **Change Password**.

Valores para o site:

| Campo | Valor |
|---|---|
| Host | `localhost` |
| Porta | `3306` |
| Banco | nome exato do hPanel |
| Usuário | usuário exato do hPanel |
| Senha | senha do usuário MySQL |

Não é necessário ativar **Remote MySQL** para o site rodando na mesma conta.

## 3. Configurar pelo painel do site

1. Abra `https://qeai.com.br/workspace/`.
2. Se aparecer **Conecte o banco do site**, preencha:
   - chave de instalação: conteúdo de `config/bootstrap.key`;
   - host, porta, banco, usuário e senha do hPanel.
3. Clique em **Conectar banco e importar estrutura**.
4. O painel testa a conexão, grava `config/db.php` com permissão `600` e importa
   a estrutura do blog sem posts de exemplo.
5. Na tela seguinte, crie o **ADM** com nome, e-mail e senha.
6. Confirme `https://qeai.com.br/blog` e `https://qeai.com.br/workspace/`.

Para obter a chave de instalação, use apenas uma vez:

```bash
cat ~/domains/qeai.com.br/public_html/config/bootstrap.key
```

## 4. Q One

O Q One tem o mesmo princípio, mas com instalador próprio:

1. Faça o deploy do projeto em `~/domains/one.qeai.com.br/qone`.
2. Aponte o document root do subdomínio para `qone/public`.
3. Abra `https://one.qeai.com.br/install`.
4. Informe os dados do banco do Q One no instalador.
5. Crie o ADM Root.

Use um banco separado para o Q One.

## Erros comuns

| Mensagem | Causa provável | Correção |
|---|---|---|
| `O banco configurado não respondeu` | `config/db.php` antigo/inválido | Remova o arquivo e use o formulário do painel |
| `Access denied for user` | usuário/senha incorretos ou sem permissão | Confira em **MySQL Users** e redefina a senha |
| `Unknown database` | nome do banco incorreto | Copie o nome exato do hPanel |
| `Connection refused` / `getaddrinfo` | host/porta incorretos | Use `localhost` e `3306` |
| `Não foi possível importar` | usuário sem permissão no banco | Use o usuário criado junto com o banco |

## Regra final

- **SSH:** somente Git/deploy e permissões.
- **hPanel:** banco e senha.
- **Painel do site:** conexão, importação e ADM.
- **Nunca** versione `config/db.php`.
