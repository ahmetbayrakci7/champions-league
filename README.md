<div align="center">

# ⭐ Insider One Champions League

**Gerçek kulüp ve oyuncu verisiyle çalışan, UEFA Şampiyonlar Ligi formatında lig + eleme simülasyonu**

Laravel 12 · Vue 3 · MySQL 8 · EA SPORTS FC verisi

</div>

---

## 📋 İçindekiler

- [Hakkında](#-hakkında)
- [Öne Çıkan Özellikler](#-öne-çıkan-özellikler)
- [Teknoloji Yığını](#-teknoloji-yığını)
- [Gereksinimler](#-gereksinimler)
- [Kurulum](#-kurulum)
- [Çalıştırma](#-çalıştırma)
- [Testler](#-testler)
- [API Uç Noktaları](#-api-uç-noktaları)
- [Proje Yapısı](#-proje-yapısı)
- [Nasıl Çalışır?](#-nasıl-çalışır)
- [Sık Sorulanlar](#-sık-sorulanlar)

---

## 🎯 Hakkında

32 gerçek kulübün katıldığı bir Şampiyonlar Ligi turnuvasını uçtan uca simüle eder:
torbalı kura → 8 grup × çift devreli lig → Son 16'dan finale kadar eleme. Maç sonuçları
takım gücüne (EA SPORTS FC kadrolarından türetilir) dayanır; dakika-dakika anlatım, oyuncu
reytingleri, kartlar, sakatlıklar, cezalar ve şampiyonluk tahminleri içerir.

> Maç satırları tek doğruluk kaynağıdır: puan tablosu, istatistikler ve tahminler hep maçlardan
> türetilir. Bir skoru düzenlediğinizde tüm sistem otomatik ve tutarlı şekilde yeniden hesaplanır.

---

## ✨ Öne Çıkan Özellikler

- 🏆 **Tam UCL formatı** — 32 kulüp, 4 torba, 8 grup; kullanıcının çektiği kura (aynı ülke aynı grupta olamaz)
- ⚽ **Gerçekçi simülasyon** — lojistik (ELO benzeri) güç ölçeği + çift-Poisson; zayıfın küçük sürpriz şansı korunur
- 📅 **Gerçek takvim** — maçlar Salı/Çarşamba'ya yayılır, tarih + saat ile
- 🎮 **OSM tarzı maç motoru** — dakika-dakika olaylar (gol/kart/sakatlık/değişiklik), ilk 11, 10 üzerinden oyuncu reytingi
- 🥇 **Eleme aşaması** — Son 16 → Çeyrek → Yarı → Final; çift maç, uzatma + penaltı; tur tur veya finale kadar oynatma
- 🟨 **Disiplin & sakatlık** — kırmızı/kümülatif sarı cezaları (ÇF sonrası sarı affı), süreli sakatlıklar
- 📊 **İstatistik panoları** — gol/asist krallığı, en yüksek reyting, kart ve takım liderlikleri
- 🔮 **Şampiyonluk tahmini** — son 3 haftada Monte Carlo ile grup başına kazanma yüzdeleri
- ✏️ **Skor düzenleme** — kartlar/sakatlıklar korunur, yalnız goller yeniden üretilir; eleme düzenlemesi bracket'i günceller
- 🌍 **Anlık TR/EN** — maç anlatımları dahil her metin; sayfa yenilenmeden geçiş
- 📱 **Responsive premium tasarım** — "Starlight Anthem" UCL gece teması, mobil uyumlu

---

## 🛠 Teknoloji Yığını

| Katman | Teknoloji |
|---|---|
| Backend | PHP 8.2, Laravel 12 |
| Veritabanı | MySQL 8 |
| Frontend | Vue 3 (`<script setup>`), Pinia, Vite |
| UI | SweetAlert2, özel CSS tema |
| Test | PHPUnit (Unit + Feature) |
| Veri | EA SPORTS FC resmi ratings (repoya bundle'lı snapshot'lar) |

---

## 📦 Gereksinimler

- **PHP** ≥ 8.2 (`pdo_mysql` eklentisi)
- **Composer** 2.x
- **Node.js** ≥ 20 + npm
- **MySQL** 8.x

---

## 🚀 Kurulum

### 1. Depoyu klonla
```bash
git clone https://github.com/<kullanıcı-adı>/insider-champions-league.git
cd insider-champions-league
```

### 2. Bağımlılıkları yükle
```bash
composer install
npm install
```

### 3. Ortam dosyası
```bash
cp .env.example .env
php artisan key:generate
```

### 4. Veritabanını oluştur
MySQL'de uygulama ve test veritabanlarını + bir kullanıcı oluşturun:
```sql
CREATE DATABASE insider_league       CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE insider_league_test  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'insider'@'localhost' IDENTIFIED BY 'GUCLU_BIR_SIFRE';
GRANT ALL PRIVILEGES ON insider_league.*      TO 'insider'@'localhost';
GRANT ALL PRIVILEGES ON insider_league_test.* TO 'insider'@'localhost';
FLUSH PRIVILEGES;
```

`.env` dosyasında veritabanı ayarlarını güncelleyin:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=insider_league
DB_USERNAME=insider
DB_PASSWORD=GUCLU_BIR_SIFRE
```

### 5. Tabloları oluştur ve verileri yükle
```bash
php artisan migrate --seed
```
> 32 kulüp + ~800 oyuncu (repoya bundle'lı EA verisi) yüklenir. **~15 saniye sürmesi normaldir.**

### 6. (Opsiyonel) Kadroları ea.com'dan tazele
```bash
php artisan league:import-squads --refresh
```

---

## ▶️ Çalıştırma

### Geliştirme
İki terminal:
```bash
npm run dev          # Vite (HMR)
php artisan serve    # http://localhost:8000
```

### Production / hızlı demo
```bash
npm run build
php artisan serve    # http://localhost:8000
```
> `@vite` derlenmiş varlıkları otomatik kullanır; `npm run dev` gerekmez. En akıcı deneyim için bu yol.

Tarayıcıdan **http://localhost:8000** → **RUN THE DRAW** ile kurayı çek, **PLAY WEEK** / **PLAY ALL**
ile maçları oynat, takıma/maça tıklayarak detayları gör.

---

## 🧪 Testler

```bash
php artisan test                      # tüm test paketi (83 test)
php artisan test --testsuite=Unit     # yalnız unit (DB'siz)
php artisan test --testsuite=Feature  # API/feature (MySQL test DB)
```
Feature testleri `insider_league_test` veritabanını kullanır (yukarıda oluşturuldu).

---

## 🔌 API Uç Noktaları

| Method | Uç | Açıklama |
|---|---|---|
| `GET`  | `/api/league` | Lig durumu (potlar / gruplar + tablo + fikstür + tahmin + eleme) |
| `POST` | `/api/league/draw` | Kurayı çek, fikstürleri üret |
| `POST` | `/api/league/play-week` | Sıradaki maç haftasını oynat |
| `POST` | `/api/league/play-all` | Kalan tüm haftaları oynat |
| `POST` | `/api/league/reset` | Sıfırla → potlara dön |
| `POST` | `/api/knockout/advance` | Eleme: R16 kurası / sıradaki leg |
| `POST` | `/api/knockout/advance-all` | Elemeyi finale kadar oynat |
| `GET`  | `/api/stats` | Oyuncu + takım liderlik tabloları |
| `GET`  | `/api/teams/{team}` | Kadro + turnuva formu |
| `GET`  | `/api/games/{game}` | Maç detayı (olasılık, ilk 11, reyting, olay akışı) |
| `PUT`  | `/api/games/{game}` | Skor düzenle |

---

## 📁 Proje Yapısı

```
app/
├── Models/              Team, Player, Group, Game, Tie, MatchEvent, Appearance, Injury
├── Services/
│   ├── Contracts/       Interface'ler (Draw, Fixture, Scheduler, Simulator, Engine, ...)
│   ├── DrawService                 # Torbalı kura, aynı-ülke kısıtı
│   ├── FixtureGenerator            # Berger çift devreli round-robin
│   ├── MatchScheduler              # Salı/Çarşamba dağılımı
│   ├── MatchSimulator              # Lojistik güç + Poisson xG + olasılıklar
│   ├── LineupSelector              # En iyi 11 + yedekler
│   ├── MatchEngine                 # Dakika-dakika olaylar + reytingler + rescore
│   ├── StandingsCalculator         # PL sıralama
│   ├── ChampionshipPredictor       # Monte Carlo (optimize)
│   ├── KnockoutService             # Eleme: kura, çift maç, uzatma/penaltı
│   ├── SuspensionService           # Kart cezaları
│   ├── InjuryService               # Sakatlıklar
│   ├── TeamFormService             # Form çarpanı
│   ├── StatsService                # Liderlik tabloları
│   ├── LeagueService               # Orkestrasyon
│   └── SquadImporter               # EA verisi → oyuncular
├── Console/Commands/ImportSquads   # league:import-squads
└── Http/Controllers/Api/           # League, Knockout, Team, Game, Stats

resources/js/
├── components/          App, LeagueTable, MatchCenter, KnockoutBoard, StatsBoard, MatchModal, ...
├── stores/league.js     Pinia merkezi durum
└── i18n.js              TR/EN sözlük

database/
├── data/ea/             32 kulübün EA kadro snapshot'ları (bundle'lı)
├── migrations/
└── seeders/
```

---

## ⚙️ Nasıl Çalışır?

**Maç sonucu** hiçbir yerde elle seçilmez; olasılık dağılımından türer:

1. İki takımın **etkin gücü** hesaplanır (kadro gücü + ev avantajı + taraftar + form).
2. Güç farkı, **lojistik eğriyle** gol payına dönüşür — küçük farklar rekabetçi, büyük farklar baskın.
3. Her tarafın **beklenen golü (xG)** rakip kalecisiyle kısılır; bir taban değer zayıfın şansını korur.
4. Gol sayısı **Poisson dağılımından** örneklenir → güçlü takım çoğunlukla kazanır ama sürpriz olasıdır.
5. **Maç motoru** skoru alır, dakikalara olay serpiştirir, oyunculara reyting verir.

**Şampiyonluk tahmini**: kalan maçlar 1000 kez simüle edilir; takımın zirvede bittiği oran = yüzdesi.

Ayar düğmeleri `config/league.php` içindedir (gol ortalaması, güç eğrisi sıcaklığı, tahmin iterasyonu vb.).

---

## ❓ Sık Sorulanlar

**`migrate --seed` neden uzun sürüyor?**
32 kulübün EA JSON kadrosu içe aktarılıyor (~800 oyuncu). ~15 saniye normaldir.

**İnternet olmadan çalışır mı?**
Evet. Kadro verisi repoya bundle'lı; `--refresh` yalnız tazelemek isterseniz internet ister.

**`pdo_sqlite` yok hatası alıyorum.**
Testler MySQL test veritabanına ayarlıdır (`phpunit.xml`); sqlite gerekmez, `insider_league_test`'i oluşturmanız yeterli.

**Maç sonuçlarını düzenleyebilir miyim?**
Evet. Maç detayında "Skoru Düzenle" ile; kartlar/sakatlıklar korunur, tablo otomatik yeniden hesaplanır.
Grup maçları kura çekilince, eleme maçları bir sonraki tur başlayınca kilitlenir.

---

<div align="center">

PHP (Laravel) · Vue.js · OOP · Otomatik testler — case study gereksinimlerinin tamamı + ekstralar.

</div>
