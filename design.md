# Marison Work OS

## Arah Visual

Marison Work OS mengambil pola workspace modern: navigasi modular, konteks tim yang selalu terlihat, tabel sebagai pusat kerja, dan activity feed untuk kolaborasi. Produk tidak menyalin Lark secara visual, terminologi, aset, ikon, atau susunan pixel.

Karakter visual:

- Tenang dan profesional untuk pekerjaan operasional harian.
- Hangat dan khas properti, bukan dashboard SaaS generik.
- Padat saat membaca data, lapang saat mengerjakan form.
- Aksi penting jelas tanpa memakai warna mencolok di seluruh halaman.

## Token Warna

| Token | Nilai | Peran |
| --- | --- | --- |
| `forest-950` | `#102a24` | Sidebar dan navigasi utama |
| `forest-900` | `#173b31` | Sidebar hover dan panel konteks |
| `cream-50` | `#f6f2e9` | Latar canvas |
| `paper` | `#fffdfa` | Card, table, dan form |
| `terracotta-600` | `#c96545` | Aksi utama dan Lead |
| `gold-400` | `#d9aa45` | Highlight dan Agenda |
| `teal-600` | `#167d72` | Proses dan status positif |
| `ink-950` | `#18211e` | Teks utama |
| `line` | `#e5e0d6` | Border dan divider |

Status colors remain semantic: emerald for success, amber for attention, and red for destructive actions.

## Workspace Shell

- Sidebar kiri menjadi sumber navigasi utama.
- Buku Saku Sales memiliki context navigation: Leads, Agenda, Monitoring, dan Laporan.
- Header menampilkan konteks halaman, notifikasi, dan tim aktif.
- Impersonation selalu memakai banner kontras dan action untuk kembali.
- Mobile menggunakan drawer sidebar, bukan sidebar yang mengecil menjadi unusable.

## Component Rules

- Gunakan `rounded-xl` untuk workspace card dan `rounded-lg` untuk controls.
- Gunakan border tipis sebelum menambah shadow.
- Gunakan `gap` untuk jarak antar elemen.
- Tabel tetap menjadi komponen utama untuk Lead, Agenda, dan laporan.
- Gunakan terracotta untuk primary action, bukan semua link.
- Jangan menambahkan komponen atau interaction yang belum memiliki backend.

## Typography

- Figtree tetap menjadi font utama karena terbaca pada tabel dan form.
- Heading memakai weight 700 dengan tracking rapat.
- Label filter dan section memakai uppercase kecil dengan tracking lebar.
- Hindari terlalu banyak ukuran font agar hierarki tetap konsisten.

## Tahapan UI

1. Fondasi token, shell, sidebar, header, dan responsive behavior.
2. Redesign Leads, Agenda, Monitoring, dan Laporan.
3. Dashboard operasional dan activity feed.
4. Search global dan quick actions setelah endpoint tersedia.
5. Visual UAT desktop, tablet, dan mobile.

## Status Implementasi

- Fondasi token, shell, sidebar, header, team context, dan impersonation banner: selesai.
- Context navigation Buku Saku Sales: selesai.
- Surface, border, text, form, dan aksen warna global: selesai.
- Dashboard Work OS, activity feed, quick actions, dan global search: selesai.
- Redesign detail halaman Leads, Agenda, Monitoring, dan Laporan: selesai.
