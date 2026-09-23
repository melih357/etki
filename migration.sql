-- Etki örnek migration dosyası
-- database.sql daha önce içe aktarıldıysa bu dosya sonraki ek alanları güvenle ekler.

USE etki_db;

CREATE TABLE IF NOT EXISTS schema_migrations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  version VARCHAR(30) NOT NULL UNIQUE,
  description VARCHAR(255) NOT NULL,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS notification_preferences JSON NULL AFTER avatar_path;

ALTER TABLE missions
  ADD COLUMN IF NOT EXISTS municipality_text TEXT NULL AFTER completed_at,
  ADD COLUMN IF NOT EXISTS visibility ENUM('public','approximate','private') NOT NULL DEFAULT 'approximate' AFTER municipality_text;

ALTER TABLE mission_evidence
  ADD COLUMN IF NOT EXISTS privacy_checked TINYINT(1) NOT NULL DEFAULT 0 AFTER note;

-- İndeksler database.sql içinde oluşturulmuştur. Bu migration yalnızca yeni alanları ekler.

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('2026.07.25.001', 'Bildirim tercihleri, görünürlük ve gizlilik kontrol alanları eklendi.');


-- Revize 1.1 uygulama katmanı güncellemesidir; yeni veritabanı kolonu gerektirmez.
INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('2026.07.25.002', 'Görev yayınlama görünürlüğü ve kategoriye göre sade konum akışı eklendi.');

-- Revize 3: ekip sohbeti, yönetici rolleri, susturma ve fotoğraf mesajları
ALTER TABLE `groups`
  ADD COLUMN IF NOT EXISTS chat_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER status;

ALTER TABLE group_members
  MODIFY COLUMN role ENUM('owner','administrator','assistant','leader','moderator','event_manager','safety_officer','verifier','member') NOT NULL DEFAULT 'member',
  ADD COLUMN IF NOT EXISTS muted_until DATETIME NULL AFTER joined_at;

CREATE TABLE IF NOT EXISTS group_messages (
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

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('2026.07.25.003', 'Ekip sohbeti, yönetici ve yardımcı rolleri, susturma, fotoğraf paylaşımı ve mesaj silme eklendi.');

-- Revize 5: Harita adres arama, görev yaşam döngüsü ve ekip içinden birebir mesajlaşma
CREATE TABLE IF NOT EXISTS direct_conversations (
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

CREATE TABLE IF NOT EXISTS direct_messages (
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

INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('2026.07.25.005', 'Harita adres arama, aktif görev işaretçileri, dayanıklı fotoğraflı sohbet ve ekip içi birebir mesajlaşma eklendi.');

-- Revize 6: sohbet yazma alanı sabitlendi ve birebir sohbet geçmişi ekranı eklendi.
-- Yeni tablo veya kolon gerekmez; mevcut direct_conversations ve direct_messages tabloları kullanılır.
INSERT IGNORE INTO schema_migrations (version, description)
VALUES ('2026.07.25.006', 'Fotoğraf sonrası mesaj yazma alanı sabitlendi ve geçmiş birebir sohbetler için Mesajlar ekranı eklendi.');
