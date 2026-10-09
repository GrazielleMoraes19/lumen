-- Migração segura para instalações LUMEN já existentes.
-- Execute uma vez no banco lumen para habilitar “Lembrar de mim”.
CREATE TABLE IF NOT EXISTS remember_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  selector CHAR(24) NOT NULL UNIQUE,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_remember_expiration (expires_at)
) ENGINE=InnoDB;
