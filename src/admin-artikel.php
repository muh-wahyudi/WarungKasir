<?php
require __DIR__ . '/includes/admin_layout.php';
$u = admin_layout_guard($pdo);
admin_layout_start($u, 'artikel', 'Artikel', 'Admin / Artikel');
?>
      <div class="ph"><h2>Artikel</h2><p>Kelola artikel dan kategori untuk halaman publik WarungKasir.</p></div>

      <section class="card">
        <div class="tabs" id="tabs" role="tablist">
          <button type="button" class="on" data-tab="artikel">Artikel</button>
          <button type="button" data-tab="kategori">Kategori Artikel</button>
        </div>

        <div id="t-artikel">
          <div class="filters">
            <input id="q" type="search" placeholder="Cari judul artikel" aria-label="Cari judul">
            <select id="f-status" aria-label="Filter status">
              <option value="">Semua status</option>
              <option value="draft">Draft</option>
              <option value="published">Published</option>
              <option value="archived">Archived</option>
            </select>
            <select id="f-cat" aria-label="Filter kategori"><option value="">Semua kategori</option></select>
            <a href="admin-artikel-edit.php" class="btn" style="margin-left:auto;">+ Tulis Artikel</a>
          </div>
          <div class="wrap">
            <table class="tbl">
              <thead><tr><th>Judul</th><th>Kategori</th><th>Status</th><th>Views</th><th>Tanggal</th><th>Aksi</th></tr></thead>
              <tbody id="list"><tr><td colspan="6" class="empty">Memuat…</td></tr></tbody>
            </table>
          </div>
          <div class="pager">
            <span id="pg-info"></span>
            <div>
              <button class="link" type="button" id="pg-prev">Sebelumnya</button>
              <button class="link" type="button" id="pg-next">Berikutnya</button>
            </div>
          </div>
        </div>

        <div id="t-kategori" hidden>
          <div class="filters">
            <input id="kat-q" type="search" placeholder="Cari nama kategori" aria-label="Cari kategori">
            <button class="btn" type="button" id="btn-add-kategori" style="margin-left:auto;">+ Tambah Kategori</button>
          </div>
          <div class="wrap">
            <table class="tbl">
              <thead><tr><th>Nama</th><th>Slug</th><th>Deskripsi</th><th>Urutan</th><th>Aksi</th></tr></thead>
              <tbody id="kat-list"><tr><td colspan="5" class="empty">Memuat…</td></tr></tbody>
            </table>
          </div>
        </div>
      </section>
<?php admin_layout_end(['js/admin-artikel.js']); ?>