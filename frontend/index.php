<?php
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/etki/frontend')), '/');
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0D1B2A">
  <title>Etki — İyilik İz Bırakır</title>
  <meta name="description" content="Gerçek görevleri keşfet, katkı sağla, doğrula ve şehrinde etki bırak.">
  <link rel="icon" type="image/png" href="assets/img/etki-icon.png">
  <link rel="preconnect" href="https://unpkg.com">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
  <link rel="stylesheet" href="assets/css/app.css?v=1.6.0">
</head>
<body data-base="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>">
  <div class="app-shell">
    <aside class="sidebar" id="sidebar">
      <a class="brand" href="#" data-section="home" aria-label="Etki ana sayfa">
        <img src="assets/img/etki-logo-board.png" alt="Etki — İyilik İz Bırakır">
      </a>

      <nav class="nav-list" aria-label="Ana menü">
        <button class="nav-item active" data-section="home"><span>⌂</span>Ana sayfa</button>
        <button class="nav-item" data-section="missions"><span>◎</span>Görevler</button>
        <button class="nav-item" data-section="map"><span>⌖</span>Etki haritası</button>
        <button class="nav-item" data-section="teams"><span>♧</span>Ekipler</button>
        <button class="nav-item" data-section="messages"><span>✉</span>Mesajlar</button>
        <button class="nav-item" data-section="impact"><span>↗</span>Etki özeti</button>
        <button class="nav-item" data-section="profile"><span>◉</span>Profilim</button>
        <button class="nav-item admin-only hidden" data-section="admin"><span>⚙</span>Yönetim</button>
      </nav>

      <div class="sidebar-card">
        <strong>Güvenlik önce gelir</strong>
        <p>Yangın, kaza, şiddet, gaz kokusu ve benzeri acil durumlar görev değildir.</p>
        <a href="tel:112">Acil durumda 112</a>
      </div>

      <div class="sidebar-footer">Etki MVP · WAMP / PHP / MySQL</div>
    </aside>

    <main class="main-content">
      <header class="topbar">
        <button class="icon-button mobile-menu" id="mobileMenu" aria-label="Menüyü aç">☰</button>
        <div class="search-wrap">
          <span>⌕</span>
          <input id="globalSearch" type="search" placeholder="Görev, bölge veya ekip ara…" autocomplete="off">
        </div>
        <div class="top-actions">
          <button class="icon-button" id="refreshBtn" title="Yenile">↻</button>
          <button class="profile-chip" id="authButton">
            <span class="avatar" id="topAvatar">E</span>
            <span id="topUserText">Giriş yap</span>
          </button>
        </div>
      </header>

      <section class="page-section active" id="section-home">
        <div class="hero">
          <div class="hero-copy">
            <span class="eyebrow">GERÇEK GÖREVLER · GERÇEK FAYDA</span>
            <h1>Bugün çevrende nasıl bir <em>etki</em> bırakacaksın?</h1>
            <p>Yakınındaki küçük iyilikleri keşfet, katkını kanıtla, toplulukla doğrula ve seviye atla.</p>
            <div class="hero-actions">
              <button class="btn btn-primary" data-open-create>Görev oluştur</button>
              <button class="btn btn-light" data-section="missions">Yakındaki görevler</button>
            </div>
          </div>
          <div class="hero-visual">
            <div class="impact-orbit orbit-one"></div>
            <div class="impact-orbit orbit-two"></div>
            <div class="hero-mark">ek</div>
            <span class="floating-badge badge-a">+30 XP</span>
            <span class="floating-badge badge-b">✓ Doğrulandı</span>
            <span class="floating-badge badge-c">♧ Ekip etkisi</span>
          </div>
        </div>

        <div class="quick-grid">
          <button class="quick-card" data-create-category="question"><span>❓</span><strong>Bir şey sor</strong><small>Yakındaki birinden güncel bilgi al</small></button>
          <button class="quick-card" data-create-category="cleaning"><span>♻️</span><strong>İyilik görevi</strong><small>Küçük ve güvenli bir katkı oluştur</small></button>
          <button class="quick-card" data-create-category="municipal"><span>🏙️</span><strong>Sorun bildir</strong><small>Doğru kuruma yönlendirme başlat</small></button>
          <button class="quick-card" data-section="teams"><span>♧</span><strong>Ekibe katıl</strong><small>Birlikte daha güçlü hareket et</small></button>
        </div>

        <div class="stats-grid" id="homeStats"></div>

        <div class="content-grid two-column">
          <div class="panel">
            <div class="panel-head">
              <div><span class="eyebrow">YAKININDA</span><h2>Öne çıkan görevler</h2></div>
              <button class="text-button" data-section="missions">Tümünü gör →</button>
            </div>
            <div id="featuredMissions" class="mission-list compact"></div>
          </div>
          <div class="panel impact-panel">
            <div class="panel-head">
              <div><span class="eyebrow">BU HAFTA</span><h2>Topluluk etkisi</h2></div>
            </div>
            <div class="ring-wrap">
              <div class="progress-ring" id="impactRing"><span id="impactPercent">0%</span></div>
              <div>
                <strong id="impactText">Veriler yükleniyor…</strong>
                <p>Hedef: daha fazla doğrulanmış gerçek katkı.</p>
              </div>
            </div>
            <div class="mini-bars" id="impactBars"></div>
          </div>
        </div>
      </section>

      <section class="page-section" id="section-missions">
        <div class="page-heading">
          <div><span class="eyebrow">KEŞFET</span><h1>Görevler</h1><p>Bir sorunun fotoğrafını paylaşmak değil, çözümüne gerçek katkı sağlamak puan kazandırır.</p></div>
          <button class="btn btn-primary" data-open-create>+ Yeni görev</button>
        </div>
        <div class="filter-row" id="categoryFilters"></div>
        <div class="mission-grid" id="missionGrid"></div>
        <div class="empty-state hidden" id="missionEmpty">Aramana uygun görev bulunamadı.</div>
      </section>

      <section class="page-section" id="section-map">
        <div class="page-heading">
          <div><span class="eyebrow">YAKLAŞIK KONUM</span><h1>Etki haritası</h1><p>Kişilerin canlı konumu değil, görevlerin yaklaşık alanı gösterilir.</p></div>
        </div>
        <div class="map-layout">
          <div id="mapCanvas" class="map-canvas"></div>
          <div class="map-side">
            <h3>Haritadaki görevler</h3>
            <div id="mapMissionList" class="mission-list compact"></div>
          </div>
        </div>
      </section>

      <section class="page-section" id="section-teams">
        <div class="page-heading">
          <div><span class="eyebrow">BİRLİKTE DAHA GÜÇLÜ</span><h1>Ekipler</h1><p>Ortak amaçlar etrafında güvenli, sınırlı ve doğrulanabilir etkinlikler düzenleyin.</p></div>
          <button class="btn btn-primary" id="openTeamModal">+ Ekip oluştur</button>
        </div>
        <div class="team-grid" id="teamGrid"></div>
      </section>

      <section class="page-section" id="section-messages">
        <div class="page-heading">
          <div><span class="eyebrow">BİREBİR SOHBETLER</span><h1>Mesajlar</h1><p>Daha önce ekiplerden tanışıp özel mesajlaştığın kişilere buradan ulaşabilirsin.</p></div>
          <button class="btn btn-outline" id="refreshConversations" type="button">↻ Yenile</button>
        </div>
        <div id="messagesGuest" class="empty-state">
          <h2>Mesajlarını görmek için giriş yap</h2>
          <p>Geçmiş birebir sohbetlerin ve son mesajların burada listelenir.</p>
          <button class="btn btn-primary" data-open-auth>Giriş yap</button>
        </div>
        <div id="messagesContent" class="messages-page hidden">
          <div class="panel conversations-panel">
            <div class="panel-head"><div><span class="eyebrow">SOHBET GEÇMİŞİ</span><h2>Kişisel konuşmalar</h2></div><span id="conversationCount" class="chip">0 sohbet</span></div>
            <div id="directConversationList" class="conversation-list" aria-live="polite"></div>
          </div>
          <aside class="messages-info panel">
            <h3>Güvenli mesajlaşma</h3>
            <p>Telefon numarası, kesin konum, ev adresi veya özel kişisel bilgi paylaşma. Rahatsız edici bir durumda sohbeti sürdürme ve ekip yöneticisine bildir.</p>
          </aside>
        </div>
      </section>

      <section class="page-section" id="section-impact">
        <div class="page-heading">
          <div><span class="eyebrow">İYİLİK İZ BIRAKIR</span><h1>Etki özeti</h1><p>Katkıyı bildirim sayısıyla değil, doğrulanmış sonuçlarla ölçüyoruz.</p></div>
        </div>
        <div class="impact-hero-card">
          <div><span class="eyebrow">TOPLULUK HEDEFİ</span><h2>100 doğrulanmış katkıya doğru</h2><p>Her tamamlanan görev mahallenin ortak etki puanını yükseltir.</p></div>
          <div class="impact-score" id="communityScore">0</div>
        </div>
        <div class="stats-grid" id="impactStats"></div>
        <div class="panel">
          <div class="panel-head"><div><span class="eyebrow">KATEGORİLER</span><h2>Etki dağılımı</h2></div></div>
          <div id="categoryImpact" class="category-impact"></div>
        </div>
      </section>

      <section class="page-section" id="section-profile">
        <div id="profileGuest" class="empty-state">
          <h2>Profilini görmek için giriş yap</h2>
          <p>XP, seviye, güven etiketi, rozetler ve görev geçmişi burada görünür.</p>
          <button class="btn btn-primary" data-open-auth>Giriş yap</button>
        </div>
        <div id="profileContent" class="hidden"></div>
      </section>

      <section class="page-section" id="section-admin">
        <div class="page-heading">
          <div><span class="eyebrow">YÖNETİM MERKEZİ</span><h1>Etki yönetimi</h1><p>Kullanıcı, görev, kanıt ve güvenlik durumlarını takip edin.</p></div>
        </div>
        <div class="stats-grid" id="adminStats"></div>
        <div class="content-grid two-column">
          <div class="panel"><div class="panel-head"><h2>Son kullanıcılar</h2></div><div class="table-wrap"><table><thead><tr><th>Kullanıcı</th><th>Rol</th><th>Seviye</th><th>Güven</th></tr></thead><tbody id="adminUsers"></tbody></table></div></div>
          <div class="panel"><div class="panel-head"><h2>Son görevler</h2></div><div class="table-wrap"><table><thead><tr><th>Görev</th><th>Durum</th><th>Risk</th><th>İşlem</th></tr></thead><tbody id="adminMissions"></tbody></table></div></div>
        </div>
      </section>
    </main>
  </div>

  <div class="toast-stack" id="toastStack" aria-live="polite"></div>

  <dialog class="modal" id="authModal">
    <button class="modal-close" data-close-dialog aria-label="Kapat">×</button>
    <div class="auth-logo"><img src="assets/img/etki-logo-board.png" alt="Etki"></div>
    <div class="tab-row">
      <button class="tab-button active" data-auth-tab="login">Giriş yap</button>
      <button class="tab-button" data-auth-tab="register">Kayıt ol</button>
    </div>
    <form id="loginForm" class="form-stack">
      <label>E-posta<input type="email" name="email" required value="demo@etki.local"></label>
      <label>Şifre<input type="password" name="password" required value="Etki123!"></label>
      <button class="btn btn-primary full" type="submit">Giriş yap</button>
      <small>Demo kullanıcı: demo@etki.local / Etki123!</small>
      <small>Yönetici: admin@etki.local / EtkiAdmin123!</small>
    </form>
    <form id="registerForm" class="form-stack hidden">
      <label>Kullanıcı adı<input type="text" name="username" minlength="3" required></label>
      <label>E-posta<input type="email" name="email" required></label>
      <label>Doğum yılı<input type="number" name="birth_year" min="1900" max="2008" required></label>
      <label>Şifre<input type="password" name="password" minlength="8" required></label>
      <label class="check-row"><input type="checkbox" required> 18 yaşından büyük olduğumu ve güvenlik kurallarını kabul ediyorum.</label>
      <button class="btn btn-primary full" type="submit">Hesap oluştur</button>
    </form>
  </dialog>

  <dialog class="modal wide" id="createMissionModal">
    <button class="modal-close" data-close-dialog aria-label="Kapat">×</button>
    <div class="modal-heading"><span class="eyebrow">YENİ ETKİ</span><h2>Görev oluştur</h2><p>Tehlikeli veya acil olayları oyunlaştırma. Yalnızca güvenli ve doğrulanabilir katkılar oluştur.</p></div>
    <form id="createMissionForm" class="form-grid">
      <label class="full">Kategori<select name="category_id" id="missionCategory" required></select></label>
      <div class="category-hint full" id="categoryHint">Kategoriye göre yalnızca gerekli alanlar gösterilir.</div>
      <label class="full">Başlık<input name="title" id="missionTitle" maxlength="120" required placeholder="Kısa ve anlaşılır bir görev başlığı yaz"></label>
      <label class="full">Açıklama<textarea name="description" id="missionDescription" rows="4" minlength="10" required placeholder="Ne yapılması gerektiğini ve güvenlik sınırlarını yaz."></textarea></label>

      <div class="full location-block" id="missionLocationBlock">
        <label id="missionLocationLabel"><span id="missionLocationLabelText">Konum / genel bölge</span>
          <input name="location_name" id="missionLocationName" maxlength="150" placeholder="Mahalle, park veya kurum adı">
        </label>
        <small class="field-help" id="missionLocationHelp">Kesin adres yerine herkesin anlayabileceği genel bir alan yaz.</small>
        <div class="location-choice" id="missionLocationTools">
          <button class="btn btn-outline" type="button" id="useCurrentLocation">⌖ Şu anki konumumu ekle</button>
          <span class="location-or">veya</span>
          <div class="address-search-row">
            <input id="missionAddressSearch" type="search" maxlength="240" placeholder="Açık adres yaz: mahalle, cadde, ilçe, şehir">
            <button class="btn btn-outline" type="button" id="findAddressLocation">Adresi haritada bul</button>
          </div>
          <div id="addressSearchResults" class="address-results hidden" aria-live="polite"></div>
          <span class="location-status" id="locationStatus">Harita noktası henüz eklenmedi.</span>
        </div>
        <input name="latitude" id="missionLatitude" type="hidden">
        <input name="longitude" id="missionLongitude" type="hidden">
      </div>

      <label>Geçerlilik süresi<select name="expires_option"><option value="2h">2 saat</option><option value="1d">1 gün</option><option value="3d">3 gün</option><option value="7d" selected>7 gün</option></select></label>
      <label class="question-only hidden">Cevap geçerliliği<select name="validity_minutes"><option value="30">30 dakika</option><option value="60" selected>60 dakika</option><option value="120">2 saat</option><option value="240">4 saat</option></select></label>
      <div class="animal-only security-note full hidden"><strong>Hayvan güvenliği:</strong> Hayvanın kesin konumunu, ev adresini veya kişisel bilgileri paylaşma. Yaralı ya da saldırgan hayvana yaklaşma; profesyonel birime yönlendir.</div>
      <div class="security-note full"><strong>Güvenlik notu:</strong> Yangın, kaza, şiddet, gaz kokusu, kimyasal atık, şüpheli paket veya tıbbi acil durum görev olarak yayınlanamaz.</div>
      <button class="btn btn-primary full" id="publishMissionButton" type="submit">Görevi yayınla</button>
    </form>
  </dialog>

  <dialog class="modal wide" id="missionModal">
    <button class="modal-close" data-close-dialog aria-label="Kapat">×</button>
    <div id="missionDetail"></div>
  </dialog>

  <dialog class="modal" id="completeModal">
    <button class="modal-close" data-close-dialog aria-label="Kapat">×</button>
    <div class="modal-heading"><span class="eyebrow">KANIT GÖNDER</span><h2>Görevi tamamla</h2></div>
    <form id="completeForm" class="form-stack" enctype="multipart/form-data">
      <input type="hidden" name="mission_id" id="completeMissionId">
      <label>Fotoğraf kanıtı<input type="file" name="evidence" accept="image/jpeg,image/png,image/webp" required></label>
      <label>Açıklama<textarea name="note" rows="4" placeholder="Ne yaptığını ve sonucun ne olduğunu kısaca yaz."></textarea></label>
      <div class="security-note">Yüz, plaka, çocuk görüntüsü veya özel adres görünüyorsa yüklemeden önce bulanıklaştır.</div>
      <button class="btn btn-primary full" type="submit">Kanıtı gönder</button>
    </form>
  </dialog>

  <dialog class="modal" id="verifyModal">
    <button class="modal-close" data-close-dialog aria-label="Kapat">×</button>
    <div class="modal-heading"><span class="eyebrow">TOPLULUK DOĞRULAMASI</span><h2>Sonucu doğrula</h2></div>
    <form id="verifyForm" class="form-stack">
      <input type="hidden" name="mission_id" id="verifyMissionId">
      <label>Sonuç<select name="result"><option value="verified">Çözüldü / doğrulandı</option><option value="partial">Kısmen çözüldü</option><option value="rejected">Doğrulanamadı</option><option value="unreachable">Konuma ulaşılamıyor</option></select></label>
      <label>Açıklama<textarea name="explanation" rows="4" placeholder="Gözlemini kişisel bilgi içermeden yaz."></textarea></label>
      <button class="btn btn-primary full" type="submit">Doğrulamayı kaydet</button>
    </form>
  </dialog>

  <dialog class="modal group-chat-modal" id="groupModal">
    <button class="modal-close" data-close-dialog aria-label="Kapat">×</button>
    <div id="groupDetail"></div>
  </dialog>

  <dialog class="modal direct-chat-modal" id="directMessageModal">
    <button class="modal-close" data-close-dialog aria-label="Kapat">×</button>
    <div id="directMessageDetail"></div>
  </dialog>

  <dialog class="modal" id="teamModal">
    <button class="modal-close" data-close-dialog aria-label="Kapat">×</button>
    <div class="modal-heading"><span class="eyebrow">YENİ TOPLULUK</span><h2>Ekip oluştur</h2></div>
    <form id="teamForm" class="form-stack">
      <label>Ekip adı<input name="name" required maxlength="80"></label>
      <label>Tür<select name="type"><option value="cleaning">Temiz Mahalle</option><option value="accessibility">Erişilebilir Şehir</option><option value="animal">Hayvan Dostları</option><option value="community">Mahalle Topluluğu</option></select></label>
      <label>Çalışma alanı<input name="area" required placeholder="İlçe / mahalle"></label>
      <label>Açıklama<textarea name="description" rows="4"></textarea></label>
      <button class="btn btn-primary full" type="submit">Ekibi oluştur</button>
    </form>
  </dialog>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
  <script src="assets/js/app.js?v=1.6.0"></script>
</body>
</html>
