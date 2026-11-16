<?php
require __DIR__ . '/includes/admin_layout.php';
$u = admin_layout_guard($pdo);
$cats = $pdo->query('SELECT * FROM article_categories ORDER BY sort_order ASC, name ASC')->fetchAll();
$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
admin_layout_start($u, 'kategori', 'Kategori Artikel', 'Admin / Kategori Artikel');
?>
      <div class="ph"><h2>Kategori Artikel</h2><p>Kategori untuk mengelompokkan artikel.</p></div>
      <section class="card">
        <div class="wrap">
          <table class="tbl">
            <thead><tr><th>Nama</th><th>Slug</th><th>Deskripsi</th><th>Urutan</th></tr></thead>
            <tbody>
            <?php foreach ($cats as $c): ?>
              <tr>
                <td><b><?= $h($c['name']) ?></b></td>
                <td><?= $h($c['slug']) ?></td>
                <td><?= $h($c['description']) ?></td>
                <td><?= (int) $c['sort_order'] ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$cats): ?>
              <tr><td colspan="4" class="empty">Belum ada kategori.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
<?php admin_layout_end([]); ?>