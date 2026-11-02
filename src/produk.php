<?php
require __DIR__ . '/includes/owner_layout.php';
$u = owner_page_guard($pdo);
owner_layout_start($u, 'produk', 'Produk', 'Kelola barang dagangan · ' . e($u['business_name']));
?>
      <div class="note-e" id="err" role="alert" hidden></div>
      <div class="stats" id="stats"></div>

      <section class="card mb" aria-labelledby="h-cap">
        <div class="cap-top"><h2 id="h-cap">Kapasitas Paket</h2><span id="cap-t"></span></div>
        <div class="meter g"><i id="cap-bar"></i></div>
        <p class="note-w" id="cap-note" hidden></p>
      </section>

      <section class="card" aria-labelledby="h-list">
        <div class="card-h"><h2 id="h-list">Daftar Produk</h2><button class="btn" type="button" id="add" data-act="add"><svg class="i" aria-hidden="true"><use href="#i-plus"/></svg>Tambah Produk</button></div>
        <div class="filters">
          <div class="sbox"><svg class="i" aria-hidden="true"><use href="#i-search"/></svg><input id="q" type="search" placeholder="Cari nama atau kode produk" aria-label="Cari produk" maxlength="60" autocomplete="off"></div>
          <select id="f-stok" aria-label="Filter stok"><option value="">Semua stok</option><option value="aman">Stok aman</option><option value="menipis">Stok menipis</option><option value="habis">Stok habis</option></select>
          <select id="f-sort" aria-label="Urutkan"><option value="terbaru">Terbaru</option><option value="nama">Nama A–Z</option><option value="harga_naik">Harga termurah</option><option value="harga_turun">Harga termahal</option><option value="stok_naik">Stok tersedikit</option><option value="stok_turun">Stok terbanyak</option></select>
        </div>
        <div class="wrap">
          <table class="tbl">
            <thead><tr><th>Produk</th><th>Harga</th><th>Stok</th><th>Status</th><th>Batas Stok</th><th>Aksi</th></tr></thead>
            <tbody id="pt"><tr><td colspan="6" class="empty">Memuat produk…</td></tr></tbody>
          </table>
        </div>
        <div class="pager"><span id="pg-info"></span><div><button class="link" type="button" id="pg-prev">Sebelumnya</button><button class="link" type="button" id="pg-next">Berikutnya</button></div></div>
      </section>
<?php owner_layout_end(['js/produk.js']); ?>