<div align="center">

![Etki GitHub Kapağı](docs/assets/etki-github-banner.png)

# Etki — İyilik İz Bırakır

**Gerçek dünyadaki küçük iyilikleri, erişilebilirlik kontrollerini ve mahalle sorunlarını topluluk görevlerine dönüştüren web uygulaması.**

`PHP` · `MySQL` · `JavaScript` · `WAMP`

[GitHub Profilim](https://github.com/melih357) · [LinkedIn](https://www.linkedin.com/in/melihefeercan/)

</div>

## 🌱 Proje hakkında

Etki, insanların çevrelerindeki ihtiyaçları görünür kılmasını ve küçük katkılarla topluluklarına destek olmasını amaçlayan **mobil uyumlu bir MVP/prototip** projesidir. Misyonlar, mahalle sorunları, soru-cevap ve ekip çalışması tek bir deneyimde buluşur.

## ✨ Prototip kapsamı

- Kullanıcı kaydı ve oturum açma
- Görev oluşturma, kategoriyle keşfetme, göreve katılma
- Fotoğraf kanıtı ve topluluk doğrulaması akışları
- Güncel soru-cevap alanı
- Ekip oluşturma ve ekiplere katılma
- XP, seviyeler ve rozetler
- Yaklaşık konumla OpenStreetMap/Leaflet görev haritası
- Göreve bağlı mesajlaşma ve yönetim bölümü

> **Durum:** Yerel geliştirme için hazırlanmış MVP. Canlı sistemde kullanıma hazır veya güvenlik açısından denetlenmiş bir ürün değildir.

## 🛠️ Teknolojiler

| Katman | Teknoloji |
|---|---|
| Sunucu | PHP 8.1+ |
| Veritabanı | MySQL / MariaDB |
| Arayüz | HTML, CSS, JavaScript |
| Yerel ortam | WAMP / Apache |
| Harita | Leaflet + OpenStreetMap (internet gerektirir) |

## 🚀 Yerel kurulum — özet

1. Proje klasörünü `C:\wamp64\www\etki` konumuna yerleştirin.
2. WAMP üzerinde Apache ve MySQL'i çalıştırın.
3. phpMyAdmin üzerinden `database.sql` dosyasını içe aktarın.
4. Yerel `.env` dosyasını kendi veritabanı bilgilerinizle yapılandırın; bu dosyayı GitHub'a yüklemeyin.
5. `http://localhost/etki/frontend/` adresini açın.
6. Sağlık kontrolü: `http://localhost/etki/backend/api/health.php`.

> `migration.sql` ilk kurulum için zorunlu değildir; gerektiğinde `database.sql` sonrasında uygulanır. Ayrıntılar için [kurulum notları](docs/ORIJINAL_KURULUM_NOTLARI.md) dosyasına bakın.

## 🖼️ Görseller

Üstteki kapak uygulamanın orijinal logo/renklerinden hazırlanmış tanıtım görselidir; çalışan uygulamanın ekran görüntüsü değildir. Gerçek ekran görüntüleri uygulama çalıştırılıp doğrulandıktan sonra eklenebilir.

## 🔐 Güvenlik ve yayın notu

- Canlı ortamda örnek hesapları kaldırın ve tüm parolaları değiştirin.
- `.env`, kullanıcı yüklemeleri ve özel yapılandırmalar herkese açık depoya eklenmemelidir.
- İçerik moderasyonu, KVKK, dosya güvenliği, HTTPS ve hız sınırlama gibi ek çalışmalar gereklidir.
- Bu proje resmî yardım hizmeti veya acil durum desteği yerine geçmez.

## 👨‍💻 Geliştirici

**Melih Efe Ercan** — [GitHub](https://github.com/melih357) · [LinkedIn](https://www.linkedin.com/in/melihefeercan/)
