-- LUMEN — Área do apoiador / profissional
-- Migração para instalações existentes. Execute depois de `database.sql`.
-- Todas as definições são idempotentes (IF NOT EXISTS / WHERE NOT EXISTS).
--
-- Três tipos de perfil são suportados em `supporter_profiles.supporter_type`:
--   professional = profissional autônomo
--   institution  = instituição / ONG
--   volunteer    = voluntário(a)
-- Campos obrigatórios e permissões variam por tipo, sem duplicar o sistema.

USE lumen;

-- Dados profissionais e situação da verificação de cada apoiador.
CREATE TABLE IF NOT EXISTS supporter_profiles (
  user_id INT UNSIGNED NOT NULL PRIMARY KEY,
  supporter_type VARCHAR(20) NOT NULL DEFAULT 'professional',
  display_name VARCHAR(160) NULL,
  document_type VARCHAR(10) NULL,
  document_number VARCHAR(40) NULL,
  council_name VARCHAR(60) NULL,
  council_number VARCHAR(40) NULL,
  council_state CHAR(2) NULL,
  legal_name VARCHAR(190) NULL,
  responsible_name VARCHAR(160) NULL,
  responsible_document VARCHAR(40) NULL,
  team_size SMALLINT UNSIGNED NULL,
  bio TEXT NULL,
  approach VARCHAR(255) NULL,
  city VARCHAR(120) NULL,
  state CHAR(2) NULL,
  service_mode VARCHAR(20) NOT NULL DEFAULT 'online',
  languages VARCHAR(160) NULL,
  max_active_cases TINYINT UNSIGNED NOT NULL DEFAULT 5,
  verification_status VARCHAR(20) NOT NULL DEFAULT 'pending',
  verification_notes VARCHAR(500) NULL,
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  submitted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_supporter_profile_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_supporter_profile_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_supporter_type (supporter_type),
  INDEX idx_supporter_verification (verification_status)
) ENGINE=InnoDB;

-- Especialidades e áreas de atuação (item 28).
CREATE TABLE IF NOT EXISTS supporter_specialties (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  supporter_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  notes VARCHAR(190) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_specialty_supporter FOREIGN KEY (supporter_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_specialty_supporter (supporter_id)
) ENGINE=InnoDB;

-- Disponibilidade semanal (itens 29 e 36).
CREATE TABLE IF NOT EXISTS supporter_availability (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  supporter_id INT UNSIGNED NOT NULL,
  weekday TINYINT UNSIGNED NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  slot_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  mode VARCHAR(20) NOT NULL DEFAULT 'online',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_availability_supporter FOREIGN KEY (supporter_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_availability_supporter (supporter_id, weekday)
) ENGINE=InnoDB;

-- Bloqueios pontuais de agenda (férias, folgas, compromissos).
CREATE TABLE IF NOT EXISTS supporter_time_off (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  supporter_id INT UNSIGNED NOT NULL,
  off_date DATE NOT NULL,
  start_time TIME NULL,
  end_time TIME NULL,
  reason VARCHAR(190) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_timeoff_supporter FOREIGN KEY (supporter_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_timeoff_supporter (supporter_id, off_date)
) ENGINE=InnoDB;

-- Locais de atendimento presencial (item 30).
CREATE TABLE IF NOT EXISTS supporter_locations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  supporter_id INT UNSIGNED NOT NULL,
  label VARCHAR(120) NOT NULL,
  address VARCHAR(255) NULL,
  city VARCHAR(120) NULL,
  state CHAR(2) NULL,
  cep VARCHAR(12) NULL,
  notes VARCHAR(190) NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_location_supporter FOREIGN KEY (supporter_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_location_supporter (supporter_id)
) ENGINE=InnoDB;

-- Documentos enviados para verificação (item 6). Apenas a equipe revisora vê o conteúdo.
CREATE TABLE IF NOT EXISTS supporter_documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  supporter_id INT UNSIGNED NOT NULL,
  kind VARCHAR(40) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  review_notes VARCHAR(500) NULL,
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_document_supporter FOREIGN KEY (supporter_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_document_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_document_supporter (supporter_id, kind)
) ENGINE=InnoDB;

-- Vínculo entre uma solicitação da beneficiária e o apoiador autorizado (itens 15, 16).
CREATE TABLE IF NOT EXISTS support_assignments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id INT UNSIGNED NOT NULL,
  supporter_id INT UNSIGNED NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'accepted',
  decline_reason VARCHAR(300) NULL,
  accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  decided_at DATETIME NULL,
  closed_at DATETIME NULL,
  closing_summary TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_assignment_request FOREIGN KEY (request_id) REFERENCES support_requests(id) ON DELETE CASCADE,
  CONSTRAINT fk_assignment_supporter FOREIGN KEY (supporter_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_assignment (request_id, supporter_id),
  INDEX idx_assignment_supporter (supporter_id, status)
) ENGINE=InnoDB;

-- Histórico do atendimento (item 18 e 19). Preservado mesmo após o encerramento.
CREATE TABLE IF NOT EXISTS support_assignment_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT UNSIGNED NOT NULL,
  actor_id INT UNSIGNED NULL,
  old_status VARCHAR(20) NULL,
  new_status VARCHAR(20) NOT NULL,
  note VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_assignment_history_assignment FOREIGN KEY (assignment_id) REFERENCES support_assignments(id) ON DELETE CASCADE,
  CONSTRAINT fk_assignment_history_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_assignment_history (assignment_id, created_at)
) ENGINE=InnoDB;

-- Agenda de atendimentos e retornos (itens 17 e 23).
CREATE TABLE IF NOT EXISTS support_appointments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT UNSIGNED NOT NULL,
  created_by INT UNSIGNED NULL,
  kind VARCHAR(20) NOT NULL DEFAULT 'first',
  scheduled_at DATETIME NOT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  mode VARCHAR(20) NOT NULL DEFAULT 'online',
  location VARCHAR(190) NULL,
  notes VARCHAR(500) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'scheduled',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_appointment_assignment FOREIGN KEY (assignment_id) REFERENCES support_assignments(id) ON DELETE CASCADE,
  CONSTRAINT fk_appointment_author FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_appointment_assignment (assignment_id, scheduled_at),
  INDEX idx_appointment_date (scheduled_at)
) ENGINE=InnoDB;

-- Conversa entre apoiador e beneficiária, sempre ligada a um atendimento autorizado (itens 20 a 22).
CREATE TABLE IF NOT EXISTS support_conversations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assignment_id INT UNSIGNED NOT NULL UNIQUE,
  beneficiary_id INT UNSIGNED NOT NULL,
  supporter_id INT UNSIGNED NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'open',
  last_message_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_conversation_assignment FOREIGN KEY (assignment_id) REFERENCES support_assignments(id) ON DELETE CASCADE,
  CONSTRAINT fk_conversation_beneficiary FOREIGN KEY (beneficiary_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_conversation_supporter FOREIGN KEY (supporter_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS support_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT UNSIGNED NOT NULL,
  sender_id INT UNSIGNED NULL,
  sender_role VARCHAR(30) NOT NULL,
  body TEXT NOT NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_message_conversation FOREIGN KEY (conversation_id) REFERENCES support_conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_message_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_message_conversation (conversation_id, created_at)
) ENGINE=InnoDB;

-- Serviços oferecidos pelo próprio apoiador (itens 32 a 35).
CREATE TABLE IF NOT EXISTS supporter_services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  supporter_id INT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  category VARCHAR(100) NOT NULL,
  description TEXT NULL,
  mode VARCHAR(20) NOT NULL DEFAULT 'online',
  duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  price_info VARCHAR(120) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_supporter_service_supporter FOREIGN KEY (supporter_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_supporter_service (supporter_id, status)
) ENGINE=InnoDB;

-- Canal para reportar situações de risco ou abuso no atendimento (item 39).
CREATE TABLE IF NOT EXISTS supporter_risk_reports (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  supporter_id INT UNSIGNED NOT NULL,
  assignment_id INT UNSIGNED NULL,
  category VARCHAR(80) NOT NULL,
  description TEXT NOT NULL,
  urgency VARCHAR(20) NOT NULL DEFAULT 'attention',
  status VARCHAR(20) NOT NULL DEFAULT 'new',
  reviewed_by INT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  review_notes VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_risk_supporter FOREIGN KEY (supporter_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_risk_assignment FOREIGN KEY (assignment_id) REFERENCES support_assignments(id) ON DELETE SET NULL,
  CONSTRAINT fk_risk_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_risk_status (status, created_at)
) ENGINE=InnoDB;

-- Apoiadores de demonstração (senha Lumen@123), um de cada tipo de perfil.
INSERT INTO users (role, name, cpf, rg, address, cep, phone, email, password_hash)
SELECT 'supporter', 'Dra. Marina Prado', '222.222.222-22', '22.222.222-2', 'Rua Clínica, 45 - São Paulo/SP', '01310-000', '(11) 98888-1000', 'apoiador@lumen.org', '$2y$12$P2KcfoWcJOYScK7w2pY0DuXK0EfIBgJFM61GlOK6LsANKRqLsIygK'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'apoiador@lumen.org');

INSERT INTO users (role, name, cpf, rg, address, cep, phone, email, password_hash)
SELECT 'supporter', 'Casa Acolher — ONG', '333.333.333-33', '33.333.333-3', 'Av. da Esperança, 80 - São Paulo/SP', '02000-000', '(11) 98888-2000', 'ong@lumen.org', '$2y$12$P2KcfoWcJOYScK7w2pY0DuXK0EfIBgJFM61GlOK6LsANKRqLsIygK'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'ong@lumen.org');

INSERT INTO users (role, name, cpf, rg, address, cep, phone, email, password_hash)
SELECT 'supporter', 'Joana Ribeiro (voluntária)', '444.444.444-44', '44.444.444-4', 'Rua do Apoio, 12 - São Paulo/SP', '03000-000', '(11) 98888-3000', 'voluntaria@lumen.org', '$2y$12$P2KcfoWcJOYScK7w2pY0DuXK0EfIBgJFM61GlOK6LsANKRqLsIygK'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'voluntaria@lumen.org');

-- Perfil: profissional autônomo (aprovado, com especialidades, agenda e serviços).
INSERT INTO supporter_profiles (user_id, supporter_type, display_name, document_type, document_number, council_name, council_number, council_state, bio, approach, city, state, service_mode, languages, max_active_cases, verification_status, submitted_at, reviewed_at)
SELECT u.id, 'professional', 'Dra. Marina Prado — Psicologia', 'cpf', '222.222.222-22', 'CRP', '06/123456', 'SP',
       'Psicóloga com 12 anos de experiência em acolhimento de mulheres em situação de violência. Atendimento presencial e online.',
       'Escuta ativa, terapia breve e encaminhamento para a rede de proteção.', 'São Paulo', 'SP', 'hibrido', 'Português, Espanhol', 6, 'approved', NOW(), NOW()
FROM users u WHERE u.email = 'apoiador@lumen.org'
AND NOT EXISTS (SELECT 1 FROM supporter_profiles p WHERE p.user_id = u.id);

-- Perfil: instituição / ONG (aprovada).
INSERT INTO supporter_profiles (user_id, supporter_type, display_name, document_type, document_number, legal_name, responsible_name, responsible_document, team_size, bio, approach, city, state, service_mode, languages, max_active_cases, verification_status, submitted_at, reviewed_at)
SELECT u.id, 'institution', 'Casa Acolher — ONG', 'cnpj', '12.345.678/0001-90', 'Casa Acolher Associação', 'Regina Alves', '555.555.555-55', 14,
       'Organização sem fins lucrativos que oferece acolhimento, apoio jurídico e assistência social a mulheres e famílias.',
       'Acolhimento multidisciplinar com assistente social, advogada e psicólogas.', 'São Paulo', 'SP', 'presencial', 'Português', 20, 'approved', NOW(), NOW()
FROM users u WHERE u.email = 'ong@lumen.org'
AND NOT EXISTS (SELECT 1 FROM supporter_profiles p WHERE p.user_id = u.id);

-- Perfil: voluntário(a) (cadastro ainda em análise, para demonstrar a tela de aprovação).
INSERT INTO supporter_profiles (user_id, supporter_type, display_name, document_type, document_number, bio, approach, city, state, service_mode, languages, max_active_cases, verification_status, submitted_at)
SELECT u.id, 'volunteer', 'Joana Ribeiro', 'cpf', '444.444.444-44',
       'Voluntária na rede de apoio desde 2021. Acompanho beneficiárias em orientações sobre a rede de proteção e serviços públicos.',
       'Acolhimento por escuta, orientação sobre direitos e acompanhamento de encaminhamentos.', 'São Paulo', 'SP', 'online', 'Português', 3, 'pending', NOW()
FROM users u WHERE u.email = 'voluntaria@lumen.org'
AND NOT EXISTS (SELECT 1 FROM supporter_profiles p WHERE p.user_id = u.id);

INSERT INTO supporter_specialties (supporter_id, name, notes)
SELECT u.id, 'Violência doméstica e familiar', 'Atendimento especializado'
FROM users u WHERE u.email = 'apoiador@lumen.org'
AND NOT EXISTS (SELECT 1 FROM supporter_specialties s WHERE s.supporter_id = u.id AND s.name = 'Violência doméstica e familiar');

INSERT INTO supporter_specialties (supporter_id, name, notes)
SELECT u.id, 'Saúde mental', 'Ansiedade, trauma e luto'
FROM users u WHERE u.email = 'apoiador@lumen.org'
AND NOT EXISTS (SELECT 1 FROM supporter_specialties s WHERE s.supporter_id = u.id AND s.name = 'Saúde mental');

INSERT INTO supporter_specialties (supporter_id, name, notes)
SELECT u.id, 'Assistência social', 'Benefícios e rede pública'
FROM users u WHERE u.email = 'ong@lumen.org'
AND NOT EXISTS (SELECT 1 FROM supporter_specialties s WHERE s.supporter_id = u.id AND s.name = 'Assistência social');

INSERT INTO supporter_specialties (supporter_id, name, notes)
SELECT u.id, 'Apoio jurídico', 'Medidas protetivas'
FROM users u WHERE u.email = 'ong@lumen.org'
AND NOT EXISTS (SELECT 1 FROM supporter_specialties s WHERE s.supporter_id = u.id AND s.name = 'Apoio jurídico');

INSERT INTO supporter_availability (supporter_id, weekday, start_time, end_time, slot_minutes, mode)
SELECT u.id, 1, '09:00:00', '12:00:00', 60, 'online'
FROM users u WHERE u.email = 'apoiador@lumen.org'
AND NOT EXISTS (SELECT 1 FROM supporter_availability a WHERE a.supporter_id = u.id AND a.weekday = 1);

INSERT INTO supporter_availability (supporter_id, weekday, start_time, end_time, slot_minutes, mode)
SELECT u.id, 3, '14:00:00', '18:00:00', 60, 'hibrido'
FROM users u WHERE u.email = 'apoiador@lumen.org'
AND NOT EXISTS (SELECT 1 FROM supporter_availability a WHERE a.supporter_id = u.id AND a.weekday = 3);

INSERT INTO supporter_availability (supporter_id, weekday, start_time, end_time, slot_minutes, mode)
SELECT u.id, 2, '08:00:00', '17:00:00', 30, 'presencial'
FROM users u WHERE u.email = 'ong@lumen.org'
AND NOT EXISTS (SELECT 1 FROM supporter_availability a WHERE a.supporter_id = u.id AND a.weekday = 2);

INSERT INTO supporter_locations (supporter_id, label, address, city, state, cep, notes, is_primary)
SELECT u.id, 'Consultório — Vila Mariana', 'Rua Clínica, 45, sala 12', 'São Paulo', 'SP', '01310-000', 'Recepção com espera reservada.', 1
FROM users u WHERE u.email = 'apoiador@lumen.org'
AND NOT EXISTS (SELECT 1 FROM supporter_locations l WHERE l.supporter_id = u.id AND l.label = 'Consultório — Vila Mariana');

INSERT INTO supporter_locations (supporter_id, label, address, city, state, cep, notes, is_primary)
SELECT u.id, 'Casa Acolher — sede', 'Av. da Esperança, 80', 'São Paulo', 'SP', '02000-000', 'Entrada com identificação na portaria.', 1
FROM users u WHERE u.email = 'ong@lumen.org'
AND NOT EXISTS (SELECT 1 FROM supporter_locations l WHERE l.supporter_id = u.id AND l.label = 'Casa Acolher — sede');

INSERT INTO supporter_services (supporter_id, name, category, description, mode, duration_minutes, price_info, status)
SELECT u.id, 'Atendimento psicológico individual', 'Saúde mental', 'Sessão de escuta e apoio psicológico com foco em acolhimento e segurança.', 'hibrido', 60, 'Gratuito na rede Lumen', 'active'
FROM users u WHERE u.email = 'apoiador@lumen.org'
AND NOT EXISTS (SELECT 1 FROM supporter_services s WHERE s.supporter_id = u.id AND s.name = 'Atendimento psicológico individual');

INSERT INTO supporter_services (supporter_id, name, category, description, mode, duration_minutes, price_info, status)
SELECT u.id, 'Orientação sobre medidas protetivas', 'Apoio jurídico', 'Orientação inicial sobre medidas protetivas e encaminhamento à Defensoria.', 'online', 45, 'Gratuito', 'active'
FROM users u WHERE u.email = 'ong@lumen.org'
AND NOT EXISTS (SELECT 1 FROM supporter_services s WHERE s.supporter_id = u.id AND s.name = 'Orientação sobre medidas protetivas');

INSERT INTO supporter_services (supporter_id, name, category, description, mode, duration_minutes, price_info, status)
SELECT u.id, 'Acompanhamento de encaminhamentos', 'Assistência social', 'Apoio para acessar benefícios, abrigos e serviços públicos.', 'presencial', 60, 'Gratuito', 'active'
FROM users u WHERE u.email = 'ong@lumen.org'
AND NOT EXISTS (SELECT 1 FROM supporter_services s WHERE s.supporter_id = u.id AND s.name = 'Acompanhamento de encaminhamentos');

-- Documentos de demonstração (arquivo fictício em storage/uploads) já aprovados,
-- para que os apoiadores verificados apareçam com a verificação completa.
INSERT INTO supporter_documents (supporter_id, kind, original_name, stored_name, mime_type, size_bytes, status, reviewed_at)
SELECT u.id, 'identity', 'documento-identidade-demo.pdf', 'demo-documento-verificacao.pdf', 'application/pdf', 689, 'approved', NOW()
FROM users u WHERE u.email = 'apoiador@lumen.org'
AND NOT EXISTS (SELECT 1 FROM supporter_documents d WHERE d.supporter_id = u.id AND d.kind = 'identity');

INSERT INTO supporter_documents (supporter_id, kind, original_name, stored_name, mime_type, size_bytes, status, reviewed_at)
SELECT u.id, 'professional_license', 'registro-profissional-demo.pdf', 'demo-documento-verificacao.pdf', 'application/pdf', 689, 'approved', NOW()
FROM users u WHERE u.email = 'apoiador@lumen.org'
AND NOT EXISTS (SELECT 1 FROM supporter_documents d WHERE d.supporter_id = u.id AND d.kind = 'professional_license');

INSERT INTO supporter_documents (supporter_id, kind, original_name, stored_name, mime_type, size_bytes, status, reviewed_at)
SELECT u.id, 'proof_of_address', 'comprovante-endereco-demo.pdf', 'demo-documento-verificacao.pdf', 'application/pdf', 689, 'approved', NOW()
FROM users u WHERE u.email = 'apoiador@lumen.org'
AND NOT EXISTS (SELECT 1 FROM supporter_documents d WHERE d.supporter_id = u.id AND d.kind = 'proof_of_address');

INSERT INTO supporter_documents (supporter_id, kind, original_name, stored_name, mime_type, size_bytes, status, reviewed_at)
SELECT u.id, 'cnpj', 'cartao-cnpj-demo.pdf', 'demo-documento-verificacao.pdf', 'application/pdf', 689, 'approved', NOW()
FROM users u WHERE u.email = 'ong@lumen.org'
AND NOT EXISTS (SELECT 1 FROM supporter_documents d WHERE d.supporter_id = u.id AND d.kind = 'cnpj');

INSERT INTO supporter_documents (supporter_id, kind, original_name, stored_name, mime_type, size_bytes, status, reviewed_at)
SELECT u.id, 'proof_of_address', 'comprovante-sede-demo.pdf', 'demo-documento-verificacao.pdf', 'application/pdf', 689, 'approved', NOW()
FROM users u WHERE u.email = 'ong@lumen.org'
AND NOT EXISTS (SELECT 1 FROM supporter_documents d WHERE d.supporter_id = u.id AND d.kind = 'proof_of_address');
