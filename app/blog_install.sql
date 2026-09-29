-- QEAI Blog — estrutura e dados iniciais
-- Idempotente: pode ser executado mais de uma vez sem duplicar conteúdo.
-- Uso: mysql -u USUARIO -p NOME_DO_BANCO < app/blog_install.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS blog_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL,
  description VARCHAR(500) NOT NULL DEFAULT '',
  seo_title VARCHAR(255) NOT NULL DEFAULT '',
  seo_description VARCHAR(320) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_blog_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_tags (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(140) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_blog_tags_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_media (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  driver VARCHAR(20) NOT NULL DEFAULT 'local',
  bucket VARCHAR(160) NOT NULL DEFAULT '',
  path VARCHAR(600) NOT NULL,
  url VARCHAR(1000) NOT NULL DEFAULT '',
  mime VARCHAR(120) NOT NULL DEFAULT '',
  width INT UNSIGNED NOT NULL DEFAULT 0,
  height INT UNSIGNED NOT NULL DEFAULT 0,
  size BIGINT UNSIGNED NOT NULL DEFAULT 0,
  alt VARCHAR(300) NOT NULL DEFAULT '',
  title VARCHAR(300) NOT NULL DEFAULT '',
  uploaded_by VARCHAR(120) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_blog_media_driver (driver)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_posts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(180) NOT NULL,
  title VARCHAR(255) NOT NULL,
  excerpt VARCHAR(500) NOT NULL DEFAULT '',
  body_md MEDIUMTEXT NOT NULL,
  status ENUM('draft','scheduled','published','trash') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  author_name VARCHAR(120) NOT NULL DEFAULT '',
  seo_title VARCHAR(255) NOT NULL DEFAULT '',
  seo_description VARCHAR(320) NOT NULL DEFAULT '',
  canonical_url VARCHAR(500) NOT NULL DEFAULT '',
  cover_media_id INT UNSIGNED NULL,
  category_id INT UNSIGNED NULL,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  reading_time SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  source VARCHAR(20) NOT NULL DEFAULT 'panel',
  api_token_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_blog_posts_slug (slug),
  KEY idx_blog_posts_status_date (status, published_at),
  KEY idx_blog_posts_cover (cover_media_id),
  KEY idx_blog_posts_category (category_id),
  CONSTRAINT fk_blog_posts_category FOREIGN KEY (category_id) REFERENCES blog_categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_post_tags (
  post_id INT UNSIGNED NOT NULL,
  tag_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (post_id, tag_id),
  KEY idx_blog_post_tags_tag (tag_id),
  CONSTRAINT fk_blog_post_tags_post FOREIGN KEY (post_id) REFERENCES blog_posts (id) ON DELETE CASCADE,
  CONSTRAINT fk_blog_post_tags_tag FOREIGN KEY (tag_id) REFERENCES blog_tags (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_revisions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  post_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL DEFAULT '',
  body_md MEDIUMTEXT NOT NULL,
  changed_by VARCHAR(120) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_blog_revisions_post (post_id),
  CONSTRAINT fk_blog_revisions_post FOREIGN KEY (post_id) REFERENCES blog_posts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_api_tokens (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  mode ENUM('draft','publisher') NOT NULL DEFAULT 'draft',
  scopes LONGTEXT NOT NULL,
  allowed_ips LONGTEXT NULL,
  last_used_at DATETIME NULL,
  expires_at DATETIME NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_blog_api_tokens_hash (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_type VARCHAR(20) NOT NULL DEFAULT 'user',
  actor_id INT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(40) NOT NULL DEFAULT '',
  entity_id INT UNSIGNED NULL,
  ip VARCHAR(45) NOT NULL DEFAULT '',
  user_agent VARCHAR(300) NOT NULL DEFAULT '',
  meta LONGTEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_blog_audit_created (created_at),
  KEY idx_blog_audit_actor (actor_type, actor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_settings (
  name VARCHAR(120) NOT NULL,
  value MEDIUMTEXT NOT NULL,
  encrypted TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conteúdo inicial -----------------------------------------------------------

INSERT INTO blog_categories (name, slug, description) VALUES
  ('Marketing Digital', 'marketing-digital', 'Estratégias para marcas crescerem com consistência.'),
  ('Conteúdo', 'conteudo', 'Criação, planejamento e produção de conteúdo.'),
  ('Inteligência Artificial', 'inteligencia-artificial', 'Como a IA apoia a operação de marketing.')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO blog_tags (name, slug) VALUES
  ('Estratégia', 'estrategia'),
  ('Redes sociais', 'redes-sociais'),
  ('Automação', 'automacao'),
  ('SEO', 'seo'),
  ('IA', 'ia')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO blog_posts
  (slug, title, excerpt, body_md, status, published_at, author_name, seo_title, seo_description, reading_time)
VALUES
  (
    'o-que-e-uma-operacao-de-marketing-conectada',
    'O que é uma operação de marketing conectada',
    'Entenda por que estratégia, conteúdo e tecnologia precisam andar juntas para uma marca crescer com consistência.',
    '## Mais do que publicar\n\nUma marca forte não nasce de posts isolados. Ela nasce de uma **operação conectada**, em que estratégia, criação e mensuração conversam.\n\n- Definir objetivos claros\n- Organizar o calendário editorial\n- Medir o que importa\n\n> Conteúdo sem direção vira ruído. Direção sem conteúdo vira intenção vazia.\n\nQuando cada peça conversa com a próxima, a marca ganha reconhecimento e confiança.',
    'published', '2026-08-12 09:00:00', 'QEAI',
    'O que é uma operação de marketing conectada — QEAI',
    'Estratégia, conteúdo e tecnologia juntos: o que significa ter uma operação de marketing conectada.', 3
  ),
  (
    'como-a-ia-pode-apoiar-o-marketing-sem-perder-a-identidade',
    'Como a IA pode apoiar o marketing sem perder a identidade',
    'A inteligência artificial acelera a produção — desde que pessoas continuem no comando da estratégia.',
    '## Acelerar sem apagar a marca\n\nA IA é excelente para **organizar, sugerir e escalar**. O que ela não substitui é a intenção.\n\n1. Pessoas definem a direção\n2. A IA ajuda a produzir\n3. Pessoas revisam e aprovam\n\n```text\nEstratégia -> Produção assistida -> Revisão humana -> Publicação\n```\n\nO resultado tem a sua identidade porque a decisão continua humana.',
    'published', '2026-08-28 09:00:00', 'QEAI',
    'IA no marketing sem perder a identidade — QEAI',
    'Como usar inteligência artificial para acelerar a produção de conteúdo mantendo a identidade da marca.', 2
  ),
  (
    'seo-na-pratica-conteudo-que-e-encontrado',
    'SEO na prática: conteúdo que é encontrado',
    'Boas práticas de SEO para que o seu conteúdo seja encontrado pelas pessoas certas.',
    '## Ser encontrado é parte da estratégia\n\nCriar conteúdo bom é metade do caminho. A outra metade é garantir que ele seja **encontrado**.\n\n- URLs limpas e descritivas\n- Títulos e descrições claros\n- Imagens com texto alternativo\n- Conteúdo útil para pessoas reais\n\nEste blog já nasce com URLs limpas, dados estruturados, sitemap e feed. É tecnologia trabalhando a favor da descoberta.',
    'published', '2026-09-10 09:00:00', 'QEAI',
    'SEO na prática: conteúdo que é encontrado — QEAI',
    'Boas práticas de SEO para que o seu conteúdo seja encontrado pelas pessoas certas.', 2
  )
ON DUPLICATE KEY UPDATE title = VALUES(title), excerpt = VALUES(excerpt), body_md = VALUES(body_md);

UPDATE blog_posts p JOIN blog_categories c ON c.slug = 'marketing-digital'
  SET p.category_id = c.id WHERE p.slug = 'o-que-e-uma-operacao-de-marketing-conectada';
UPDATE blog_posts p JOIN blog_categories c ON c.slug = 'inteligencia-artificial'
  SET p.category_id = c.id WHERE p.slug = 'como-a-ia-pode-apoiar-o-marketing-sem-perder-a-identidade';
UPDATE blog_posts p JOIN blog_categories c ON c.slug = 'conteudo'
  SET p.category_id = c.id WHERE p.slug = 'seo-na-pratica-conteudo-que-e-encontrado';

INSERT IGNORE INTO blog_post_tags (post_id, tag_id)
SELECT p.id, t.id FROM blog_posts p JOIN blog_tags t
  WHERE p.slug = 'o-que-e-uma-operacao-de-marketing-conectada' AND t.slug IN ('estrategia','redes-sociais');
INSERT IGNORE INTO blog_post_tags (post_id, tag_id)
SELECT p.id, t.id FROM blog_posts p JOIN blog_tags t
  WHERE p.slug = 'como-a-ia-pode-apoiar-o-marketing-sem-perder-a-identidade' AND t.slug IN ('ia','automacao');
INSERT IGNORE INTO blog_post_tags (post_id, tag_id)
SELECT p.id, t.id FROM blog_posts p JOIN blog_tags t
  WHERE p.slug = 'seo-na-pratica-conteudo-que-e-encontrado' AND t.slug IN ('seo','conteudo','estrategia');

-- Usuários e grupos de acesso do painel -------------------------------------

CREATE TABLE IF NOT EXISTS roles (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL,
  permissions LONGTEXT NOT NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL DEFAULT '',
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role_id INT UNSIGNED NULL,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at DATETIME NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role_id),
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_tokens (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  purpose ENUM('reset','invite') NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_tokens_hash (token_hash),
  KEY idx_user_tokens_user (user_id, purpose),
  CONSTRAINT fk_user_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Grupos padrão. "permissions" com "*" significa acesso total.
-- O nome é atualizado ao reexecutar, mas as permissões personalizadas são preservadas.
INSERT INTO roles (name, slug, permissions, is_system) VALUES
  ('ADM', 'adm', '["*"]', 1),
  ('Desenvolvedor', 'desenvolvedor', '["site.settings","blog.view","blog.create","blog.edit.any","blog.publish","blog.delete","blog.media","blog.tokens","blog.settings","blog.audit"]', 1),
  ('Escritor', 'escritor', '["blog.view","blog.create","blog.edit.any","blog.publish","blog.media"]', 1),
  ('Convidado', 'convidado', '["blog.view"]', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);
