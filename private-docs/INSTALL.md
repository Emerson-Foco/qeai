# Publicação e atualização na Hostinger

## O que foi verificado

Em 14/09/2026, a consulta pública a https://qeai.com.br/ devolveu HTTP 500, corpo vazio e o cabeçalho `X-Powered-By: PHP/8.3.33`. A versão de PHP informada atende aos requisitos do projeto. Sem acesso ao log da hospedagem, não é possível atribuir uma causa definitiva ao erro.

O projeto original abriu com HTTP 200 no Apache/PHP 8.2 local e passou na verificação de sintaxe. Foram aplicadas melhorias de compatibilidade, persistência e tratamento de erros. A resolução em produção deve ser confirmada após o upload e a consulta aos logs.

## Atualizar o site já enviado

1. Faça um backup dos arquivos atuais no hPanel, principalmente `config/app.key`, `config/bootstrap.key` e `storage/config.json`, se existir.
2. Envie `dist/qeai-hostinger-atualizacao.zip` para a pasta do domínio e extraia seu conteúdo diretamente em `public_html`. O resultado deve conter `public_html/index.php`, `public_html/app/bootstrap.php` e `public_html/assets/`. Evite uma pasta intermediária `qeai` ou `dist`.
3. Substitua os arquivos do código. Ative a exibição de arquivos ocultos e confirme a presença de `.htaccess` e `.user.ini` na raiz, além dos `.htaccess` internos.
4. O ZIP de atualização não contém chaves, senhas ou configuração operacional: preserve os arquivos existentes em `config/` e `storage/config.json`. As pastas não devem ser apagadas antes de extrair.
5. Se os arquivos `config/app.key` e `config/bootstrap.key` não tiverem sido enviados anteriormente, copie os originais desta pasta local. Não substitua uma chave que já exista no servidor. `app.key` é necessária para ler a senha SMTP salva.
6. Mantenha PHP 8.3, extensão OpenSSL habilitada e HTTPS ativo. Deixe `log_errors` ligado e `display_errors` desligado. A Hostinger também permite consultar os registros pelo hPanel.
7. As pastas `storage/`, `storage/rate-limit/` e `storage/sessions/` precisam permitir gravação pelo usuário PHP. O código cria as subpastas automaticamente. Use os padrões da hospedagem (em geral pastas 755, arquivos 644); não use 777. Se não houver gravação, confira o proprietário no hPanel/suporte.
8. Se houver cache de página inteira/CDN ativo, exclua páginas PHP com sessão e formulário do cache. O código envia `Cache-Control: no-store`. Limpe o cache da Hostinger após a atualização.
9. Abra a página inicial, `/privacidade`, `/cookies` e `/workspace/`. Confirme também que `/config/app.key` e `/storage/config.json` continuam bloqueados com HTTP 403.
10. Remova o ZIP de dentro de `public_html` depois da extração.

## URLs públicas

A navegação usa URLs sem extensão:

- Página inicial: `/`
- Política de privacidade: `/privacidade`
- Política de cookies: `/cookies`
- Recebimento do formulário: `/contato`
- Painel: `/workspace/`

As URLs antigas `/index.php`, `/privacy.php`, `/cookies.php` e `/workspace/index.php` respondem com redirecionamento permanente para os endereços limpos. Essa compatibilidade evita links quebrados e informa aos buscadores qual endereço deve ser indexado.

## Primeiro acesso ao painel

O painel usa o banco MySQL do site (o mesmo do blog). Crie `config/db.php` antes do primeiro acesso (veja "Configurar o blog").

Abra `https://qeai.com.br/workspace/`. Se ainda não existir usuário, informe a chave de `config/bootstrap.key` e crie o **ADM** com nome, **e-mail** e senha (mínimo de 10 caracteres). A chave de instalação não é usada no login normal.

Depois de entrar, em **Usuários e grupos**, o ADM adiciona pessoas e define os **grupos de acesso** (ADM, Desenvolvedor, Escritor, Convidado), podendo personalizar as permissões de cada grupo. O padrão é **convidar por link** (a pessoa define a própria senha); também é possível definir uma senha provisória, que deve ser trocada no primeiro acesso.

**Recuperação de senha:** o link "Esqueci minha senha" envia um e-mail de redefinição usando o SMTP configurado no painel (seção "Configurar o formulário"). Sem SMTP válido, o link não chega — nesse caso, o ADM pode gerar um link de acesso ou uma senha provisória em "Usuários e grupos".

Se já existir uma conta, o painel apresenta o login. A falta do arquivo `installed.lock` não permite refazer a instalação.

## Configurar o formulário

No painel, informe os dados da caixa de e-mail:

- Servidor: `smtp.hostinger.com`.
- Porta: `465`, conexão SSL/TLS.
- Alternativa: porta `587`, STARTTLS.
- Usuário: endereço completo da caixa.
- Senha: senha dessa caixa de e-mail.
- Destinatário: endereço que receberá as mensagens.

Salve e use **Enviar e-mail de teste**. O SMTP real precisa ser validado na hospedagem: os testes locais não enviam mensagens para terceiros. Uma senha vazia ou incorreta impede o envio, mas não deve derrubar a página inicial.

## Configurar o blog

O blog do site usa um banco MySQL próprio (separado do Q One).

1. No hPanel, crie um banco MySQL e um usuário com acesso total a ele. Anote host, nome, usuário e senha.
2. Crie `config/db.php` no servidor usando `config/db.example.php` como modelo (host, porta, database, username, password). Mantenha fora do Git e com permissão restrita.
3. Importe a estrutura e o conteúdo inicial: `app/blog_install.sql`.
   - Pelo phpMyAdmin (Importar) ou
   - Por SSH: `mysql -u USUARIO -p NOME_DO_BANCO < app/blog_install.sql`
4. Confirme que `/blog`, `/blog/{slug}`, `/sitemap.xml` e `/feed` respondem.
5. O `robots.txt` já aponta para `https://qeai.com.br/sitemap.xml`.

Observações:

- `config/db.php` **não** vem no pacote de atualização (assim como as chaves). Crie-o no servidor ou preserve-o entre atualizações.
- Se o banco não estiver configurado, apenas o blog fica indisponível; o site institucional continua funcionando.
- O painel usa um editor visual que grava Markdown, servido localmente em `assets/vendor/toastui/` (sem CDN). Não há etapa de build: os arquivos já vão prontos no pacote.

## Mídia do blog e S3

A biblioteca de mídia tem dois drivers, escolhidos automaticamente:

- **local** (padrão): salva em `media/blog/` no servidor. Serve para desenvolvimento; não é recomendado em produção.
- **s3**: quando bucket, região, access key e secret key estão preenchidos. Os arquivos vão **direto para o S3** por URL assinada e o servidor guarda apenas a referência.

Para configurar, acesse `Painel → Gerenciar blog → Configurações`, preencha os dados do S3 e salve. Em seguida use **Migrar mídia local para o S3** para enviar o que já existia. As chaves ficam cifradas no banco (tabela `blog_settings`).

Requisitos do bucket: leitura pública dos objetos (para SEO das imagens) e CORS liberando o domínio. Use um usuário IAM com permissão mínima (PutObject/GetObject/DeleteObject/ListBucket no bucket).

A pasta `media/` é de execução: **não** entra no pacote de atualização e pode ser ignorada no Git.

## API do blog (para agentes)

Crie tokens em **Gerenciar blog → API** (modo rascunho ou publicador, com escopos). A integração usa `https://qeai.com.br/blog/api` com o cabeçalho `X-Api-Key` (ou `Authorization: Bearer`). A referência completa está em `private-docs/API-BLOG.md`. Não é necessário configurar nada além dos tokens; a API fica pronta para uso.

## Se o erro continuar

- Consulte o log PHP imediatamente após reproduzir o problema. Copie somente a mensagem e o nome do arquivo/linha, sem credenciais.
- `Failed opening required`: o upload está incompleto ou a estrutura das pastas mudou. Confira o caminho indicado e as letras maiúsculas/minúsculas dos nomes; Linux diferencia os dois.
- `Permission denied` ou `session_start`: confira `storage/`, `storage/sessions/` e a propriedade dos arquivos. Esta versão usa um diretório de sessão privado do próprio projeto.
- `Parse error` ou `strict_types ... first statement`: confira integridade dos arquivos e salve PHP em UTF-8 sem BOM. Evite editar PHP no editor visual da hospedagem.
- Mensagens `[QEAI] ... config.json ... inválido`: restaure a configuração válida de um backup. Não apague a configuração para contornar o login.
- Se até `assets/css/site.css` responder 500, consulte também os erros do servidor e regras `.htaccess` herdadas da pasta superior. Erros nessas regras ocorrem antes de executar PHP.
- A nova `.htaccess` remove os overrides `Options` e `ServerSignature`; mantém a proteção dos arquivos internos. Não remova permanentemente as regras de proteção para testar.
- Uma página de manutenção com status 503 indica que o PHP executou, mas encontrou uma falha de configuração ou armazenamento. O detalhe fica no log, sem expor caminhos e chaves aos visitantes.

## Fontes oficiais

- [Hostinger: solucionar erro 500](https://www.hostinger.com/support/1583187-how-to-fix-500-internal-server-error-at-hostinger/)
- [Hostinger: localizar logs de erros](https://support.hostinger.com/en/articles/1583298-where-to-find-your-website-s-error-logs-in-hostinger)
- [Hostinger: configuração de e-mail](https://www.hostinger.com/br/support/1575756-como-encontrar-os-detalhes-de-configuracao-de-e-mail-no-hpanel-hostinger/)

## Verificação local

Execute `C:/xampp/php/php.exe tests/run.php`. A suíte usa uma cópia temporária isolada, cria dados de teste, inicia um servidor local e limpa apenas sua própria cópia ao concluir. Ela não altera a conta real do painel nem envia e-mails externos. O servidor embutido PHP não interpreta `.htaccess`; os bloqueios HTTP devem ser verificados separadamente em Apache/LiteSpeed.
