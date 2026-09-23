-- Etki - WAMP / MySQL ilk kurulum veritabanı
-- phpMyAdmin üzerinden içe aktarılabilir.

CREATE DATABASE IF NOT EXISTS etki_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_turkish_ci;

USE etki_db;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS authority_reports;
DROP TABLE IF EXISTS authorities;
DROP TABLE IF EXISTS thanks;
DROP TABLE IF EXISTS xp_transactions;
DROP TABLE IF EXISTS user_badges;
DROP TABLE IF EXISTS badges;
DROP TABLE IF EXISTS mission_messages;
DROP TABLE IF EXISTS direct_messages;
DROP TABLE IF EXISTS direct_conversations;
DROP TABLE IF EXISTS group_messages;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS group_members;
DROP TABLE IF EXISTS `groups`;
DROP TABLE IF EXISTS answers;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS verifications;
DROP TABLE IF EXISTS mission_evidence;
DROP TABLE IF EXISTS mission_assignments;
DROP TABLE IF EXISTS missions;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS user_devices;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(40) NOT NULL UNIQUE,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(30) NULL,
  password_hash VARCHAR(255) NOT NULL,
  birth_year SMALLINT UNSIGNED NOT NULL,
  role ENUM('user','moderator','institution','admin') NOT NULL DEFAULT 'user',
  status ENUM('active','restricted','suspended','deleted') NOT NULL DEFAULT 'active',
  xp INT UNSIGNED NOT NULL DEFAULT 0,
  level SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  trust_score TINYINT UNSIGNED NOT NULL DEFAULT 50,
  energy TINYINT UNSIGNED NOT NULL DEFAULT 5,
  avatar_path VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role_status (role, status),
  INDEX idx_users_xp (xp)
) ENGINE=InnoDB;

CREATE TABLE user_devices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  device_identifier VARCHAR(190) NOT NULL,
  last_login DATETIME NULL,
  risk_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_device (user_id, device_identifier),
  CONSTRAINT fk_device_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(80) NOT NULL,
  icon VARCHAR(20) NOT NULL,
  description VARCHAR(255) NOT NULL,
  default_xp SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE missions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  creator_id BIGINT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL,
  description TEXT NOT NULL,
  location_name VARCHAR(150) NOT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  public_location_precision SMALLINT UNSIGNED NOT NULL DEFAULT 250,
  status ENUM('draft','review','open','accepted','in_progress','evidence_pending','verification_pending','authority_routed','authority_waiting','solution_check','completed','partially_completed','rejected','cancelled','safety_closed') NOT NULL DEFAULT 'open',
  risk_level ENUM('low','medium','high','blocked') NOT NULL DEFAULT 'low',
  required_evidence ENUM('none','answer','photo','before_after','video','reference_number') NOT NULL DEFAULT 'photo',
  xp_reward SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  expires_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_mission_creator FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_mission_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
  INDEX idx_mission_status_category (status, category_id),
  INDEX idx_mission_location (latitude, longitude),
  INDEX idx_mission_expires (expires_at)
) ENGINE=InnoDB;

CREATE TABLE mission_assignments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mission_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  accepted_at DATETIME NULL,
  completed_at DATETIME NULL,
  status ENUM('accepted','in_progress','evidence_submitted','verified','abandoned') NOT NULL DEFAULT 'accepted',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_assignment (mission_id, user_id),
  CONSTRAINT fk_assignment_mission FOREIGN KEY (mission_id) REFERENCES missions(id) ON DELETE CASCADE,
  CONSTRAINT fk_assignment_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_assignment_user_status (user_id, status)
) ENGINE=InnoDB;

CREATE TABLE mission_evidence (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mission_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  evidence_type ENUM('photo','before','after','video','reference_number','text') NOT NULL,
  file_path VARCHAR(255) NULL,
  blurred_file_path VARCHAR(255) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  captured_at DATETIME NULL,
  ai_score DECIMAL(5,2) NULL,
  moderation_status ENUM('pending','approved','rejected','manual_review') NOT NULL DEFAULT 'pending',
  note VARCHAR(1000) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_evidence_mission FOREIGN KEY (mission_id) REFERENCES missions(id) ON DELETE CASCADE,
  CONSTRAINT fk_evidence_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_evidence_moderation (moderation_status)
) ENGINE=InnoDB;

CREATE TABLE verifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mission_id BIGINT UNSIGNED NOT NULL,
  verifier_id BIGINT UNSIGNED NOT NULL,
  result ENUM('verified','partial','rejected','unreachable') NOT NULL,
  explanation VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_verification (mission_id, verifier_id),
  CONSTRAINT fk_verification_mission FOREIGN KEY (mission_id) REFERENCES missions(id) ON DELETE CASCADE,
  CONSTRAINT fk_verification_user FOREIGN KEY (verifier_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE questions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mission_id BIGINT UNSIGNED NOT NULL UNIQUE,
  answer_type ENUM('text','yes_no','number','photo') NOT NULL DEFAULT 'text',
  validity_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  accepted_answer_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_question_mission FOREIGN KEY (mission_id) REFERENCES missions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE answers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  answer VARCHAR(800) NOT NULL,
  confidence TINYINT UNSIGNED NOT NULL DEFAULT 4,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_answer_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
  CONSTRAINT fk_answer_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_answer_question_created (question_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE `groups` (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(80) NOT NULL,
  type VARCHAR(40) NOT NULL,
  area VARCHAR(120) NOT NULL,
  description VARCHAR(800) NULL,
  level SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('pending','active','restricted','closed') NOT NULL DEFAULT 'active',
  chat_enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_group_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE RESTRICT,
  INDEX idx_group_area_status (area, status)
) ENGINE=InnoDB;

CREATE TABLE group_members (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  role ENUM('owner','administrator','assistant','leader','moderator','event_manager','safety_officer','verifier','member') NOT NULL DEFAULT 'member',
  status ENUM('pending','active','removed') NOT NULL DEFAULT 'active',
  joined_at DATETIME NULL,
  muted_until DATETIME NULL,
  UNIQUE KEY uq_group_member (group_id, user_id),
  CONSTRAINT fk_group_member_group FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
  CONSTRAINT fk_group_member_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL,
  start_time DATETIME NOT NULL,
  end_time DATETIME NULL,
  meeting_area VARCHAR(150) NOT NULL,
  capacity SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  risk_level ENUM('low','medium','high') NOT NULL DEFAULT 'low',
  required_equipment VARCHAR(500) NULL,
  status ENUM('planned','active','completed','cancelled') NOT NULL DEFAULT 'planned',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_event_group FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE

) ENGINE=InnoDB;

CREATE TABLE group_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id BIGINT UNSIGNED NOT NULL,
  sender_id BIGINT UNSIGNED NOT NULL,
  body VARCHAR(1000) NULL,
  image_path VARCHAR(500) NULL,
  message_type ENUM('text','image','mixed') NOT NULL DEFAULT 'text',
  status ENUM('visible','deleted','reported') NOT NULL DEFAULT 'visible',
  deleted_by BIGINT UNSIGNED NULL,
  deleted_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_group_message_group FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
  CONSTRAINT fk_group_message_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_group_message_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_group_message_group_created (group_id, created_at),
  INDEX idx_group_message_status (group_id, status)
) ENGINE=InnoDB;


CREATE TABLE direct_conversations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  group_id BIGINT UNSIGNED NOT NULL,
  user_one_id BIGINT UNSIGNED NOT NULL,
  user_two_id BIGINT UNSIGNED NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  status ENUM('active','closed') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_direct_conversation_pair (group_id, user_one_id, user_two_id),
  CONSTRAINT fk_direct_conversation_group FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
  CONSTRAINT fk_direct_conversation_user_one FOREIGN KEY (user_one_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_direct_conversation_user_two FOREIGN KEY (user_two_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_direct_conversation_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_direct_conversation_updated (updated_at)
) ENGINE=InnoDB;

CREATE TABLE direct_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id BIGINT UNSIGNED NOT NULL,
  sender_id BIGINT UNSIGNED NOT NULL,
  body VARCHAR(1000) NULL,
  image_path VARCHAR(500) NULL,
  message_type ENUM('text','image','mixed') NOT NULL DEFAULT 'text',
  status ENUM('visible','deleted','reported') NOT NULL DEFAULT 'visible',
  deleted_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_direct_message_conversation FOREIGN KEY (conversation_id) REFERENCES direct_conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_direct_message_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_direct_message_conversation_created (conversation_id, created_at),
  INDEX idx_direct_message_status (conversation_id, status)
) ENGINE=InnoDB;

CREATE TABLE mission_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mission_id BIGINT UNSIGNED NOT NULL,
  sender_id BIGINT UNSIGNED NOT NULL,
  body VARCHAR(500) NOT NULL,
  status ENUM('visible','hidden','reported') NOT NULL DEFAULT 'visible',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_message_mission FOREIGN KEY (mission_id) REFERENCES missions(id) ON DELETE CASCADE,
  CONSTRAINT fk_message_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_message_mission_created (mission_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE badges (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  icon VARCHAR(20) NOT NULL,
  requirement_type VARCHAR(50) NOT NULL,
  requirement_value INT UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE user_badges (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  badge_id INT UNSIGNED NOT NULL,
  earned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_badge (user_id, badge_id),
  CONSTRAINT fk_user_badge_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_badge_badge FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE xp_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  mission_id BIGINT UNSIGNED NULL,
  amount SMALLINT NOT NULL,
  reason VARCHAR(160) NOT NULL,
  status ENUM('pending','approved','reversed') NOT NULL DEFAULT 'approved',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_xp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_xp_mission FOREIGN KEY (mission_id) REFERENCES missions(id) ON DELETE SET NULL,
  INDEX idx_xp_user_created (user_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE thanks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mission_id BIGINT UNSIGNED NOT NULL,
  sender_id BIGINT UNSIGNED NOT NULL,
  receiver_id BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_thanks (mission_id, sender_id, receiver_id),
  CONSTRAINT fk_thanks_mission FOREIGN KEY (mission_id) REFERENCES missions(id) ON DELETE CASCADE,
  CONSTRAINT fk_thanks_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_thanks_receiver FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE authorities (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  city VARCHAR(80) NOT NULL,
  district VARCHAR(80) NULL,
  category_slug VARCHAR(40) NOT NULL,
  name VARCHAR(160) NOT NULL,
  official_url VARCHAR(500) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  INDEX idx_authority_route (city, district, category_slug)
) ENGINE=InnoDB;

CREATE TABLE authority_reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mission_id BIGINT UNSIGNED NOT NULL,
  authority_id INT UNSIGNED NULL,
  report_url VARCHAR(500) NULL,
  reference_number VARCHAR(120) NULL,
  status ENUM('prepared','submitted','received','in_review','planned','resolved','not_responsible') NOT NULL DEFAULT 'prepared',
  reported_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_authority_report_mission FOREIGN KEY (mission_id) REFERENCES missions(id) ON DELETE CASCADE,
  CONSTRAINT fk_authority_report_authority FOREIGN KEY (authority_id) REFERENCES authorities(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL,
  body VARCHAR(500) NOT NULL,
  type VARCHAR(40) NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_notification_user_read (user_id, is_read, created_at)
) ENGINE=InnoDB;

CREATE TABLE reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reporter_id BIGINT UNSIGNED NOT NULL,
  target_type ENUM('mission','message','user','group') NOT NULL,
  target_id BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(500) NOT NULL,
  status ENUM('open','reviewing','resolved','dismissed') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_report_user FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_report_status (status, created_at)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL,
  entity_type VARCHAR(50) NOT NULL,
  entity_id BIGINT UNSIGNED NULL,
  metadata JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_entity (entity_type, entity_id, created_at)
) ENGINE=InnoDB;

INSERT INTO categories (id, slug, name, icon, description, default_xp, sort_order) VALUES
(1, 'question', 'Bir Bakıp Gel', '❓', 'Yakındaki bir yer hakkında güncel bilgi iste.', 10, 1),
(2, 'cleaning', 'Güvenli Temizlik', '♻️', 'Küçük ve güvenli çevre temizliği görevleri.', 30, 2),
(3, 'accessibility', 'Erişilebilirlik', '♿', 'Rampa, asansör ve geçiş uygunluğunu doğrula.', 20, 3),
(4, 'municipal', 'Mahalle Sorunu', '🏙️', 'Sorunu doğru kuruma yönlendirmeye katkı sağla.', 25, 4),
(5, 'animal', 'Hayvan Desteği', '🐾', 'Doğrulanmış su ve bakım noktalarını kontrol et.', 30, 5),
(6, 'solution_check', 'Çözüm Kontrolü', '✅', 'Kurumun çözüldü dediği noktayı sahada doğrula.', 35, 6);

INSERT INTO users (id, username, email, phone, password_hash, birth_year, role, status, xp, level, trust_score, energy) VALUES
(1, 'EtkiYönetim', 'admin@etki.local', NULL, '$2y$12$/PA0FxJd1qkpUCri.XBhIO2DNpdhZHDtOSCQT5nPvwfDI2GfBVTmu', 1990, 'admin', 'active', 2450, 5, 95, 9),
(2, 'Deniz', 'demo@etki.local', NULL, '$2y$12$zg7gd4ZeuB6oBO7/q4uWUe8t9P1jpzZSf9LoxtTq/iqKUbVfPHRN2', 1998, 'user', 'active', 820, 3, 78, 7),
(3, 'Zeynep', 'zeynep@etki.local', NULL, '$2y$12$zg7gd4ZeuB6oBO7/q4uWUe8t9P1jpzZSf9LoxtTq/iqKUbVfPHRN2', 1996, 'user', 'active', 540, 3, 82, 6),
(4, 'Mert', 'mert@etki.local', NULL, '$2y$12$zg7gd4ZeuB6oBO7/q4uWUe8t9P1jpzZSf9LoxtTq/iqKUbVfPHRN2', 1995, 'user', 'active', 310, 2, 71, 5);

INSERT INTO missions (id, creator_id, category_id, title, description, location_name, latitude, longitude, status, risk_level, required_evidence, xp_reward, expires_at) VALUES
(1, 3, 1, 'Kütüphanede prizli boş masa var mı?', 'Üst katta çalışabileceğim prizli bir masa olup olmadığını kontrol edebilir misiniz?', 'Kadıköy Halk Kütüphanesi', 40.9904050, 29.0271960, 'open', 'low', 'answer', 10, DATE_ADD(NOW(), INTERVAL 2 HOUR)),
(2, 4, 2, 'Park girişindeki küçük ambalajları temizle', 'Yalnızca güvenli plastik ve kâğıt ambalajları eldivenle topla. Kesici veya tıbbi atığa dokunma.', 'Moda Parkı giriş alanı', 40.9788200, 29.0261500, 'open', 'low', 'before_after', 30, DATE_ADD(NOW(), INTERVAL 5 DAY)),
(3, 2, 3, 'Metro asansörü çalışıyor mu?', 'Asansörün çalışıp çalışmadığını ve girişin engelsiz olup olmadığını doğrula.', 'Kadıköy Metro ana giriş', 40.9909800, 29.0237700, 'open', 'low', 'photo', 20, DATE_ADD(NOW(), INTERVAL 1 DAY)),
(4, 3, 4, 'Kaldırımdaki çökme için doğru kurumu belirle', 'Bebek arabası ve tekerlekli sandalye geçişini zorlaştıran kaldırım çökmesi bulunuyor. Uygun belediye bağlantısını doğrula.', 'Caferağa Mahallesi', 40.9857200, 29.0269000, 'verification_pending', 'medium', 'photo', 25, DATE_ADD(NOW(), INTERVAL 7 DAY)),
(5, 4, 6, 'Sokak lambası gerçekten onarıldı mı?', 'Kurum kaydında çözüldü görünen lambanın akşam çalışıp çalışmadığını güvenli mesafeden kontrol et.', 'Rıhtım Caddesi', 40.9915100, 29.0220400, 'solution_check', 'low', 'photo', 35, DATE_ADD(NOW(), INTERVAL 3 DAY)),
(6, 2, 5, 'Doğrulanmış su noktasını kontrol et', 'Kabın temizliğini ve su seviyesini kontrol et. Hayvana yaklaşma veya müdahale etme.', 'Yoğurtçu Parkı su noktası', 40.9834700, 29.0332300, 'open', 'low', 'before_after', 30, DATE_ADD(NOW(), INTERVAL 2 DAY));

INSERT INTO questions (id, mission_id, answer_type, validity_minutes) VALUES
(1, 1, 'text', 120);

INSERT INTO mission_assignments (mission_id, user_id, accepted_at, completed_at, status) VALUES
(4, 2, DATE_SUB(NOW(), INTERVAL 3 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR), 'evidence_submitted');

INSERT INTO mission_evidence (mission_id, user_id, evidence_type, file_path, captured_at, moderation_status, note) VALUES
(4, 2, 'photo', NULL, DATE_SUB(NOW(), INTERVAL 1 HOUR), 'pending', 'Kaldırım çökmesi ve geçiş genişliği kontrol edildi.');

INSERT INTO answers (question_id, user_id, answer, confidence) VALUES
(1, 4, 'Alt katta iki boş masa var, birinde priz bulunuyor.', 4);

INSERT INTO badges (id, name, icon, requirement_type, requirement_value) VALUES
(1, 'İlk Etki', '✨', 'completed_missions', 1),
(2, 'Mahalle Gözcüsü', '👀', 'verifications', 10),
(3, 'Temiz Sokak', '♻️', 'cleaning_missions', 10),
(4, 'Engelsiz Yol Gösterici', '♿', 'accessibility_missions', 10),
(5, 'Güvenilir Doğrulayıcı', '✅', 'trust_score', 80);

INSERT INTO user_badges (user_id, badge_id, earned_at) VALUES
(2, 1, DATE_SUB(NOW(), INTERVAL 30 DAY)),
(2, 5, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(3, 1, DATE_SUB(NOW(), INTERVAL 20 DAY));

INSERT INTO xp_transactions (user_id, mission_id, amount, reason, status, created_at) VALUES
(2, 3, 20, 'Erişilebilirlik bilgisi ekledi', 'approved', DATE_SUB(NOW(), INTERVAL 4 DAY)),
(2, 2, 30, 'Doğrulanmış görev tamamlandı', 'approved', DATE_SUB(NOW(), INTERVAL 8 DAY)),
(2, 1, 10, 'Kişisel soruya cevap', 'approved', DATE_SUB(NOW(), INTERVAL 10 DAY));

INSERT INTO `groups` (id, owner_id, name, type, area, description, level, status) VALUES
(1, 3, 'Moda Temiz Mahalle', 'cleaning', 'Kadıköy / Moda', 'Küçük ve güvenli çevre temizliği görevleri yapan mahalle ekibi.', 4, 'active'),
(2, 2, 'Engelsiz Kadıköy', 'accessibility', 'Kadıköy', 'Rampa, asansör ve kaldırım geçişlerini doğrulayan ekip.', 3, 'active'),
(3, 4, 'Su Dostları', 'animal', 'Kadıköy sahil hattı', 'Yalnızca doğrulanmış su noktalarını güvenli şekilde takip eder.', 2, 'active');

INSERT INTO group_members (group_id, user_id, role, status, joined_at) VALUES
(1, 3, 'owner', 'active', DATE_SUB(NOW(), INTERVAL 90 DAY)),
(1, 2, 'member', 'active', DATE_SUB(NOW(), INTERVAL 20 DAY)),
(2, 2, 'owner', 'active', DATE_SUB(NOW(), INTERVAL 70 DAY)),
(2, 3, 'verifier', 'active', DATE_SUB(NOW(), INTERVAL 14 DAY)),
(3, 4, 'owner', 'active', DATE_SUB(NOW(), INTERVAL 40 DAY));

INSERT INTO group_messages (group_id, sender_id, body, message_type, status, created_at) VALUES
(1, 3, 'Ekibimize hoş geldiniz. Buradan görevleri ve buluşma detaylarını konuşabiliriz.', 'text', 'visible', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 2, 'Teşekkürler, hafta sonu etkinliğine katılacağım.', 'text', 'visible', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 2, 'Yeni erişilebilirlik doğrulamalarını bu grupta paylaşabilirsiniz.', 'text', 'visible', DATE_SUB(NOW(), INTERVAL 12 HOUR));

INSERT INTO events (group_id, title, start_time, end_time, meeting_area, capacity, risk_level, required_equipment, status) VALUES
(1, 'Moda Sahili Güvenli Temizlik', DATE_ADD(NOW(), INTERVAL 10 DAY), DATE_ADD(DATE_ADD(NOW(), INTERVAL 10 DAY), INTERVAL 1 HOUR), 'Moda Sahili yaklaşık buluşma alanı', 15, 'low', 'Eldiven, maşa, kapalı ayakkabı', 'planned');

INSERT INTO authorities (city, district, category_slug, name, official_url) VALUES
('İstanbul', 'Kadıköy', 'municipal', 'Kadıköy Belediyesi', 'https://www.kadikoy.bel.tr/'),
('İstanbul', NULL, 'municipal', 'İstanbul Büyükşehir Belediyesi Çözüm Merkezi', 'https://cozummerkezi.ibb.istanbul/');

SET FOREIGN_KEY_CHECKS = 1;
