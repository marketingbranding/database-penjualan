# Oasis Next

## Arah visual

Oasis Next adalah workspace operasional untuk tim sales properti. Antarmuka dibuat terang, ramah, dan mudah dipindai saat dipakai untuk pekerjaan lapangan. Struktur workspace modern menjadi acuan ritme halaman, bukan sesuatu yang disalin secara pixel-perfect.

Prinsip visual:

- Latar terang dengan kontras yang cukup untuk data operasional.
- Surface putih, border tipis, dan radius besar menjadi dasar komponen.
- Aksi utama terlihat jelas tanpa membuat semua elemen menjadi tombol.
- Judul memakai sentence case dan tracking rapat.
- Animasi hanya muncul sebagai respons aksi pengguna dan mengikuti `prefers-reduced-motion`.

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
- Topbar transparan tanpa border bawah, tinggi target sekitar `52px`, dan menampilkan judul halaman `24px`, deskripsi `12px`, search putih `420px` dengan hint `Ctrl K`, notifikasi, kode tim, dan pill profil.
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
7. **Halaman lain.** Agenda, Monitoring, Laporan, Data Konsumen, Cabang, Proyek, Kavling, Pengguna, Tim, dan Role & permission sudah diselaraskan ke shell/token baru tanpa mengubah backend.
8. **Verifikasi visual.** Spatial Workspace diverifikasi pada viewport `1440x900`, `1920x1080`, `1024x768`, dan `390x844`; screenshot desktop/mobile serta perbandingan TARGET-vs-sesudah disimpan di `docs/screenshots/spatial-override-*.png`. Pengukuran DOM menunjukkan rail `66px`, panel `216px`, item menu `35px`, scroll halaman tidak bertambah, inspector kosong opacity `0`, label visible berada di dalam stage, dan label cabang/tim visible tidak overlap lebih dari `10%` pada dataset lokal. Uji skenario 300 user tetap perlu dijalankan pada dataset staging yang representatif.

## Audit token legacy

- Prefix `marison-*` sudah tidak dipakai pada source view/CSS/JS dan tokennya telah dihapus dari `app.css`.
- Class Tailwind `gray-*`, `amber-*`, dan `indigo-*` masih tersisa pada halaman legacy di luar halaman redesign utama (notifikasi, auth/profile, garansi, sebagian proses penjualan, komponen Breeze, dan placeholder). Alias kompatibilitas di `app.css` menjaga tampilan tanpa rename massal.
- `x-ui.kanban-column` dan `x-ui.lead-card` tetap ada sebagai komponen legacy yang tidak dirender oleh halaman Leads baru. `sortablejs` sudah dihapus karena tidak lagi dipakai.

## Klasifikasi diff protected path

- `routes/web.php`, `app/Http/Controllers/LeadController.php`, dan `app/Models/Lead.php` masih memiliki diff Buku Saku/Handoff, termasuk route follow-up, qualification, dan handoff serta relasi/field pendukungnya.
- Diff tersebut dipertahankan sebagai workflow backend yang sudah ada di working tree, tetapi tidak dipakai untuk menambah UI Oasis Next pada audit ini. Jika baseline review menganggap `HEAD` sebagai satu-satunya sumber fitur yang sah, diff ini adalah pengecualian terhadap kontrak “tanpa backend baru” dan harus dipisahkan atau disetujui sebelum merge.
- Spatial Workspace tidak menambah route/controller/query/migration; ia hanya memakai PATCH assignment existing.

## Status audit

- **Sesuai:** batasan tanpa backend baru untuk UI Spatial/redesign yang diaudit, token Oasis Next pada shell/halaman utama, Poppins, endpoint Spatial tunggal, permission mode edit, fallback mobile, role read-only, dan penghapusan Kanban/Denah/command palette.
- **Sebagian:** visual halaman legacy masih memakai alias `gray-*`/`amber-*`; fitur Handoff backend tetap ada tetapi sengaja tidak ditampilkan di UI Lead.
- **Belum:** pengujian performa dengan 10 cabang/50 tim/300 user dan verifikasi server 403/422 end-to-end pada staging.

## Aturan perubahan

- Jangan menambah route, controller method, query, migration, atau endpoint baru untuk kebutuhan UI ini.
- Jangan memakai angka, nama, atau kode dari mockup sebagai data aplikasi.
- Jika data atau endpoint tidak tersedia, hilangkan elemen tersebut dan catat di bagian Belum dikerjakan.
- Perubahan backend existing, permission, dan alur bisnis harus tetap aman.
