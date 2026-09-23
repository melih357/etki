# Etki — WAMP / PHP / MySQL MVP

**Etki**, gerçek dünyadaki küçük iyilikleri, güncel soruları, erişilebilirlik kontrollerini ve mahalle sorunlarını doğrulanabilir görevlere dönüştüren mobil uyumlu web prototipidir.

Slogan: **İyilik iz bırakır.**

Bu paket WAMP Server üzerinde doğrudan çalışacak şekilde hazırlanmıştır. Framework veya Composer kurulumu gerektirmez.

## Klasör yapısı

```text
etki/
├── backend/
│   ├── api/
│   │   ├── admin.php
│   │   ├── answers.php
│   │   ├── auth.php
│   │   ├── categories.php
│   │   ├── groups.php
│   │   ├── health.php
│   │   ├── messages.php
│   │   ├── mission_action.php
│   │   ├── missions.php
│   │   └── profile.php
│   ├── .htaccess
│   └── bootstrap.php
├── frontend/
│   ├── assets/
│   │   ├── css/app.css
│   │   ├── img/etki-icon.png
│   │   ├── img/etki-logo-board.png
│   │   └── js/app.js
│   └── index.php
├── uploads/
│   ├── .gitkeep
│   └── .htaccess
├── .env
├── database.sql
├── migration.sql
└── readme.md
```

## Gereksinimler

- WAMP Server
- Apache 2.4+
- PHP 8.1 veya daha yeni
- MySQL 8 veya güncel MariaDB
- PHP eklentileri: `pdo_mysql`, `mbstring`, `fileinfo`, `json`
- Tarayıcıda JavaScript açık olmalıdır

## 1. Projeyi WAMP klasörüne yerleştirme

ZIP dosyasını çıkartın ve içindeki **etki** klasörünü şu konuma kopyalayın:

```text
C:\wamp64\www\etki
```

Sonuç şu şekilde görünmelidir:

```text
C:\wamp64\www\etki\frontend\index.php
```

## 2. WAMP servislerini başlatma

WAMP simgesinin yeşil olduğundan emin olun. Apache ve MySQL servisleri çalışıyor olmalıdır.

## 3. Veritabanını oluşturma

Tarayıcıdan phpMyAdmin'i açın:

```text
http://localhost/phpmyadmin/
```

Ardından:

1. **İçe Aktar / Import** sekmesine girin.
2. Proje kökündeki `database.sql` dosyasını seçin.
3. İçe aktarma işlemini başlatın.
4. `etki_db` adlı veritabanının ve tabloların oluştuğunu kontrol edin.

`database.sql` örnek kullanıcıları, görevleri, ekipleri ve kategorileri otomatik ekler.

## 4. `.env` ayarları

WAMP'ın varsayılan MySQL kurulumu için dosya hazır gelir:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=etki_db
DB_USERNAME=root
DB_PASSWORD=
```

MySQL root hesabınızda şifre varsa `.env` içindeki `DB_PASSWORD` değerini değiştirin.

Proje klasörünün adı `etki` değilse `APP_URL` değerini yeni klasör adına göre güncelleyin.

## 5. Uygulamayı açma

Tarayıcıda şu adresi açın:

```text
http://localhost/etki/frontend/
```

API sağlık kontrolü:

```text
http://localhost/etki/backend/api/health.php
```

Sağlık kontrolünde `database: connected` görünmelidir.

## Demo hesapları

### Normal kullanıcı

```text
E-posta: demo@etki.local
Şifre: Etki123!
```

### Yönetici

```text
E-posta: admin@etki.local
Şifre: EtkiAdmin123!
```

Yönetici hesabıyla giriş yapınca sol menüde **Yönetim** bölümü açılır.

## Revize 1.1 — Görev yayınlama ve sade konum akışı

- Görev yayınlandı mesajından sonra yeni kayıt anında listenin en üstünde gösterilir.
- Görev listesi isteklerinde tarayıcı önbelleği kapatılmıştır.
- Yayınlama sonrasında eski arama ve kategori filtresi temizlenir; böylece yeni görev gizli kalmaz.
- Enlem ve boylam alanları kullanıcıdan kaldırılmıştır.
- Gerektiğinde **Mevcut konumumu kullan** düğmesi koordinatı arka planda ekler.
- Hayvan görevlerinde genel bölge isteğe bağlıdır ve kesin koordinat kaydedilmez.
- Kategoriye göre başlık, açıklama, konum gereksinimi ve güvenlik uyarıları değişir.
- Çift tıklamayla aynı görevin iki kez gönderilmesini önlemek için yayınlama düğmesi işlem sırasında kilitlenir.

## Prototipte çalışan özellikler

- Kullanıcı kaydı ve giriş
- 18 yaş kontrolü
- Oturum ve CSRF koruması
- Görev listeleme ve kategori filtreleme
- Görev oluşturma
- Kişisel soru oluşturma ve cevaplama
- Görev kabul etme ve başlatma
- Fotoğraf kanıtı yükleme
- Topluluk doğrulaması
- XP ve seviye hesabı
- Güven puanı görünümü
- Göreve bağlı sınırlı mesajlaşma
- Ekipleri listeleme, ekibe katılma ve ekip oluşturma
- OpenStreetMap tabanlı yaklaşık görev haritası
- Profil, rozetler ve XP geçmişi
- Yönetici istatistikleri ve görev durumu düzenleme
- Tehlikeli görev kelimeleri için temel güvenlik filtresi
- Yüklenen dosyalarda uzantı, MIME ve boyut kontrolü

## Fotoğraf yükleme testi

1. Demo kullanıcıyla giriş yapın.
2. Başka bir kullanıcı tarafından oluşturulmuş açık görevi seçin.
3. **Görevi kabul et** düğmesine basın.
4. Görevi başlatın.
5. **Kanıt gönder** seçeneğine girin.
6. JPG, PNG veya WEBP fotoğraf seçin.
7. Kanıtı gönderin.

Dosya otomatik olarak şu klasöre kaydedilir:

```text
uploads\YIL\AY\
```

Apache'nin `uploads` klasörüne yazma izni bulunmalıdır. Windows'ta çoğunlukla ek ayar gerekmez.

## Migration dosyası

`migration.sql`, `database.sql` kurulduktan sonra çalıştırılmak üzere hazırlanmış örnek güncelleme dosyasıdır. Yeni bildirim tercihleri, görünürlük ve gizlilik kontrol alanlarını ekler.

İlk kurulum için yalnızca `database.sql` yeterlidir. Daha sonra `migration.sql` phpMyAdmin üzerinden içe aktarılabilir.

## Harita hakkında

Harita arayüzü Leaflet ve OpenStreetMap kullanır. Harita kutucuklarının görünmesi için internet bağlantısı gerekir. Uygulamanın diğer bölümleri yerelde çalışmaya devam eder.

## Güvenlik sınırları

Bu paket bir MVP/prototiptir. Üretime çıkmadan önce aşağıdakiler eklenmelidir:

- Telefon ve e-posta doğrulaması
- Otomatik yüz/plaka bulanıklaştırma servisi
- Gelişmiş içerik moderasyonu
- Dosya virüs taraması
- Rate limiting ve brute-force koruması
- KVKK aydınlatma ve açık rıza süreçleri
- Güvenli nesne depolama
- Log yönetimi ve yedekleme
- Resmî kurum entegrasyonları
- HTTPS ve güvenli cookie zorunluluğu

## Sık karşılaşılan sorunlar

### `Veritabanı bağlantısı kurulamadı`

- WAMP MySQL servisinin çalıştığını kontrol edin.
- `.env` içindeki kullanıcı adı, şifre, port ve veritabanı adını kontrol edin.
- `database.sql` dosyasını yeniden içe aktarın.

### Sayfa açılıyor fakat görevler görünmüyor

Şu adresi açarak API cevabını kontrol edin:

```text
http://localhost/etki/backend/api/health.php
```

### Fotoğraf yüklenemiyor

- `uploads` klasörünün mevcut olduğunu kontrol edin.
- PHP `upload_max_filesize` ve `post_max_size` değerlerinin en az 8 MB olduğundan emin olun.
- `fileinfo` PHP eklentisinin açık olduğunu kontrol edin.


### `Cannot read properties of null (reading 'reset')`

Bu hata Revize 2 ile düzeltildi. Eski JavaScript dosyası tarayıcı önbelleğinde kalırsa sayfayı `Ctrl + F5` ile yenileyin.

### `404 Not Found`

Klasörün tam olarak şu konumda olduğundan emin olun:

```text
C:\wamp64\www\etki
```

Ve şu URL'yi kullanın:

```text
http://localhost/etki/frontend/
```

## Marka renkleri

- Etki yeşili: `#10B981`
- Gece laciverti: `#0D1B2A`
- Gri: `#64748B`
- Açık arka plan: `#F2F4F7`

Logo görseli, projeyle birlikte verilen marka panosundan uygulama kullanımına uygun biçimde hazırlanmıştır.

## Revize 3 — Ekip sohbeti ve yönetim sistemi

Ekipler artık yalnızca liste olarak görünmez. Bir ekibe katılan kullanıcı **Grubu aç** düğmesiyle ekip sohbetine girebilir.

### Eklenen özellikler

- Grup üyeleri yazılı mesaj ve JPG, PNG veya WEBP fotoğraf gönderebilir.
- Sohbet ekranı 5 saniyede bir otomatik yenilenir.
- Kullanıcı kendi mesajını silebilir.
- Kurucu, yönetici ve yönetici yardımcısı uygunsuz mesajları silebilir.
- Grup kurucusu otomatik olarak **Kurucu** rolünü alır.
- Kurucu; üyeleri yönetici, yönetici yardımcısı veya üye yapabilir.
- Yönetici; üyeleri yönetici yardımcısı veya üye yapabilir.
- Kurucu ve yönetici grup mesajlaşmasını üyeler için açıp kapatabilir.
- Mesajlaşma kapatıldığında kurucu, yönetici ve yönetici yardımcısı yazmaya devam edebilir.
- Yetkili kişiler üyeleri 10 dakika, 1 saat, 8 saat, 1 gün veya 1 hafta susturabilir.
- Yetkili kişiler daha düşük yetkili üyeleri ekipten çıkarabilir.
- Kurucu dışındaki üyeler ekipten ayrılabilir.
- Fotoğraflar `uploads/YIL/AY` klasörüne kaydedilir ve PHP gibi çalıştırılabilir dosyalar engellenir.

### Mevcut kurulumu Revize 3'e yükseltme

Mevcut görevleri ve kullanıcıları silmemek için `database.sql` dosyasını tekrar içe aktarmayın.

1. Mevcut proje klasörünüzü yedekleyin.
2. ZIP içindeki `backend` ve `frontend` klasörlerini mevcut klasörlerle değiştirin.
3. ZIP içindeki güncel `migration.sql` dosyasını phpMyAdmin üzerinden mevcut `etki_db` veritabanına içe aktarın.
4. `uploads` klasörünün yazılabilir olduğundan emin olun.
5. Tarayıcıda `Ctrl + F5` ile önbelleği temizleyerek yenileyin.

Yeni kurulum yapıyorsanız yalnızca güncel `database.sql` dosyasını içe aktarmanız yeterlidir; ardından ayrıca `migration.sql` çalıştırmanız gerekmez.

### Rol yetkileri

| Rol | Sohbeti aç/kapat | Rol verme | Üye susturma/çıkarma | Her mesajı silme |
|---|---|---|---|---|
| Kurucu | Evet | Yönetici, yardımcı, üye | Kendisinden düşük tüm roller | Evet |
| Yönetici | Evet | Yardımcı, üye | Kendisinden düşük roller | Evet |
| Yönetici yardımcısı | Hayır | Hayır | Normal ve eski ekip rolleri | Evet |
| Üye | Hayır | Hayır | Hayır | Yalnızca kendi mesajı |

> Grup sohbetinde telefon numarası, kesin konum, özel adres veya başka kişilerin kişisel bilgileri paylaşılmamalıdır. Fotoğraflardaki yüz ve plakalar yüklemeden önce bulanıklaştırılmalıdır.


## Revize 4

- Grup sohbetinde bir fotoğraf gönderildikten sonra mesaj gönderiminin kilitlenmesi giderildi.
- Fotoğraf alanı her gönderimden sonra güvenli biçimde yeniden oluşturulur.
- Mesaj gönderilirken otomatik sohbet yenilemesi geçici olarak durdurulur.
- Çift gönderimi engelleyen gönderim durumu eklendi.

---

## Revize 5 — Harita ve mesajlaşma güncellemesi

Bu sürümde:

- Grup sohbetinde fotoğraf gönderdikten sonra form kilitlenmeden sınırsız metin veya fotoğraf gönderilebilir.
- Enter mesajı gönderir, Shift+Enter yeni satır açar.
- Grup sohbeti 5 saniyede bir, birebir sohbet 4 saniyede bir yenilenir.
- Ekip üye listesindeki **Özel mesaj** düğmesiyle aynı ekipteki iki aktif kullanıcı birebir konuşabilir.
- Birebir konuşmalarda metin ve JPG, PNG veya WEBP fotoğraf gönderilebilir.
- Görev oluştururken enlem ve boylam elle yazılmaz.
- **Şu anki konumumu ekle** düğmesi veya **açık adres arama** alanı kullanılır.
- Aktif, süresi dolmamış ve henüz tamamlanmamış görevler haritada gösterilir.
- Kanıt gönderilince görev doğrulama aşamasına geçtiği için haritadaki aktif görev işaretçisi kaldırılır.
- Süresi dolan görevler otomatik olarak haritada görünmez ve yeniden kabul edilemez.
- Hayvan görevlerinde harita noktası isteğe bağlıdır; eklendiğinde koordinat yaklaşık 1 kilometrelik bölgeye yuvarlanır.

### Mevcut kurulumu Revize 5'e yükseltme

1. Mevcut `.env`, `uploads` klasörü ve MySQL veritabanını yedekleyin.
2. ZIP içindeki `backend` ve `frontend` klasörlerini mevcut klasörlerin üzerine kopyalayın.
3. phpMyAdmin'de `etki_db` veritabanını seçin.
4. Yalnızca güncel `migration.sql` dosyasını içe aktarın.
5. `database.sql` dosyasını tekrar içe aktarmayın; bu dosya sıfırdan kurulum içindir ve mevcut verileri siler.
6. Tarayıcıda `Ctrl + F5` ile zorla yenileyin.

### Açık adres araması çalışmıyorsa

Adres arama OpenStreetMap adres servisine internet üzerinden bağlanır. WAMP içindeki PHP cURL uzantısının açık olması önerilir:

1. WAMP simgesine tıklayın.
2. `PHP > PHP extensions` bölümünü açın.
3. `php_curl` uzantısını etkinleştirin.
4. WAMP servislerini yeniden başlatın.

Tarayıcı konumu `http://localhost` üzerinde kullanılabilir. Tarayıcı konum iznini reddettiyseniz adres çubuğundaki izin simgesinden localhost için konum iznini açın.

## Revize 6 — Sohbet düzeltmesi ve Mesajlar ekranı

- Grup sohbetinde fotoğraf mesajı gönderildiğinde mesaj yazma alanı artık ekranın altında sabit kalır.
- Mesaj listesi kendi içinde kayar; büyük fotoğraflar yazma alanını ekran dışına itemez.
- Fotoğraf seçme alanı her gönderimden sonra yeni bir dosya alanıyla yeniden oluşturulur. Böylece fotoğraftan sonra tekrar metin veya fotoğraf gönderilebilir.
- Birebir sohbetlerde de aynı dayanıklı gönderim akışı kullanılır.
- Sol menüye **Mesajlar** bölümü eklendi.
- Daha önce birebir mesajlaşılan kişiler, son mesaj önizlemesi, ekip adı ve son mesaj tarihiyle listelenir.
- Sohbete listeden tekrar girilebilir. İki kişi artık aynı aktif ekipte değilse geçmiş kayıt listede görünür ancak yeni mesaj gönderilemez.

Mevcut projeyi güncellerken `backend` ve `frontend` klasörlerini değiştirin, ardından güncel `migration.sql` dosyasını phpMyAdmin üzerinden bir kez çalıştırın. `database.sql` dosyasını mevcut veritabanının üzerine yeniden aktarmayın.
