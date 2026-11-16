<?php
require __DIR__ . '/includes/admin_layout.php';
$u = admin_layout_guard($pdo);
$id = (int) ($_GET['id'] ?? 0);
$headExtra = '<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<style>
  .editor-wrap { background:#fff; border:1px solid var(--bd); border-radius:12px; overflow:hidden; }
  #editor { min-height: 400px; font-size: 1rem; }
  .ql-container { font-family: inherit; font-size: 1rem; }
  .cover-preview { width: 100%; max-width: 400px; border-radius: 12px; border: 1px solid var(--bd); margin-top: 8px; }
</style>';
admin_layout_start($u, 'artikel', $id ? 'Edit Artikel' : 'Tulis Artikel Baru', 'Admin / Artikel / ' . ($id ? 'Edit' : 'Tulis'), $headExtra);
?>
      <form id="form" data-id="<?= $id ?>" enctype="multipart/form-data">
        <section class="card">
          <div class="fld"><label for="f-title">Judul</label><input id="f-title" name="title" maxlength="200" required><p class="ferr" id="e-title"></p></div>
          <div class="fld"><label for="f-excerpt">Ringkasan (max 300)</label><textarea id="f-excerpt" name="excerpt" maxlength="300" required></textarea><p class="ferr" id="e-excerpt"></p></div>
          <div class="fld"><label for="f-slug">Slug (URL, opsional)</label><input id="f-slug" name="slug" maxlength="200" placeholder="otomatis dari judul"><p class="ferr" id="e-slug"></p></div>
          <div class="fld"><label for="f-cat">Kategori</label><select id="f-cat" name="category_id"><option value="">— Tanpa kategori —</option></select></div>
          <div class="fld"><label for="f-tags">Tag (pisah koma)</label><input id="f-tags" name="tags" placeholder="contoh: kasir, stok, laporan"><p class="hint">Pisahkan dengan koma.</p></div>
          <div class="fld"><label>Isi Artikel</label><div class="editor-wrap"><div id="editor"></div></div><p class="ferr" id="e-content"></p></div>
          <div class="fld"><label>Cover Image (max 3 MB)</label><input type="file" id="f-cover" accept="image/jpeg,image/png,image/webp"><div id="cover-preview"></div><p class="ferr" id="e-cover"></p></div>
          <div class="fld"><label for="f-status">Status</label><select id="f-status" name="status"><option value="draft">Draft</option><option value="published">Published</option><option value="archived">Archived</option></select></div>
          <div class="fld"><label for="f-meta-title">Meta Title (SEO, opsional)</label><input id="f-meta-title" name="meta_title" maxlength="200"></div>
          <div class="fld"><label for="f-meta-desc">Meta Description (SEO, opsional)</label><textarea id="f-meta-desc" name="meta_description" maxlength="300"></textarea></div>
          <p class="ferr" id="e-form"></p>
          <div class="modal-f"><a href="admin-artikel.php" class="btn line">Batal</a><button class="btn" type="submit">Simpan Artikel</button></div>
        </section>
      </form>
      <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<?php admin_layout_end(['js/admin-artikel-edit.js']); ?>