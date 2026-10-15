# Oasis Next

## Arah visual

Oasis Next adalah workspace operasional untuk tim sales properti. Antarmuka dibuat terang, ramah, dan mudah dipindai saat dipakai untuk pekerjaan lapangan. Struktur workspace modern menjadi acuan ritme halaman, bukan sesuatu yang disalin secara pixel-perfect.

Prinsip visual:

- Latar terang dengan kontras yang cukup untuk data operasional.
- Surface putih, border tipis, dan radius besar menjadi dasar komponen.
- Aksi utama terlihat jelas tanpa membuat semua elemen menjadi tombol.
- Judul memakai sentence case dan tracking rapat.
- Animasi hanya muncul sebagai respons aksi pengguna dan mengikuti `prefers-reduced-motion`.

## Identitas merek

Oasis Next memakai cincin sebagai metafora kolam oasis, blok isometrik sebagai kavling, dan matahari terbit sebagai awal baru. Wordmark tidak digambar ulang: gunakan berkas SVG resmi yang sudah berupa outline Poppins Bold.

### Berkas dan pemakaian

| Berkas | Lokasi | Pemakaian |
| --- | --- | --- |
| `oasis-next-logo.svg` | `resources/brand/`, `public/brand/` | Lockup terang untuk inline Blade, cetak, dan PDF yang mendukung SVG |
| `oasis-next-logo-dark.svg` | `resources/brand/`, `public/brand/` | Lockup dengan wordmark terang pada latar gelap |
| `oasis-next-mark.svg` | `resources/brand/`, `public/brand/` | Ikon terang untuk rail, loading, error, dan favicon/PWA |
| `oasis-next-mark-dark.svg` | `resources/brand/`, `public/brand/` | Ikon ink di atas latar lime atau lime-soft |
| `favicon.svg`, `favicon.ico`, PNG favicon | `public/` | Favicon browser dan ikon perangkat |
| `android-chrome-*.png`, `maskable-512x512.png`, `site.webmanifest` | `public/` | PWA/install prompt |
| `og-image.png` | `public/` | Open Graph/Twitter card 1200×630 |
| `email-mark.png` | `public/brand/` | Email HTML, 48px tampilan, tanpa SVG |
| `oasis-next-logo-sheet.png` | `docs/brand/` | Lembar acuan presentasi, bukan aset runtime |

Logo horizontal dipakai di header panel mobile dan halaman auth; panel desktop memakai ikon 32px dengan teks hidup `Oasis Next` dan subjudul `Workspace operasional`. Rail memakai ikon 40px, sedangkan error/loading memakai ikon mandiri. Dashboard dan halaman modul tidak menambah logo pada header global.

### Aturan pakai

- Ukuran minimum ikon 16px dan lockup horizontal 96px lebar; sediakan ruang kosong sekurang-kurangnya setebal cincin ikon.
- Jangan memiringkan, meregangkan, menggambar ulang, mengetik ulang wordmark, atau mengganti warna blok dan matahari.
- Ikon gelap dengan kotak ink hanya dipakai di atas latar lime/lime-soft agar tidak menyatu dengan latar gelap.
- Wordmark inline memakai `--c-brand-oasis` dan `--c-brand-next`: terang `#1d2219`/`#5f9a2a`, gelap `#ecefe6`/`#9ccc4e`.
- Regenerasi aset dengan `npm run brand:build`; validasi dengan `npm run brand:verify`. Generator membersihkan blok `<metadata>` provenance dari SVG sumber saat menyalin hasil.

### Titik pemasangan

- Rail desktop: mark 40px dan tautan dashboard berlabel `Oasis Next, buka dashboard`.
- Panel navigasi desktop: mark 32px dengan wordmark teks hidup dan subjudul; drawer mobile: lockup horizontal.
- Auth guest: lockup 40px, tagline, dan animasi masuk yang mengikuti reduced motion.
- Error 401/402/403/404/419/429/500/503: mark 56px di atas judul.
- Loading Spatial Workspace: mark dengan denyut halus yang dapat dimatikan reduced motion.
- Email HTML: PNG mark 48px plus teks HTML `Oasis Next`; email tidak memakai SVG.
- Print/PDF laporan: lockup terang pada kop; tema pengguna tidak memengaruhi output cetak.
- Browser/PWA/social metadata: favicon, Apple touch icon, Android/maskable icons, manifest, OG image, dan offline page.

### Pengecualian nama lama

Kemunculan `Marison` yang sengaja dipertahankan bukan merek UI: nama paket/deskripsi/keyword di `composer.json`, namespace cache service worker `marison-*` untuk kompatibilitas cache, komentar nama database di `.env`, fixture/seeder dan nilai project/lokasi pada test, serta catatan historis audit di dokumen ini. Domain email dan data database juga tetap tidak disentuh bila muncul di lingkungan deployment. Semua teks merek yang tampil di UI, head, email, offline page, manifest, dan aset sudah memakai `Oasis Next`.

## Halaman auth

Halaman login dan halaman auth lain yang memakai `x-guest-layout` menggunakan konsep “kavling yang dibangun saat mengisi form”. Layout guest menempatkan kartu form selebar 420px di kiri dan papan 7×7 Canvas 2D di kanan pada desktop; tablet menumpuk papan redup di atas kartu dan mobile memakai strip papan 5×5 yang ringkas. Jika JavaScript atau Canvas tidak tersedia, papan disembunyikan tanpa mengganggu form HTML biasa.

Grid memakai proyeksi isometrik datar dan token tema yang sama dengan Spatial Workspace. Jumlah blok menyala adalah `min(49, panjang email + panjang kata sandi)`; hanya panjang nilai yang dipakai, bukan isi, dan tidak ada isi form yang disimpan, dikirim, atau dicatat oleh animasi. Event `input`, `change`, `blur`, serta pemeriksaan autofill tertunda memperbarui state. Hover desktop hanya mengangkat blok sekitar kursor dan tidak mengubah jumlah blok.

Submit login tetap berjalan ke route Laravel biasa. Progressive enhancement hanya menambahkan indikator “Memeriksa…” dan menulis flag sekali pakai `sessionStorage['oasis:welcome']`. Layout aplikasi membaca flag tersebut untuk menampilkan overlay sambutan singkat setelah redirect; flag langsung dihapus, overlay bisa dilewati, dan seluruh animasi dimatikan ketika `prefers-reduced-motion: reduce` aktif.

Input auth tetap memakai label, CSRF, autocomplete, validasi server, pesan error, throttle, serta redirect yang sudah ada. Tombol password hanya mengubah visibilitas di sisi browser, memakai `aria-pressed`, dan tidak mengubah nama field. Grid `aria-hidden` serta tidak menjadi target fokus; fokus awal tetap menuju field auth yang relevan.

## Token warna dan radius

| Token | Nilai | Peran |
| --- | --- | --- |
| `canvas` | `#ecece9` | Latar halaman |
| `surface` | `#ffffff` | Card, panel, tabel, dan form |
| `ink` | `#1d2219` | Rail, teks utama, dan tombol sekunder |
| `muted` | `#8b9086` | Teks sekunder dan ikon pendukung |
| `line` | `#e8e8e3` | Border dan divider |
| `lime` | `#9ccc4e` | Aksi utama dan highlight aktif |
| `lime-dark` | `#4a7a22` | Hover, teks aksen, dan tahap Akad; dipilih agar teks aksen di atas putih memenuhi WCAG AA |
| `lime-soft` | `#e8f4cf` | Item aktif dan highlight lembut |
| `orange` | `#f4a65c` | Tahap UTJ dan perhatian ringan |
| `yellow` | `#f3e35f` | Tahap Cek SLIK dan peringatan ringan |
| `stage-face-to-face` | `#c9e58f` | Tahap Tatap Muka |
| `stage-site-visit` | `#9ccc4e` | Tahap Cek Lokasi |
| `stage-slik` | `#f3e35f` | Tahap Cek SLIK |
| `stage-utj` | `#f4a65c` | Tahap UTJ |
| `stage-akad` | `#4a7a22` | Tahap Akad |
| `stage-freelance` | `#d3d6cd` | Tahap Jadi Freelance |

Warna semantik untuk sukses, peringatan, error, dan aksi destruktif tetap terpisah dari palet brand. Jangan memakai warna tahap sebagai pengganti warna validasi.

## Tema (terang/gelap)

Tema memakai CSS custom property berbentuk channel RGB. `:root` adalah tema terang; `:root[data-theme="dark"]` adalah tema gelap. Utility Tailwind semantic (`bg-surface`, `text-muted`, `border-line`, dan alias lama) membaca token tersebut sehingga migrasi nama class dapat dilakukan bertahap tanpa mengubah markup existing.

| Token | Terang | Gelap |
| --- | --- | --- |
| `canvas` | `#ecece9` | `#0f130d` |
| `rail` | `#1d2219` | `#080b07` |
| `surface` | `#ffffff` | `#181d16` |
| `surface-2` | `#f4f4f1` | `#1f251c` |
| `surface-3` | `#ffffff` | `#232a20` |
| `row-hover` | `#fafaf6` | `#1c2219` |
| `row-selected` | `#f3f9e4` | `#1e2a16` |
| `line` | `#eeeee9` | `#262d22` |
| `line-strong` | `#e3e3dd` | `#2d3528` |
| `text` | `#1d2219` | `#ecefe6` |
| `muted` / `subtle` | `#8b9086` / `#7c8176` | `#8f988a` / `#8f988a` |
| `accent` | `#9ccc4e` | `#9ccc4e` |
| `accent-soft` | `#e8f4cf` | `rgba(156,204,78,.14)` |
| `accent-text` | `#3f6f1d` | `#b6dd78` |
| `accent-dark` | `#5f9a2a` | `#5f9a2a` |
| `on-accent` | `#1d2219` | `#10150d` |
| `danger` | `#b4472f` | `#ff9a80` |
| `inverse` / `inverse-text` | `#1d2219` / `#ffffff` | `#ecefe6` / `#10150d` |
| `checkbox` | `#cfd2c8` | `#3a4433` |

### Mekanisme

- `resources/js/theme.js` menyimpan `light`, `dark`, atau `system` pada `localStorage.theme`; default-nya `system`.
- Script inline diletakkan sebelum stylesheet pada layout authenticated dan guest untuk menetapkan `data-theme` serta `color-scheme` tanpa FOUC.
- Perubahan antar-tab memakai event `storage`; mode Sistem mengikuti `matchMedia('(prefers-color-scheme: dark)')` secara langsung.
- Perubahan efektif mengirim `window` event `themechange` dengan `detail.theme` bernilai `light` atau `dark`. Spatial Workspace akan memakai event yang sama pada tahap 5.
- Dropdown profil menyediakan Terang/Gelap/Sistem; tombol matahari/bulan di global bar mengubah tema efektif antara terang dan gelap.
- Transisi warna dibatasi 150ms dan dilepas setelah 200ms; `prefers-reduced-motion` menonaktifkannya. Fokus keyboard, selection, autofill, dan scrollbar membaca token.

### Role, status, dan Spatial

Chip role/status dan tahap lead tidak membuat warna baru per halaman. Varian gelap memakai tint transparan dengan teks aksen; role yang belum dikenal memakai fallback palet yang sama. Spatial Workspace akan memiliki `SCENE_THEMES.light` dan `SCENE_THEMES.dark`, mengubah material, tekstur, lampu, label, dan HUD tanpa membuat ulang geometri atau memutus drag/pending assignment.

### Strategi alias dan pengecualian

- Class legacy seperti `bg-white`, `text-gray-*`, `border-gray-*`, `bg-ink`, dan token lama Marison yang masih ditemukan diarahkan ke semantic variable terlebih dahulu; rename markup dilakukan bertahap.
- Orange, yellow, dan lime tetap menjadi warna identitas/status yang tidak berubah antar tema. Logo memakai variabel brand khusus agar wordmark ikut tema tanpa mengubah SVG resmi.
- Print/PDF/email tetap terang dan tidak ikut selector tema. Warna Spatial/komponen legacy yang belum dimigrasikan dicatat per tahap, bukan diberi `dark:` acak pada setiap elemen.

### Status implementasi tema

- **Tahap 1 selesai:** token, konfigurasi Tailwind selector, FOUC guard, localStorage/system/storage synchronization, event `themechange`, kontrol tema pada global bar, guest/auth shell, rail, panel navigasi, search, chip tim, dan dropdown profil. Login terang/gelap desktop/mobile tersimpan di `docs/screenshots/tema-login-*.png`.
- **Tahap 2 selesai:** komponen tabel, toolbar, filter, bulk bar, row action, pagination, role/status chip, empty state, form section, modal, tombol utama/destruktif, serta halaman Pengguna, Data konsumen, dan Promo memakai token tema. Filter mobile dan menu row action diverifikasi pada dua tema; screenshot tersimpan sebagai `docs/screenshots/tema-{users,consumers,promotions}-{terang,gelap}-{1440,390}.png`.
- **Tahap 3 selesai:** Dashboard, Leads, Monitoring Lead, Agenda sales, Laporan performa, Garansi, dan seluruh halaman Proses penjualan memakai token untuk kartu statistik, surface, tabel, input, ringkasan, dialog, status, dan form. Verifikasi tema terang/gelap dilakukan pada `1440×900` dan `390×844` tanpa overflow; screenshot tersimpan sebagai `docs/screenshots/tema-stage3-*.png`.
- **Tahap 4 selesai:** Spatial Workspace memakai `SCENE_THEMES` untuk material Three.js, lighting, pawn role color, hierarchy link, drag-drop highlight, CSS2D label dot, HUD, panel, fallback, dan background. Listener `themechange` memperbarui material/label secara in-place tanpa membuat ulang geometri; light/dark diverifikasi live pada desktop dan mobile. Screenshot tersimpan sebagai `docs/screenshots/tema-stage4-spatial-{light,dark}-{1440,390}.png`.
- **Tahap 5 selesai:** audit akhir token, kontras, protected path, responsive overflow, system theme, print-only light mode, build, cache, formatter, dan regression suite. CSS tidak lagi memiliki hex literal; remaining legacy classes/print colors/welcome template dicatat sebagai compatibility atau non-themed surface. Pixel-diff reference belum tersedia dan performance dataset staging belum dapat dijalankan tanpa fixture resmi.
- **Tahap 6 selesai secara lokal:** regression authorization/validation Spatial dan modul terkait lulus (`35 tests, 136 assertions`), audit accessible name pada Dashboard, Leads, dan Spatial menemukan `0` kontrol visible tanpa nama, Spatial tetap lazy-loaded dengan main bundle sekitar `50 kB` dan chunk Spatial sekitar `554 kB` minified (`141 kB` gzip), serta runtime Spatial tetap tanpa overflow pada desktop. Dataset staging 300 user dan baseline pixel-diff resmi masih diblokir oleh fixture/reference yang belum tersedia.
- **Tahap 7 selesai secara lokal:** full suite `157 tests, 583 assertions`, build/cache/Pint/diff check lulus, 14 route kritis merespons `200` pada light/dark tanpa overflow desktop, Spatial memuat 18 label pada kedua tema, dan route protected tetap terdaftar tanpa perubahan. Staging dataset, baseline pixel-diff, serta persetujuan merge/release masih menunggu resource resmi.
- **Tahap 8 direncanakan:** staging/production handoff: jalankan release gate dengan fixture resmi, simpan baseline visual, monitor bundle/WebGL setelah deploy, lalu lakukan cleanup alias legacy hanya jika seluruh view operasional sudah disetujui untuk rename. Tidak ada route/controller/request/model/migration/RBAC baru.

Radius:

- Card dan panel utama: `24px`.
- Kontrol dan tombol: `14px` sampai `16px`.
- Chip dan pill: `9px` sampai `10px`.
- Avatar: bulat penuh.

## Tipografi

- Gunakan Poppins dengan bobot 400, 500, 600, dan 700.
- Heading memakai bobot 600 atau 700 dengan tracking rapat.
- Label menggunakan sentence case. Hindari label uppercase sebagai dekorasi.
- Ukuran area sentuh minimum 40px.
- Fokus keyboard harus terlihat jelas.

## Workspace shell

- Root shell memakai `h-dvh overflow-hidden`, padding `14px`, dan gap `14px` di atas `canvas`.
- Rail ikon kiri berukuran `66px`, radius `24px`, padding vertikal `16px`, dan memakai warna `ink`.
- Tombol rail berukuran `42px` dengan radius `14px`; logo berukuran `40px` dengan radius `13px`.
- Rail menyimpan ikon utama. Item aktif memakai latar gelap dan ikon `lime`.
- Panel navigasi berukuran `216px`, radius `24px`, padding `14px`, dan memakai surface putih.
- Item menu memiliki tinggi sekitar `35px`, padding horizontal `10px`, padding vertikal `8px`, dan font `13px`. Scrollbar panel tipis hanya terlihat saat hover.
- Struktur menu existing tetap dipakai, termasuk grup Pengaturan dan submenu Spatial workspace, Cabang, Proyek, serta Kavling.
- Global bar transparan tanpa border bawah, tinggi target `44px`, dengan breadcrumb, search putih `340px × 40px` dengan hint `Ctrl K`, notifikasi, kode tim, dan pill profil. Judul, deskripsi, serta aksi halaman berada pada page header terpisah.
- Banner impersonation tetap ditampilkan dengan kontras tinggi dan aksi untuk kembali.
- Pada lebar di bawah `1280px`, panel navigasi dapat diciutkan sehingga hanya rail yang tersisa. Di bawah `768px`, rail disembunyikan dan panel menjadi drawer.
- Area konten halaman memakai `overflow-y-auto` dengan scrollbar tipis `6px`; shell dan Spatial Workspace tidak ikut scroll.
- Search global tetap memakai route dan query existing. Command palette baru tidak dipakai.

## Aturan komponen

- Gunakan border tipis sebelum menambah shadow.
- Shadow hanya dipakai pada elemen melayang, seperti tooltip, panel HUD, atau objek yang sedang diseret.
- Gunakan `gap` untuk jarak antar elemen.
- Pola garis diagonal 135 derajat dengan garis putih transparansi 28 sampai 40 persen hanya dipakai pada blok data dan status.
- Aksi utama memakai `lime`. Tombol sekunder memakai surface atau `ink` sesuai konteks.
- Card memakai `x-ui.card` jika membutuhkan header, deskripsi, atau slot aksi.
- Chip tahap memakai `x-ui.stage-chip` agar warna status tetap konsisten.
- Avatar memakai `x-ui.avatar`.
- Jangan membuat interaksi tanpa route, permission, data, atau endpoint backend yang mendukungnya.

Komponen reusable yang sudah tersedia:

- `x-ui.card` untuk surface kerja.
- `x-ui.stage-chip` untuk tahap Lead.
- `x-ui.avatar` untuk avatar inisial.
- `x-ui.lead-card` untuk ringkasan Lead jika halaman membutuhkan daftar kartu.
- `x-ui.kanban-column` dipertahankan sebagai komponen legacy dan tidak dipakai pada tampilan Leads baru.
- `x-ui.toast` tetap tersedia untuk feedback halaman lain yang memiliki aksi backend, tetapi tidak dipakai untuk feedback drag-drop Leads yang dihilangkan.

## Pola halaman modul

Tahap 1 menyiapkan satu sumber markup dan token untuk migrasi seluruh halaman modul. Komponen anonymous Blade berikut menjadi fondasi bersama:

- `x-global-bar`: breadcrumb, search global `340px × 40px`, notifikasi, kode tim, dan dropdown profil.
- `x-page-header`: judul `26px`, deskripsi satu baris, aksi utama, aksi sekunder, dan tombol kembali.
- `x-table-card`: kartu konten putih radius `24px`, padding `16px`, serta badan tabel dengan scroll internal.
- `x-toolbar`, `x-search-input`, dan `x-filter-pill`: toolbar tinggi `40px` dengan form GET dan filter aktif.
- `x-data-table`: header abu muda tinggi `42px`, baris standar `58px`, atau varian padat `48px`.
- `x-row-actions`: satu aksi utama terlihat dan menu `⋯` untuk aksi tambahan.
- `x-bulk-bar`: bar mengambang gelap yang hanya tampil saat ada baris terpilih.
- `x-role-chip` dan `x-status-badge`: palet role Spatial Workspace dan status semantik yang konsisten.
- `x-pagination`: ringkasan hasil dan tombol halaman `32px`.
- `x-empty-state`: empty state ringkas dengan aksi utama atau reset filter.
- `x-form-section`: kartu form dengan grid dua kolom desktop dan satu kolom mobile.

Semua komponen menerima attribute bag dan slot eksplisit. Komponen hanya mengatur struktur/presentasi; route, query string, permission, field, dan endpoint tetap diberikan oleh halaman pemanggil.

## Mobile one-handed redesign

Redesign mobile memakai breakpoint `max-width: 767px` agar perilaku desktop dan tablet tetap menggunakan layout existing. Shell mobile memakai top bar ringkas, safe area, tinggi `100dvh`, bottom navigation lima tab, tombol aksi cepat `+`, serta bottom sheet untuk Menu, Team, dan Profile. Sheet mendukung backdrop close, tombol Escape, browser back, transisi, scroll internal, dan penguncian scroll halaman.

Tabel daftar memakai adapter `x-data-table` dengan slot kartu mobile dan fallback card layout untuk tabel legacy. Kartu mempertahankan pagination, filter GET, permission, row action, bulk selection, dan endpoint existing. Laporan performa menyediakan pilihan `Kartu`/`Tabel` yang disimpan di `localStorage`. Angka ringkasan memakai format Indonesia (`1.930`).

Form mobile menggunakan satu kolom, input minimal 16px, target sentuh minimal 44px, dan action simpan sticky di bawah dengan safe-area padding. Tema terang, gelap, dan sistem tetap membaca token yang sama; perubahan mobile hanya aktif di bawah 768px.

Artefak screenshot Tahap 6 tersedia di `docs/screenshots/stage6-login-{light,dark}-{360,390,430}.png`. Login guest diverifikasi pada `360×740`, `390×844`, dan `430×932`: HTTP `200`, tanpa horizontal overflow, input 16px, target submit 48px, dan tanpa console error. Verifikasi halaman authenticated menunggu provisioning user/database lokal; database saat ini belum memiliki user.

## Dashboard

Dashboard memakai data yang sudah dikirim oleh `DashboardController`. Elemen utama:

- Kartu statistik Lead aktif, Agenda mendatang, Agenda selesai, Konsumen, Kavling tersedia, dan Kavling terjual jika datanya tersedia.
- Daftar Lead terbaru dengan chip tahap.
- Daftar Agenda mendatang dengan empty state yang mengajak pengguna membuat agenda melalui route existing.
- Aksi cepat yang hanya muncul jika permission dan route-nya tersedia.

Dashboard tidak menampilkan Denah kavling, Performa sales, atau Pipeline lead per tahap kecuali controller existing memang mengirim data yang diperlukan.

## Leads

Tampilan utama Leads adalah tabel existing dengan gaya baru. Filter, pagination, permission, dan query existing tetap dipakai.

Tampilan yang dihilangkan dari halaman Leads:

- Kanban dan drag and drop status.
- Toast dan tombol Batalkan yang hanya terkait alur Kanban.
- Tab Kalender.
- Tab Denah.

Perubahan status Lead tetap mengikuti alur dan endpoint yang sudah ada di halaman detail atau form Lead. Tampilan tabel tidak membuat endpoint baru.

## Spatial Workspace

Spatial Workspace menggambarkan cabang, tim, dan user dalam scene 3D isometrik berbasis Three.js/WebGL. Scene memakai data global yang sudah dikirim controller dan tidak membuat query atau endpoint baru. CSS2DRenderer dipakai untuk nameplate cabang, tim, dan user.

### Sumber data dan endpoint

`SpatialWorkspaceController` mengirim data `branches`, `teams`, dan `users`, termasuk `teamIds`, `teamNames`, `globalRoles`, role assignment berbasis tim, `reportsToId`, dan jumlah anggota.

Endpoint assignment yang tersedia:

- `PATCH admin.spatial-workspace.users.update` adalah satu-satunya endpoint tulis untuk memindahkan user ke satu tim dan mengubah `reports_to_id`.
- Endpoint tersebut juga menyelaraskan `branch_id` user mengikuti branch tim tujuan.
- `PUT admin.teams.update` menerima `branch_id`, tetapi merupakan form update tim penuh. Tidak ada endpoint assignment khusus untuk memindahkan tim ke branch dari scene.
- Setiap user dikirim sebagai satu request PATCH berurutan saat Simpan perubahan ditekan. Tidak ada bulk endpoint baru.
- `window.spatialWorkspace`, `window.spatialWorkspaceCanEdit`, dan `window.spatialWorkspaceUpdateUrl` tetap menjadi kontrak data dan konfigurasi frontend.

Karena itu, scene hanya menyediakan drag and drop user ke tim. Perpindahan tim ke cabang tidak dibuat sebagai interaksi baru.

### Model visual

- Proyeksi memakai kamera orthographic dengan sudut isometrik, bukan renderer SVG manual.
- Geometri platform memakai `RoundedBoxGeometry`; material dibuat datar, berpola, dan hemat draw call untuk dekorasi pohon.
- Lahan dasar berupa pulau atau kisi isometrik terang dengan permukaan `#f4f6ee`, sisi `#d6dbc6`/`#c3c9b0`, dan grid halus `#e3e6d8`.
- Dekorasi ringan berupa pohon instanced dan jalan putus-putus antar cabang.
- Cabang memakai slab orange dengan pola garis dan label pill gelap bertitik orange.
- Tim memakai plot hijau yang lebih tinggi dan label pill putih bertitik hijau.
- User memakai pion kapsul dengan kepala bulat. Warna badan mengikuti role yang dikirim backend.
- Admin dapat memakai mahkota kecil dan Manajer dapat memakai bendera jika role tersebut benar-benar tersedia.
- Role dibaca dari data database. Role yang tidak dikenal memakai fallback warna dari palet tahap.
- Layout disusun otomatis dari data cabang, tim, dan user. Pion diatur dalam grid lokal per tim dengan jarak sekitar `1.1` unit.
- Maksimal `12` pion terlihat per plot tim. User selebihnya diwakili badge `+N` pada pill tim; dropdown inspector tetap memuat semua user.
- Nameplate user ditempel di atas kepala pion seperti NPC, tetapi default tersembunyi. Label user hanya tampil saat hover, terpilih, tim difokuskan, atau zoom minimal `1.6x`.
- Label cabang dan tim memakai pill tinggi `26px`, lebar maksimal sekitar `180px`, ellipsis, tooltip nama lengkap, dan prioritas declutter cabang di atas tim di atas user.
- CSS2D nameplate berada di layer scene (`z-index: 1`), di bawah seluruh HUD/toolbar (`z-index: 10`) dan inspector (`z-index: 20`), sehingga nama pion tidak menutupi kontrol atau detail pilihan pada zoom tinggi.
- Slot Cabang baru tidak ditampilkan di scene; pembuatan cabang tetap dilakukan melalui halaman Pengaturan existing.

### Interaksi

- Mode lihat bersifat read-only. Klik pion membuka detail dan memfokuskan kamera secara halus ke pion.
- Mode edit hanya tersedia untuk pengguna dengan permission `users.assign-roles`.
- User tanpa permission tersebut tidak mendapatkan drag, dropdown aktif, atau tombol Edit mode.
- Hover pada pion menampilkan nama; fokus keyboard menampilkan ring fokus yang terlihat.
- Klik pion menampilkan ring putus-putus lime di kaki pion dan membuka panel detail.
- Drag memakai Pointer Events. Pion diberi bayangan dan state hover saat diangkat.
- Tim tujuan yang valid mendapat highlight lime saat pion diangkat.
- Lepas di luar target valid mengembalikan pion ke posisi asal.
- Perubahan assignment disimpan sebagai pending change sampai pengguna menekan Simpan perubahan. Indikator jumlah perubahan menunjukkan state lokal.
- Tombol Batalkan dan Undo hanya membatalkan perubahan lokal; keduanya tidak mengirim request.
- Penyimpanan mengirim PATCH satu per satu secara berurutan. Jika satu item gagal, item tetap pending, pion kembali ke tim asal, dan pesan server 403/422 ditampilkan pada status/panel item.
- User tanpa tim ditampilkan dalam panel Belum ditugaskan dan dapat dipindahkan ke tim jika permission tersedia.
- Dropdown tim di panel detail menjadi alternatif keyboard untuk drag and drop.
- Tombol `Fokus pawn` dan `Fokus tim` menggerakkan target kamera tanpa mengubah assignment.
- Pilihan role dan toggle akses hanya boleh interaktif jika ada endpoint yang mendukungnya. Saat ini informasi role dan akses ditampilkan read-only.
- Tombol Hubungkan hanya memindahkan fokus ke dropdown atasan; perubahan atasan tetap masuk pending assignment yang sama.
- Role global, role tim, dan informasi akses di panel kanan read-only dari `globalRoles` dan `roles`.
- Scene mendukung rotasi, pan, zoom 50%–400%, reset, dan fokus kamera; tidak ada tool Tambah atau slot cabang baru.
- Workspace memakai satu kartu kanvas penuh dengan HUD mengambang: mode edit/lihat, legenda yang dapat dilipat, toolbar vertikal, pending changes, kontrol kamera, kartu user tanpa tim, dan inspector overlay.
- Inspector hanya terlihat ketika objek user dipilih; saat kosong ia tidak mengambil lebar scene. Tombol Simpan/Batalkan tetap memakai pending assignment dan endpoint PATCH existing.

### Aksesibilitas dan fallback

- Semua aksi drag memiliki alternatif keyboard melalui pilihan user, dropdown tim, dan tombol Simpan perubahan.
- Fokus keyboard harus terlihat.
- `prefers-reduced-motion` mematikan animasi angkat, lengkung, dan transisi yang tidak perlu.
- Di bawah 768px, scene tetap tersedia dengan panel detail ditumpuk di bawah dan HUD kamera diperkecil.
- Pada mobile, perpindahan user dilakukan lewat dropdown tim dan tombol simpan; jika WebGL gagal, fallback daftar cabang dan tim ditampilkan.
- Jika tidak ada cabang atau tim, tampilkan empty state tanpa membuat aksi baru; struktur tetap dikelola pada halaman Cabang/Tim existing.

### Performa Spatial Workspace

Arsitektur render on demand: tidak ada loop `requestAnimationFrame` saat diam. Idle mencatat `0` scene render per detik. Render hanya terjadi saat kamera bergerak (termasuk damping), drag, tween, resize, atau tema/data berubah. Event `OrbitControls` digabung per frame agar tidak ada render ganda.

Instancing pion: satu `InstancedMesh` badan (`CapsuleGeometry` rendah, warna per instance via `setColorAt`), satu kepala (mata digabung ke geometri kepala via vertex color, bukan mesh terpisah), dan `InstancedMesh` kecil hanya untuk aksesori crown/flag/badge. Matriks instance hanya diperbarui untuk pion yang berubah. Rotasi menghadap kamera di-batch dan hanya saat azimuth berubah lebih dari 1 derajat.

Statis digabung: lahan, slab cabang, plot tim, dan jalan digabung via `mergeGeometries` per material. Pohon memakai `InstancedMesh` (batang + tajuk). Objek statis memakai `matrixAutoUpdate = false`. Garis hierarchy digabung menjadi satu `LineSegments` dengan buffer posisi dinamis (satu draw call; update per user saat drag via `updateForUser`).

Material murah: statis memakai `MeshBasicMaterial` (warna sama persis dengan palet, tanpa lampu), shadow map nonaktif sepenuhnya, bayangan diganti decal elips `InstancedMesh` transparan. Pion memakai `MeshLambertMaterial` dengan satu hemisphere + directional tanpa bayangan.

Resolusi dinamis: `pixelRatio` dibatasi `min(devicePixelRatio, 1.5)`, turun ke `1.0` selama orbit/drag/zoom, pulih setelah `150 ms` diam. Anisotropy maksimal `4`.

Label berjenjang: satu pool overlay (maksimal `100` label DOM), posisi via `translate3d`, baca ukuran di-batch. LOD: zoom jauh hanya cabang, tim pada zoom ≥ `1.2x`, user hanya hover/terpilih atau zoom ≥ `1.8x` pada tim fokus. Declutter memakai spatial hash dengan prioritas terpilih > hover > cabang > tim > user. Label di luar viewport disembunyikan.

Picking hemat: hover/klik memakai broad-phase analitik (sinar vs bidang plot) lalu pion terdekat via grid hash 2D per plot, throttle satu kali per frame dan hanya saat pointer bergerak. Raycast segitiga hanya sebagai fallback.

Drag hemat: selama drag hanya pion yang diseret, garis hierarchy miliknya, dan highlight tim tujuan yang diperbarui; layout label ditangguhkan (`suspendLayout`) dan satu render per gerakan.

State: objek Three.js disimpan di closure modul, bukan state reaktif. Peta indeks (`userId` → pawn, `teamId` → plot, grid hash) dibangun sekali saat data dimuat. `dispose` membersihkan geometri, material, tekstur, dan listener.

Mode hemat: otomatis jika `hardwareConcurrency <= 4`, `deviceMemory <= 4`, atau FPS di bawah `30` selama `3` detik (pixelRatio `1.0`, label user hanya hover, tajuk pohon ringan disembunyikan). Tombol "Mode hemat" di toolbar menyimpan pilihan di `localStorage`.

Anggaran performa (data sintetis `10x50x300`, headless Chromium sebagai perbandingan relatif): idle `0` frame/detik; orbit/zoom CPU normal FPS p50 ≥ `55` dan p95 ≤ `25 ms`; draw calls ≤ `60`; triangles ≤ `120.000`; node DOM label ≤ `100`; geometri unik ≤ `40`; muat sampai frame pertama ≤ `1` detik setelah data tersedia.

### Area belum ditugaskan

User tanpa tim (`teamIds` kosong, termasuk yang punya cabang tetapi tanpa tim) digambar di satu plot transit "Belum ditugaskan" di dalam scene, terpisah minimal `2` unit dari plot lain di tepi pulau sisi kiri-bawah (sejajar kartu HUD kiri bawah), dihubungkan jalan putus-putus ke cabang terdekat. Ukuran pulau dan auto-fit kamera dihitung ulang dari bounding box cabang + transit agar area ini selalu terlihat penuh.

Tampilan: plot rendah (`±0.2` unit) netral pasir krem `#ece6d3` (gelap `#2a2f22`) dengan garis diagonal halus dan tepi putus-putus, berbeda dari tim (hijau) dan cabang (oranye). Pill label netral bertitik abu: "Belum ditugaskan · N", atau "Semua user sudah ditugaskan" saat kosong (redup; disembunyikan dalam mode lihat jika kosong).

Kapasitas: maksimal `12` pion (grid jarak `±1.1` unit), sisanya badge `+N`. Pion tidak digambar di luar batas plot mana pun. Semua posisi berasal dari fungsi layout murni `layoutPawnPositions` yang deterministik (urutan stabil berdasarkan id).

Interaksi: pion transit bisa dipilih, di-hover, dan diseret ke tim mana pun (aturan cabang yang sama via `stageAssignment`). Area ini bukan target drop: saat drag melewatinya tampil tooltip "Tidak bisa melepas tim dari sini", dan lepas di sana mengembalikan pion ke asal. Tanpa izin edit, pion tidak bisa diseret.

Kartu HUD "Belum ditugaskan" dapat dibuka: header toggle, daftar scroll maksimal `320px`, kolom cari, filter role (chip), klik item menerbangkan kamera ke pion (`spatial:focus-user` + `spatial:select-user`), dan tiap item punya aksi "Tempatkan ke tim…" yang membuka inspector (dropdown tim existing) untuk user tersebut. Setelah ditempatkan (pending atau tersimpan), pion pindah visual ke plot tim dan jumlah label/kartu berkurang.

Layout murni dan tes: `resources/js/spatial/layout.js` tanpa THREE mengekspor `createWorkspaceLayout`, `layoutPawnPositions`, `layoutDecorations` (pseudo-random berbenih tetap, jarak ≥ `1.5` unit dari plot dan ≥ `2` unit dari label, maksimal sekitar `1` pohon per `6` sel kosong), dan `validateLayout`. Tes `scripts/test-spatial-layout.mjs` (`npm run test:spatial-layout`) menjalankan `500` variasi acak (termasuk tim kosong, tim `100` user, tanpa cabang, tanpa tim) dan gagal jika ada pion di luar plot atau tumpang tindih.

## Belum dikerjakan (butuh backend)

- **Denah kavling di Dashboard.** Membutuhkan query inventory Lot yang dikirim khusus ke Dashboard serta definisi layout kavling yang stabil.
- **Performa sales di Dashboard.** Membutuhkan agregasi performa dan permission data yang jelas dari backend.
- **Pipeline Lead per tahap di Dashboard.** Tidak ditampilkan jika hitungan per tahap tidak dikirim oleh controller existing.
- **Kanban drag and drop Leads.** Membutuhkan endpoint update status Lead yang aman, validasi transisi, audit aktivitas, dan permission khusus.
- **Kalender Leads.** Membutuhkan query atau endpoint kalender dengan tanggal, durasi, dan filter yang sesuai.
- **Denah Leads.** Membutuhkan data posisi spasial Lead dan endpoint penyimpanan posisi jika posisi dapat diubah.
- **Command palette baru.** Search global existing tetap digunakan. Palette baru membutuhkan kontrak pencarian dan aksi cepat yang terpisah jika ingin diaktifkan kembali.
- **Perpindahan tim ke cabang dari Spatial Workspace.** Endpoint `admin.teams.update` menerima update penuh tim, bukan assignment khusus dari scene.
- **Pengaturan role dan toggle akses dari Spatial Workspace.** Data role dapat dibaca, tetapi perubahan role dan permission memerlukan endpoint yang sesuai; panel saat ini sengaja read-only.
- **Virtualisasi pion untuk skenario ekstrem.** Renderer sudah membatasi redraw panel/mobile dan memakai `requestAnimationFrame`, tetapi virtualisasi berbasis viewport belum diperlukan tanpa bukti bottleneck produksi.

## Tahapan UI dan status implementasi

1. **Design source of truth.** `design.md` diperbarui dengan arah visual, token, shell, aturan interaksi, Spatial Workspace, dan daftar pekerjaan yang membutuhkan backend.
2. **Token, font, dan shell.** Token Poppins, warna utama, rail, panel, topbar, drawer, dan banner impersonation sudah tersedia. Search global existing dipakai tanpa command palette baru.
3. **Komponen dasar.** Card, stat card, stage chip, avatar, tombol, dan pill tersedia dengan token serta radius baru.
4. **Dashboard.** Dashboard sudah dipangkas. Denah kavling, Performa sales, dan Pipeline per tahap tidak ditampilkan karena tidak menjadi kontrak data dashboard.
5. **Leads.** Tabel Leads existing dipertahankan dengan gaya baru. Kanban, drag-drop, Kalender, Denah, toast, dan tombol Batalkan sudah dihilangkan dari halaman ini.
6. **Spatial Workspace.** Scene SVG telah diganti scene Three.js orthographic. Shell kompak, kanvas tunggal tanpa scroll halaman, HUD floating, inspector overlay, lahan terang/grid, batas 12 pion per plot, badge overflow, label NPC declutter, mode lihat/edit berbasis permission, seleksi, fokus kamera, panel detail, pending assignment, drag/drop, hierarchy links, fallback WebGL/mobile, dan penyimpanan berurutan melalui endpoint existing sudah tersedia.
7. **Komponen halaman modul.** Komponen reusable untuk global bar, page header, toolbar, tabel, aksi, badge, pagination, empty state, dan form sudah tersedia. Migrasi halaman modul dimulai pada tahap berikutnya dari Pengguna, Data Konsumen, dan Promo.
8. **Tiga modul daftar pertama.** Pengguna, Data konsumen, dan Promo memakai global bar/page header baru, toolbar GET dengan filter langsung, tabel bersama, aksi baris, pagination, empty state, dan responsive table. Pengguna juga memakai bulk bar floating saat ada pilihan. Form create/edit belum dimigrasikan.
9. **Modul Pengaturan.** Cabang, Proyek, Kavling, Audit log, Bank, dan Target lead time memakai pola daftar yang sama. Filter, riwayat, expandable detail audit, form inline bank/unit, dan update target lead time tetap mempertahankan endpoint existing.
10. **Modul Sales dan proses penjualan.** Leads, Monitoring Lead, Agenda sales, Laporan performa, Garansi, PSJB/UTJ, BI Checking, Pemberkasan, Proses Bank, PPJB Developer, dan Akad & BAST memakai tabel, toolbar, badge status, aksi baris, pagination, empty state, dan form inline bersama. Seluruh filter, form update, dialog mundur, serta endpoint existing dipertahankan.
11. **Form dan detail.** Form create/edit Lead, Konsumen, Agenda sales, Tim, dan Role serta detail Lead, detail Konsumen, dan detail laporan performa memakai shell page header, form section, kartu aksi, tabel, dan summary bersama. Partial Alpine konsumen, upload bukti agenda, permission, validasi, dan endpoint existing tetap dipertahankan.
12. **Tahap 6 proses penjualan.** Pemberkasan, Proses Bank, PPJB Developer, Akad & BAST, dan Garansi memakai form section, table card, status badge, inline update, empty state, dan pagination bersama. Halaman perubahan data PSJB/UTJ yang sebelumnya legacy juga memakai page header, kartu proses, kontrol form, dan aksi standar; field, Alpine state, permission, validasi, serta endpoint existing dipertahankan.
13. **Verifikasi visual.** Spatial Workspace diverifikasi pada viewport `1440x900`, `1920x1080`, `1024x768`, dan `390x844`; screenshot desktop/mobile serta perbandingan TARGET-vs-sesudah disimpan di `docs/screenshots/spatial-override-*.png`. Daftar Tahap 4, halaman Tahap 5, dan modul proses Tahap 6 diverifikasi pada empat viewport tanpa horizontal overflow. Uji skenario 300 user tetap perlu dijalankan pada dataset staging yang representatif.
14. **Tahap 7 final polish.** Halaman Profile, Notifikasi, dan seluruh guest/auth shell memakai visual Oasis Next dengan breadcrumb, card, tombol, form control, empty state, dan responsive spacing yang sama. Print laporan performa dirapikan untuk output A4 tanpa mengubah isi data atau auto-print behavior. Legacy token pada halaman auth/profile utama diganti dengan token semantik; alias kompatibilitas tetap dipertahankan untuk view operasional yang belum dimigrasikan massal.
15. **Verifikasi final.** Login, Profile, dan Notifikasi diverifikasi pada empat viewport. Console browser bersih, route tetap memakai endpoint existing, dan screenshot Tahap 7 tersimpan di `docs/screenshots/stage7-*.png`.
16. **Tahap 8 admin dan release readiness.** Form Cabang, Proyek, Promo, dan Riwayat Kavling memakai page header, form section, readonly control, action button, history card, empty state, dan responsive layout bersama. Alpine target promo, form block/kavling, delete actions, validation messages, permission checks, serta endpoint existing tetap dipertahankan.
17. **Verifikasi Tahap 8.** Tujuh route form/detail admin diverifikasi pada empat viewport tanpa horizontal overflow; interaksi tambah target Promo berhasil diuji. Regression feature tests untuk inventory, promo, navigation, profile, dan notifikasi lulus. Uji dataset besar tetap menunggu staging representatif.
18. **Tahap 9 hardening.** Placeholder modul dan komponen Breeze aktif dirapikan ke token semantic Oasis Next; input, label, modal, navigasi responsif, pill warning, dan empty placeholder memakai primitive bersama. Alias kompatibilitas masih tersedia untuk view legacy yang belum aktif atau belum perlu migrasi massal.
19. **Verifikasi hardening.** Route Profile, Notifikasi, Promo, Proyek, dan Riwayat Kavling merespons `200` pada empat viewport dengan `scrollWidth` sama dengan lebar client. Login diverifikasi melalui browser tanpa console error. Regression authorization/validation test lulus dan seluruh suite tetap lulus.
20. **Tahap 10 release review.** Halaman Pengguna diselaraskan ke `marison-modul.png`: deskripsi, filter `Cabang`/`Role global` dengan opsi `Semua`, toolbar tanpa judul kartu, chip role (`super_admin` hitam, `sas` kuning, `sales`/`spv` lime, `koordinator_sales` peach), dot cabang orange, aksi baris berikon, menu `Hapus pengguna`, pagination dengan ellipsis halaman pertama/terakhir, dan placeholder search global. Route, query, permission, bulk action, dan endpoint tidak berubah.
21. **Verifikasi release.** Pengguna, Konsumen, Leads, PSJB/UTJ, dan Profile merespons `200` pada empat viewport dengan `scrollWidth` sama dengan lebar viewport. Screenshot Tahap 10 tersimpan di `docs/screenshots/stage10-*.png` (artefak duplikat sidebar pada PNG adalah keterbatasan tool screenshot; pengukuran DOM memastikan tidak ada overflow). Console bersih dan seluruh suite lulus.
22. **Tahap 11 selesai secara lokal.** Audit protected path (`routes`, `app/Http`, `app/Models`, `database`, `app/Support`) bersih tanpa diff backend; perubahan aplikasi terbatas pada `design.md`, `resources/css`, `resources/js`, `resources/views`, dan artefak screenshot. Regression permission/validasi lulus, build/cache/format/diff check lulus, login browser tidak menghasilkan console error, dan artefak log `.playwright-mcp` sudah dibersihkan.
23. **Tahap 12 selesai secara lokal.** Dataset lokal terukur `9 cabang / 9 tim / 91 user`; belum memenuhi fixture staging `10 / 50 / 300`. Spatial baseline tetap sehat pada desktop/mobile: 18 label, tanpa fallback WebGL dan tanpa overflow; chunk Spatial sekitar `554 kB` minified (`141 kB` gzip), dengan navigasi sekitar `353 ms` response dan `408 ms` load pada dataset lokal. Full suite, cache, build, Pint, dan diff check lulus.
24. **Tahap 13 direncanakan: staging release handoff.** Jalankan fixture resmi `10 cabang / 50 tim / 300 user`, verifikasi `403`/`422` end-to-end dan alur kritis di staging, ambil baseline screenshot resmi untuk pixel-diff, ukur WebGL/scene frame performance, lalu minta persetujuan merge/release. Tidak ada perubahan route, controller, request, model, migration, atau endpoint.
25. **Checklist siap commit.** Sebelum commit, pastikan staging gate selesai, lalu ulangi `php artisan test --compact`, `php artisan view:cache`, `npm run build`, `vendor/bin/pint --dirty`, dan `git diff --check`; commit terpisah untuk view/CSS/JS/dokumen dan screenshot bila perlu review.

## Audit token legacy

- Prefix `marison-*` sudah tidak dipakai pada source view/CSS/JS dan tokennya telah dihapus dari `app.css`.
- Class Tailwind `gray-*`, `amber-*`, dan `indigo-*` sudah bersih dari komponen bersama, halaman auth/profile/notifikasi, dan halaman Pengguna. Class `border-gray-300`/`rounded-md`/`text-gray-*` masih dipakai pada partial form operasional (Lead, Konsumen, PSJB/UTJ, BI Checking, Promo) dan sengaja dipertahankan agar tidak mengubah markup form aktif; alias kompatibilitas di `app.css` memetakan class tersebut ke token Oasis Next tanpa rename massal.
- `x-ui.kanban-column` dan `x-ui.lead-card` tetap ada sebagai komponen legacy yang tidak dirender oleh halaman Leads baru. `sortablejs` sudah dihapus karena tidak lagi dipakai.

## Klasifikasi diff protected path

- Workflow Buku Saku/Handoff pada `routes/web.php`, `app/Http/Controllers/LeadController.php`, dan `app/Models/Lead.php` tetap merupakan fitur existing dan tidak disentuh oleh audit UI ini.
- Pada audit Tahap 11, `git diff --name-only -- routes app/Http app/Models database app/Support` tidak menghasilkan file. Tidak ada exception protected path yang perlu dipisahkan dari perubahan UI.
- Spatial Workspace tidak menambah route/controller/query/migration; ia hanya memakai PATCH assignment existing.

## Status audit

- **Sesuai:** batasan tanpa backend baru untuk UI Spatial/redesign yang diaudit, token Oasis Next pada shell/halaman utama, Poppins, endpoint Spatial tunggal, permission mode edit, fallback mobile, role read-only, dan penghapusan Kanban/Denah/command palette.
- **Sebagian:** visual halaman legacy masih memakai alias `gray-*`/`amber-*`; fitur Handoff backend tetap ada tetapi sengaja tidak ditampilkan di UI Lead.
- **Belum:** pengujian performa dengan 10 cabang/50 tim/300 user, baseline pixel-diff resmi, dan verifikasi server 403/422 end-to-end pada staging.

## Aturan perubahan

- Jangan menambah route, controller method, query, migration, atau endpoint baru untuk kebutuhan UI ini.
- Jangan memakai angka, nama, atau kode dari mockup sebagai data aplikasi.
- Jika data atau endpoint tidak tersedia, hilangkan elemen tersebut dan catat di bagian Belum dikerjakan.
- Perubahan backend existing, permission, dan alur bisnis harus tetap aman.
