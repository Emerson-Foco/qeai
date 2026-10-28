QEAI - site institucional
========================

PHP 8.2+ (Hostinger: PHP 8.3). O site não tem etapa de build.

Publicação oficial: Git + SSH na branch `main`.
Guia completo: private-docs/DEPLOY-GIT.md
Publicação/atualização e contingência: private-docs/INSTALL.md
Arquitetura: private-docs/ARCHITECTURE.md
Padrão visual e segurança: private-docs/UI-STANDARDS.md

Prévia local: http://localhost/qeai/
Painel local: http://localhost/qeai/workspace/

O banco do blog é configurado na primeira tela do painel, como um instalador:
informe host, banco, usuário e senha; o schema é importado sem conteúdo de
exemplo.

URLs públicas: /, /termos, /privacidade e /cookies. Os endereços antigos com .php
são redirecionados permanentemente para as URLs limpas pelo arquivo .htaccess.

Nunca versionar config/app.key, config/bootstrap.key, config/db.php,
storage/config.json ou media/. O pacote dist/ é apenas contingência e não
substitui o deploy via Git.
