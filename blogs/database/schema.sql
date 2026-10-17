-- BlogFlow CMS reference schema (default prefix bf_). The installer creates tables from
-- database/migrations/*.sql with your chosen prefix; you do not need to import this file manually.


CREATE TABLE IF NOT EXISTS `bf_users` (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'author',
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  session_version INT UNSIGNED NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  last_login_ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bf_media` (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  filename VARCHAR(190) NOT NULL,
  path VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NULL,
  mime VARCHAR(50) NOT NULL,
  title VARCHAR(255) NULL,
  alt_text VARCHAR(500) NULL,
  caption TEXT NULL,
  credit VARCHAR(255) NULL,
  width INT UNSIGNED NOT NULL DEFAULT 0,
  height INT UNSIGNED NOT NULL DEFAULT 0,
  filesize INT UNSIGNED NOT NULL DEFAULT 0,
  original_filesize INT UNSIGNED NOT NULL DEFAULT 0,
  original_width INT UNSIGNED NOT NULL DEFAULT 0,
  original_height INT UNSIGNED NOT NULL DEFAULT 0,
  original_path VARCHAR(255) NULL,
  variants LONGTEXT NULL,
  checksum CHAR(40) NULL,
  optimized_at DATETIME NULL,
  optimization_version INT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_media_path (path),
  KEY idx_media_created (created_at),
  KEY idx_media_checksum (checksum),
  CONSTRAINT bf_fk_media_user FOREIGN KEY (uploaded_by) REFERENCES `bf_users` (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bf_authors` (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  display_name VARCHAR(120) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  job_title VARCHAR(120) NULL,
  bio TEXT NULL,
  photo_id INT UNSIGNED NULL,
  website_url VARCHAR(500) NULL,
  social LONGTEXT NULL,
  seo_title VARCHAR(255) NULL,
  seo_description TEXT NULL,
  robots VARCHAR(20) NOT NULL DEFAULT 'default',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_authors_slug (slug),
  UNIQUE KEY uq_authors_user (user_id),
  CONSTRAINT bf_fk_authors_user FOREIGN KEY (user_id) REFERENCES `bf_users` (id) ON DELETE SET NULL,
  CONSTRAINT bf_fk_authors_photo FOREIGN KEY (photo_id) REFERENCES `bf_media` (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bf_categories` (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  description TEXT NULL,
  image_id INT UNSIGNED NULL,
  seo_title VARCHAR(255) NULL,
  seo_description TEXT NULL,
  robots VARCHAR(20) NOT NULL DEFAULT 'default',
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_slug (slug),
  KEY idx_categories_parent (parent_id),
  CONSTRAINT bf_fk_categories_parent FOREIGN KEY (parent_id) REFERENCES `bf_categories` (id) ON DELETE SET NULL,
  CONSTRAINT bf_fk_categories_image FOREIGN KEY (image_id) REFERENCES `bf_media` (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bf_tags` (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  description TEXT NULL,
  seo_title VARCHAR(255) NULL,
  seo_description TEXT NULL,
  robots VARCHAR(20) NOT NULL DEFAULT 'default',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tags_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bf_posts` (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  excerpt TEXT NULL,
  content_json LONGTEXT NULL,
  content_html LONGTEXT NULL,
  content_text LONGTEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  category_id INT UNSIGNED NULL,
  author_id INT UNSIGNED NULL,
  featured_image_id INT UNSIGNED NULL,
  featured_image_alt VARCHAR(500) NULL,
  featured_image_caption VARCHAR(500) NULL,
  show_toc TINYINT(1) NULL,
  published_at DATETIME NULL,
  content_modified_at DATETIME NULL,
  seo_title VARCHAR(255) NULL,
  meta_description TEXT NULL,
  focus_keyword VARCHAR(190) NULL,
  secondary_keywords VARCHAR(500) NULL,
  canonical_url VARCHAR(500) NULL,
  robots_index VARCHAR(10) NOT NULL DEFAULT 'default',
  robots_follow VARCHAR(10) NOT NULL DEFAULT 'default',
  robots_advanced VARCHAR(255) NULL,
  og_title VARCHAR(255) NULL,
  og_description TEXT NULL,
  og_image_id INT UNSIGNED NULL,
  twitter_card VARCHAR(30) NOT NULL DEFAULT 'default',
  schema_type VARCHAR(30) NOT NULL DEFAULT 'default',
  schema_enabled TINYINT(1) NOT NULL DEFAULT 1,
  faq LONGTEXT NULL,
  word_count INT UNSIGNED NOT NULL DEFAULT 0,
  reading_time INT UNSIGNED NOT NULL DEFAULT 0,
  preview_token CHAR(32) NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_posts_slug (slug),
  KEY idx_posts_public (deleted_at, status, published_at),
  KEY idx_posts_category (category_id),
  KEY idx_posts_author (author_id),
  KEY idx_posts_featured (is_featured),
  KEY idx_posts_updated (updated_at),
  CONSTRAINT bf_fk_posts_category FOREIGN KEY (category_id) REFERENCES `bf_categories` (id) ON DELETE SET NULL,
  CONSTRAINT bf_fk_posts_author FOREIGN KEY (author_id) REFERENCES `bf_authors` (id) ON DELETE SET NULL,
  CONSTRAINT bf_fk_posts_image FOREIGN KEY (featured_image_id) REFERENCES `bf_media` (id) ON DELETE SET NULL,
  CONSTRAINT bf_fk_posts_og_image FOREIGN KEY (og_image_id) REFERENCES `bf_media` (id) ON DELETE SET NULL,
  CONSTRAINT bf_fk_posts_created_by FOREIGN KEY (created_by) REFERENCES `bf_users` (id) ON DELETE SET NULL,
  CONSTRAINT bf_fk_posts_updated_by FOREIGN KEY (updated_by) REFERENCES `bf_users` (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bf_post_tags` (
  post_id INT UNSIGNED NOT NULL,
  tag_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (post_id, tag_id),
  KEY idx_post_tags_tag (tag_id),
  CONSTRAINT bf_fk_post_tags_post FOREIGN KEY (post_id) REFERENCES `bf_posts` (id) ON DELETE CASCADE,
  CONSTRAINT bf_fk_post_tags_tag FOREIGN KEY (tag_id) REFERENCES `bf_tags` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bf_post_related` (
  post_id INT UNSIGNED NOT NULL,
  related_id INT UNSIGNED NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (post_id, related_id),
  KEY idx_post_related_related (related_id),
  CONSTRAINT bf_fk_post_related_post FOREIGN KEY (post_id) REFERENCES `bf_posts` (id) ON DELETE CASCADE,
  CONSTRAINT bf_fk_post_related_related FOREIGN KEY (related_id) REFERENCES `bf_posts` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bf_post_media` (
  post_id INT UNSIGNED NOT NULL,
  media_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (post_id, media_id),
  KEY idx_post_media_media (media_id),
  CONSTRAINT bf_fk_post_media_post FOREIGN KEY (post_id) REFERENCES `bf_posts` (id) ON DELETE CASCADE,
  CONSTRAINT bf_fk_post_media_media FOREIGN KEY (media_id) REFERENCES `bf_media` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bf_post_revisions` (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  post_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NULL,
  kind VARCHAR(20) NOT NULL DEFAULT 'revision',
  title VARCHAR(255) NOT NULL,
  excerpt TEXT NULL,
  content_json LONGTEXT NULL,
  content_html LONGTEXT NULL,
  meta LONGTEXT NULL,
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_revisions_post (post_id, kind, created_at),
  CONSTRAINT bf_fk_revisions_post FOREIGN KEY (post_id) REFERENCES `bf_posts` (id) ON DELETE CASCADE,
  CONSTRAINT bf_fk_revisions_user FOREIGN KEY (user_id) REFERENCES `bf_users` (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bf_menus` (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  location VARCHAR(30) NOT NULL,
  name VARCHAR(120) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_menus_location (location)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bf_menu_items` (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  menu_id INT UNSIGNED NOT NULL,
  parent_id INT UNSIGNED NULL,
  label VARCHAR(120) NOT NULL,
  link_type VARCHAR(20) NOT NULL DEFAULT 'custom',
  target_id INT UNSIGNED NULL,
  url VARCHAR(1000) NULL,
  new_tab TINYINT(1) NOT NULL DEFAULT 0,
  is_visible TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_menu_items_menu (menu_id, sort_order),
  CONSTRAINT bf_fk_menu_items_menu FOREIGN KEY (menu_id) REFERENCES `bf_menus` (id) ON DELETE CASCADE,
  CONSTRAINT bf_fk_menu_items_parent FOREIGN KEY (parent_id) REFERENCES `bf_menu_items` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bf_settings` (
  name VARCHAR(190) NOT NULL,
  value LONGTEXT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bf_seo_audit_results` (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  entity_type VARCHAR(20) NOT NULL,
  entity_id INT UNSIGNED NULL,
  check_key VARCHAR(60) NOT NULL,
  severity VARCHAR(20) NOT NULL,
  message VARCHAR(500) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_audit_severity (severity),
  KEY idx_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bf_redirects` (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_path VARCHAR(500) NOT NULL,
  source_hash CHAR(40) NOT NULL,
  destination VARCHAR(1000) NOT NULL,
  status_code SMALLINT UNSIGNED NOT NULL DEFAULT 301,
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  is_auto TINYINT(1) NOT NULL DEFAULT 0,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  last_hit_at DATETIME NULL,
  note VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_redirects_source (source_hash),
  CONSTRAINT bf_fk_redirects_user FOREIGN KEY (created_by) REFERENCES `bf_users` (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bf_activity_logs` (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,
  entity_type VARCHAR(30) NULL,
  entity_id INT UNSIGNED NULL,
  summary VARCHAR(500) NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_activity_created (created_at),
  KEY idx_activity_user (user_id),
  CONSTRAINT bf_fk_activity_user FOREIGN KEY (user_id) REFERENCES `bf_users` (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bf_password_reset_tokens` (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reset_token (token_hash),
  CONSTRAINT bf_fk_reset_user FOREIGN KEY (user_id) REFERENCES `bf_users` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bf_login_attempts` (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email_hash CHAR(64) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_login_ip (ip, created_at),
  KEY idx_login_email (email_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bf_link_checks` (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  url_hash CHAR(40) NOT NULL,
  url VARCHAR(1000) NOT NULL,
  status_code SMALLINT UNSIGNED NULL,
  error VARCHAR(255) NULL,
  checked_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_link_checks_url (url_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bf_migrations` (version VARCHAR(100) NOT NULL, applied_at DATETIME NOT NULL, PRIMARY KEY (version)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
