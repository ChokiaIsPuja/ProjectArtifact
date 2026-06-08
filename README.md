# ProjectArtifact

A web based Rogue-like RPG, made by using HTML, PHP, JavaScript and SQL.


# Project Artifact - Decision Support System (DSS) Berbasis Graph

Project ini dikembangkan sebagai pemenuhan Tugas Besar Akhir (UAS) mata kuliah **Struktur Data**. Project Artifact mengimplementasikan struktur data **Graph (Graf)** sebagai fondasi utama dari sebuah *Decision Support System* (DSS) untuk membantu pemain menentukan jalur perjalanan terbaik di dalam dungeon yang dihasilkan secara prosedural (*Procedural Map Generation*).

## 👥 Kelompok Proyek
* **Kadek Puja Arya Putra** - (2501010120)
* **I Gede Fajar Waradana** - (2501010123)

---

## 📖 1. Studi Kasus
Dalam game *roguelike* prosedural ini, pemain dihadapkan pada 15 kolom rute perjalanan yang saling bercabang. Setiap ruangan (*node*) memiliki tingkat risiko dan hadiah berbeda (*Combat, Elite, Shop, Event,* dan *Boss*). Pemain sering mengalami kesulitan untuk menentukan rute optimal yang sesuai dengan kondisi *real-time* karakter mereka (seperti status HP atau orientasi strategi *farming*).

Untuk mengatasi masalah tersebut, dibangun sebuah sistem penunjang keputusan (**Decision Support System / DSS**) menggunakan **Algoritma Dijkstra** untuk melakukan kalkulasi rute berdasarkan bobot risiko dinamis secara *real-time*.

---

## 📐 2. Pemodelan Graph (Struktur Data)

Aplikasi ini merepresentasikan peta dungeon ke dalam bentuk struktur data **Weighted, Directed Acyclic Graph (DAG)**:
* **Vertices (Node):** Merepresentasikan jenis-jenis ruangan di dalam dungeon.
  * `Start` (Titik Awal)
  * `Combat` (Pertarungan Biasa)
  * `Shop` (Toko Perlengkapan)
  * `Event` (Kejadian Acak)
  * `Boss` (Titik Akhir / Pertempuran Utama)
* **Edges (Sisi/Jalur):** Merepresentasikan koridor penghubung antar ruangan yang digenerasikan menggunakan pendekatan keterikatan tetangga terdekat (*Nearest-Neighbor Edge Connection*).
* **Properties (Sifat Graf):**
  * **Directed:** Pemain hanya bisa bergerak maju secara progresif dari kolom $0$ ke kolom $14$ (tidak dapat memutar balik).
  * **Acyclic:** Struktur peta dipastikan tidak mengandung siklus tak terbatas (*infinite loops*).
  * **Weighted:** Setiap tipe node diinjeksikan koefisien bobot risiko dinamis berdasarkan pilihan strategi pemain pada menu asisten taktis Mimi.

---

## 🧠 3. Implementasi Algoritma & DSS

Sistem menggunakan **Algoritma Dijkstra** berbasis *Priority Queue Matrix* pada sisi client (JavaScript) untuk memecahkan jalur dengan akumulasi bobot terkecil menuju titik target akhir (`Boss`).

### ⚙️ Mekanisme Pembobotan Taktis (DSS Mode)
1. **🛡️ Safest Path (Rute Teraman):**
   * Memberikan penalti bobot yang sangat besar pada pertarungan.
   * *Weights Matrix:* `Combat: 15, Elite: 50, Event: 2, Shop: 1`.
   * *Hasil:* Algoritma akan mencari celah rute meliuk demi menghindari monster dan memprioritaskan ruang pemulihan.
2. **💰 Greed Path (Memaksimalkan Gold):**
   * Membalikkan prioritas untuk mengejar monster elite demi drop loot terbesar.
   * *Weights Matrix:* `Combat: 2, Elite: 1, Event: 25, Shop: 8`.
   * *Hasil:* Jalur lurus menyala menembus kumpulan musuh untuk mengoptimalkan *farming*.
3. **⚖️ Optimal Path (Rekomendasi Pintar AI - Bonus Nilai):**
   * Sistem membaca variabel HP karakter dari database secara *real-time* menggunakan ambang batas (*threshold evaluation*).
   * **Kondisi HP Aman ($>40\%$):** Menerapkan bobot agresif agar player bisa terus memperkuat status level.
   * **Kondisi HP Kritis ($<40\%$):** Algoritma secara otomatis melakukan *fallback* dan menulis ulang matriks bobot ke mode bertahan hidup (Safe Mode) guna menyelamatkan kelangsungan permainan.

---

## 📊 4. Kompleksitas Algoritma (Time & Space Complexity)

* **Time Complexity (Kompleksitas Waktu):** Operasi algoritma berjalan pada kedalaman **$O(V^2)$** atau **$O(V \log V + E)$** karena dipicu oleh fungsi pengurutan barisan antrean (`queue.sort`), di mana $V$ adalah jumlah total ruangan dan $E$ adalah jalur penghubung. Karena jumlah node dibatasi di bawah 100 node per level, proses kalkulasi lintasan selesai secara instan dalam waktu **< 1ms**.
* **Space Complexity (Kompleksitas Ruang):** **$O(V + E)$** untuk menyimpan seluruh struktur node dan relasi edge di dalam memory runtime.

---

## 🛠️ 5. Kendala Pengembangan & Solusi Terapan
* **Sinkronisasi Kamera Centering:** Ditemukan bug di mana fungsi kamera tidak dapat membidik posisi awal pemain saat pemuatan ulang benih acak (*seed reset*). 
  * *Solusi:* Memperbarui fungsi interpolasi geometri kamera agar membaca nilai koordinat array data graf mentah secara langsung, menghilangkan ketergantungan pada ID String DOM elemen browser.
* **Integrasi Jalur AI & HP Null:** Terjadi crash sistem DSS di mana nilai HP terbaca 0 karena ketidakcocokan query antartabel relasional. 
  * *Solusi:* Melakukan normalisasi struktur data query gabungan (*LEFT JOIN*) untuk menyatukan muatan statistik vitalitas objek karakter.

---

## 💻 6. Fitur Unggulan Proyek (Kriteria Bonus)
* **Visualisasi Interaktif Real-Time:** Jalur rekomendasi langsung menyala terang sebagai garis solid dengan efek neon bercahaya sesaat setelah tombol pilihan diklik.
* **AI/Smart Recommendation:** Penentuan keputusan adaptif yang menyesuaikan diri berdasarkan fluktuasi HP pemain.
* **Procedural Canvas Control:** Mendukung manipulasi interaktif seperti menyeret peta (*canvas dragging*) dan perbesaran kamera (*zooming engine*).
