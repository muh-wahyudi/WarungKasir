<?php
// Penyimpanan foto produk yang aman. Berkas disimpan di src/uploads/produk/<id pemilik>/<nama acak>.<ekstensi>.
const UPLOAD_ROOT = __DIR__ . '/../uploads';
const MAX_IMAGE_BYTES = 2 * 1024 * 1024;     // 2 MB (sama dengan batas upload_max_filesize bawaan PHP)
const MAX_IMAGE_PIXELS = 16000000;           // 16 megapiksel, mencegah gambar raksasa menghabiskan memori
const MAX_IMAGE_SIDE = 800;                  // sisi terpanjang saat diproses ulang oleh GD

class UploadError extends RuntimeException {
    public int $status;
    public function __construct(string $message, int $status = 400) { parent::__construct($message); $this->status = $status; }
}

// Tolak permintaan yang berasal dari situs lain (perlindungan CSRF, sama seperti API JSON).
function require_same_origin(): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]) {
        json_out(403, ['message' => 'Permintaan ditolak.']);
    }
}

// Buat folder bila belum ada. Setiap folder diberi index.html kosong agar isinya tidak bisa dilihat sebagai daftar berkas.
function ensure_upload_dir(string $rel): string {
    $path = UPLOAD_ROOT;
    foreach (array_merge([''], explode('/', $rel)) as $seg) {
        if ($seg !== '') $path .= '/' . $seg;
        if (!is_dir($path) && !@mkdir($path, 0755) && !is_dir($path)) {
            throw new UploadError('Folder unggahan tidak dapat dibuat. Periksa izin folder "uploads".', 500);
        }
        if (!is_file($path . '/index.html')) @file_put_contents($path . '/index.html', '');
    }
    return $path;
}

// Lokasi foto di database harus berbentuk persis seperti yang dibuat di sini.
function valid_image_path(?string $rel): bool {
    return $rel !== null && preg_match('#^produk/\d+/[a-f0-9]{32}\.(jpg|png|webp)$#', $rel) === 1;
}

function delete_image_file(?string $rel): void {
    if (valid_image_path($rel)) @unlink(UPLOAD_ROOT . '/' . $rel);
}

function gd_loader(string $mime): ?string {
    if (!extension_loaded('gd')) return null;
    $fn = ['image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng', 'image/webp' => 'imagecreatefromwebp'][$mime] ?? null;
    return ($fn && function_exists($fn)) ? $fn : null;
}

// Proses ulang gambar dengan GD: buang data tersembunyi (EXIF, kode yang ditempelkan), perkecil, simpan sebagai JPEG.
function gd_reencode(string $src, string $loader, string $dest): bool {
    $im = @$loader($src);
    if (!$im) return false;
    $w = imagesx($im); $h = imagesy($im);
    $scale = min(1, MAX_IMAGE_SIDE / max($w, $h));
    $nw = max(1, (int) round($w * $scale)); $nh = max(1, (int) round($h * $scale));
    $out = imagecreatetruecolor($nw, $nh);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));   // latar putih untuk gambar transparan
    imagecopyresampled($out, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $ok = imagejpeg($out, $dest, 82);
    imagedestroy($im); imagedestroy($out);
    return $ok;
}

// Validasi lalu simpan foto. Mengembalikan lokasi relatif (disimpan di database), atau melempar UploadError.
function save_product_image(array $f, int $uid): string {
    $err = $f['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) throw new UploadError('Ukuran foto terlalu besar. Maksimal 2 MB.', 413);
    if ($err !== UPLOAD_ERR_OK) throw new UploadError('Foto gagal diunggah. Coba lagi.', 400);
    $tmp = (string) ($f['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp)) throw new UploadError('Berkas tidak valid.', 400);
    if (($f['size'] ?? 0) > MAX_IMAGE_BYTES || filesize($tmp) > MAX_IMAGE_BYTES) throw new UploadError('Ukuran foto terlalu besar. Maksimal 2 MB.', 413);

    // Tipe ditentukan dari ISI berkas, bukan dari nama atau tipe yang dikirim pengguna.
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if ($ext === null) throw new UploadError('Format foto harus JPG, PNG, atau WebP.', 415);
    $info = @getimagesize($tmp);
    if (!$info || ($info['mime'] ?? '') !== $mime || $info[0] < 1 || $info[1] < 1) throw new UploadError('Berkas bukan gambar yang valid.', 400);
    if ($info[0] * $info[1] > MAX_IMAGE_PIXELS) throw new UploadError('Resolusi foto terlalu besar. Perkecil fotonya dulu.', 400);

    $dir = ensure_upload_dir("produk/$uid");
    $name = bin2hex(random_bytes(16));
    $loader = gd_loader($mime);
    if ($loader) {
        $ext = 'jpg';
        if (!gd_reencode($tmp, $loader, "$dir/$name.$ext")) { @unlink("$dir/$name.$ext"); throw new UploadError('Foto tidak dapat diproses. Gunakan foto lain.', 400); }
    } elseif (!move_uploaded_file($tmp, "$dir/$name.$ext")) {
        throw new UploadError('Foto gagal disimpan. Periksa izin folder "uploads".', 500);
    }
    @chmod("$dir/$name.$ext", 0644);
    return "produk/$uid/$name.$ext";
}