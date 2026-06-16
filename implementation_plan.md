# Refaktor & Perbaikan CRUD (Superadmin & Admin Venue)

Berdasarkan instruksi, saya akan melakukan refactoring menyeluruh untuk memperbaiki operasi CRUD yang rusak akibat transisi ke arsitektur Multi-Tenant. Rencana ini dibagi menjadi perbaikan Backend (Model & Controller) dan Frontend (UI & State Management).

## Proposed Changes

### TUGAS 1: Perbaikan Fondasi Model (Backend)
Kita perlu memastikan kolom `venue_id` dapat diisi massal (fillable) dan semua relasi antar model sudah terhubung dengan benar.

#### [MODIFY] `app/Models/User.php`
- Menambahkan relasi `venue()` jika admin hanya bisa mengelola 1 venue (opsional, tapi disarankan untuk kejelasan).

#### [MODIFY] `app/Models/Venue.php`
- Memastikan relasi `owner()` (User), `courts()`, `products()`, `bookings()`, `orders()` sudah lengkap dan akurat.

#### [MODIFY] `app/Models/Product.php`, `app/Models/Booking.php`, `app/Models/Order.php`, `app/Models/Court.php`
- Memastikan `venue_id` masuk dalam properti `$fillable`.
- Memastikan relasi `belongsTo(Venue::class)` di setiap model terkait.

---

### TUGAS 2: Maksimalisasi CRUD Superadmin
Superadmin bertugas membuat Venue dan Akun Admin sekaligus tanpa ada data yang yatim (orphaned).

#### [MODIFY] `app/Http/Controllers/SuperAdminController.php`
- Memastikan endpoint `storeVenue` membungkus operasi pembuatan Venue dan pembuatan User Admin dalam blok `DB::beginTransaction()`.
- Menyempurnakan response API dengan struktur error yang jelas.

#### [MODIFY] `src/pages/superadmin/SuperAdminDashboard.tsx`
- Mengganti semua penggunaan `alert()` dengan notifikasi Toast modern (`react-hot-toast`).
- Menambahkan validasi input yang lebih baik (mencegah field kosong sebelum memanggil API).
- Memastikan auto-refresh tabel setelah operasi Create, Update, atau Delete/Suspend berhasil.

---

### TUGAS 3: Isolasi Data & CRUD Admin Venue
Setiap Admin GOR hanya boleh melihat dan memanipulasi data milik venue (GOR) mereka sendiri.

#### [MODIFY] `app/Http/Controllers/AdminController.php`
- **Read Logic (Isolasi):** Memastikan klausa `->where('venue_id', auth()->user()->venue_id)` (atau melalui helper `getAdminVenue()`) diterapkan di fungsi `getAllBookings`, `getCourts`, `getProducts`, `getOrders`, `getWeeklyRevenue`.
- **Create Logic (Auto-Inject):** Memastikan `storeProduct`, `addCourt`, dsb., menyuntikkan `venue_id` milik admin yang login secara server-side, mengabaikan `venue_id` yang mungkin dikirim dari frontend.
- **Update/Delete Logic:** Memastikan sebelum operasi modifikasi, dicek terlebih dahulu apakah data tersebut benar-benar milik venue si admin.
- Menambahkan fungsi edit produk (`updateProduct`) dan edit lapangan (`updateCourt`) yang saat ini belum lengkap.

---

### TUGAS 4: UI Admin Dashboard (Frontend)
Menghidupkan fungsionalitas CRUD di sisi antarmuka Admin GOR.

#### [MODIFY] `src/pages/admin/AdminDashboard.tsx`
- Mengganti `alert()` dengan notifikasi dari `react-hot-toast`.
- Mengimplementasikan state dan modal untuk fungsi Edit (Edit Lapangan & Edit Produk), di mana form secara otomatis terisi dengan data awal barang yang diklik.
- Menambahkan konfirmasi sebelum Delete ("Yakin ingin menghapus?").
- Memastikan sinkronisasi data dengan API berjalan lancar menggunakan `Authorization: Bearer` (sudah dilindungi oleh interceptor `api.ts`).

## Verification Plan

### Automated Tests
- Menjalankan `php -l` pada semua file PHP yang dimodifikasi untuk memastikan tidak ada *syntax error*.
- Memastikan `npm run dev` di frontend tidak menampilkan error Typescript terkait perubahan properti/state.

### Manual Verification
- Superadmin: Menguji proses Tambah GOR baru (harus sukses membuat Venue dan Admin sekaligus).
- Admin Venue: Menguji login, memastikan hanya lapangan dan produk miliknya yang terlihat.
- Admin Venue: Menguji tambah produk, edit produk, hapus produk (harus menampilkan toast berhasil/gagal yang sesuai).

## User Review Required

> [!IMPORTANT]
> Proyek frontend ini akan menggunakan `react-hot-toast` untuk UI notifikasi. Jika paket ini belum terinstall di package.json, saya akan menjalankannya `npm install react-hot-toast`. Apakah Anda setuju dengan penggunaan paket ini?

Silakan periksa dan berikan persetujuan Anda sebelum saya mengeksekusi rencana ini!
