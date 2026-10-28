# Instruções do projeto

Leia antes de alterar o site:

- `private-docs/UI-STANDARDS.md` — padrão visual, componentes, motion, segurança e organização.
- `private-docs/ARCHITECTURE.md` — arquitetura, rotas e persistência.
- `private-docs/DEPLOY-PADRAO.md` — fluxo oficial: Git, hPanel, painel e banco.
- `private-docs/INSTALL.md` — publicação e atualização.
- `private-docs/DEPLOY-GIT.md` — deploy via Git/SSH do site e do Q One, ambientes e rollback.

Regras essenciais:

1. Não adicionar Bootstrap ou frameworks sem decisão explícita; use o design system próprio.
2. Não publicar textos de teste, demonstração, debug ou instruções internas.
3. Toda regra de negócio e autorização deve ficar no backend.
4. Não expor segredos, configurações ou dados de clientes no front-end.
5. Preserve acessibilidade, responsividade e `prefers-reduced-motion`.
6. Valide com `php -l` e com as verificações internas antes de publicar.
7. Nunca versione `config/app.key`, `config/bootstrap.key`, `config/db.php`, `storage/config.json` ou `media/`.
8. Em produção, publique via Git na branch `main`; não edite arquivos diretamente em `public_html`.
9. Nunca use `git clean -fdx` em produção; os arquivos de configuração e runtime não são versionados.
