<?php
require __DIR__ . '/includes/owner_layout.php';
$u = owner_page_guard($pdo);
owner_layout_start($u, 'kasir', 'Kasir', 'Catat penjualan · ' . e($u['business_name']));
?>
      <style>
        .kasir{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);gap:16px;align-items:start}
        .kasir-cart{position:sticky;top:76px}
        .prod-list{display:grid;gap:8px;max-height:60vh;overflow:auto;padding-right:4px}
        .prod{display:flex;align-items:center;gap:12px;padding:10px;border:1px solid var(--bd);border-radius:12px;background:#fff;cursor:pointer;text-align:left;width:100%}
        .prod:hover{border-color:var(--g);background:var(--gl)}
        .prod[disabled]{opacity:.5;cursor:not-allowed}
        .prod b{display:block;font-size:.95rem}
        .prod small{color:var(--mu);font-size:.82rem}
        .prod .pimg{width:40px;height:40px}
        .cart-item{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--bd)}
        .cart-item:last-child{border-bottom:0}
        .cart-item .grow{flex:1;min-width:0}
        .cart-item b{display:block;font-size:.92rem}
        .cart-item small{color:var(--mu);font-size:.8rem}
        .qty{display:inline-flex;align-items:center;border:1px solid var(--bd);border-radius:8px;overflow:hidden}
        .qty button{width:28px;height:28px;border:0;background:#fff;color:var(--gd);font-weight:800;font-size:1rem}
        .qty button:hover{background:var(--gl)}
        .qty input{width:44px;height:28px;border:0;border-left:1px solid var(--bd);border-right:1px solid var(--bd);text-align:center;font:inherit;font-size:.9rem}
        .total-row{display:flex;justify-content:space-between;align-items:baseline;padding:10px 0;border-top:1px solid var(--bd);margin-top:8px}
        .total-row b{font-size:1.35rem}
        .pay-opts{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0 12px}
        .pay-opts label{display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border:1.5px solid #CBD5E1;border-radius:10px;cursor:pointer;font-weight:600;font-size:.9rem}
        .pay-opts input{accent-color:var(--g)}
        .pay-opts label:has(input:checked){border-color:var(--g);background:var(--gl);color:var(--gd)}
        .cart-fab{display:none;position:fixed;left:16px;right:16px;bottom:16px;z-index:35;padding:14px 18px;border:0;border-radius:14px;background:var(--g);color:#fff;font-weight:700;font-size:1rem;box-shadow:0 8px 24px rgba(22,163,74,.35);align-items:center;justify-content:space-between;gap:12px;cursor:pointer}
        .cart-fab:active{transform:translateY(1px)}
        .cart-fab .badge{background:rgba(255,255,255,.25);color:#fff;font-size:.85rem;padding:2px 10px;border-radius:999px}
        .cart-fab .total{font-size:1.05rem}
        .sheet-scrim{display:none;position:fixed;inset:0;z-index:38;background:rgba(15,23,42,.45);opacity:0;transition:opacity .2s;pointer-events:none}
        .sheet-scrim.on{opacity:1;pointer-events:auto}
        .cart-sheet{display:none}
        @media(max-width:900px){
          .kasir{grid-template-columns:1fr}
          .kasir-cart{display:none}
          .prod-list{max-height:none;grid-template-columns:repeat(2,1fr)}
          .prod{flex-direction:column;align-items:flex-start;padding:12px;min-height:84px}
          .prod .pimg{width:48px;height:48px}
          .cart-fab{display:flex}
          .sheet-scrim{display:block}
          .cart-sheet{display:block;position:fixed;left:0;right:0;bottom:0;z-index:40;background:#fff;border-radius:20px 20px 0 0;box-shadow:0 -8px 32px rgba(15,23,42,.18);transform:translateY(100%);transition:transform .25s ease-out;max-height:82vh;overflow:auto;padding:0 16px 24px}
          .cart-sheet.open{transform:none}
          .cart-sheet .sheet-handle{position:sticky;top:0;z-index:1;display:flex;justify-content:center;padding:10px 0 6px;background:#fff;border-radius:20px 20px 0 0}
          .cart-sheet .sheet-handle i{width:40px;height:4px;border-radius:99px;background:#CBD5E1}
          .cart-sheet .card-h{margin-top:4px}
        }
        @media(any-pointer:coarse){
          .qty button{width:44px;height:44px;font-size:1.15rem}
          .qty input{height:44px;width:52px}
          .prod{min-height:88px}
          .btn{min-height:48px}
          .link{padding:10px 14px}
          button,a{-webkit-tap-highlight-color:transparent;touch-action:manipulation}
        }
      </style>

      <div class="note-e" id="err" role="alert" hidden></div>

      <div class="kasir">
        <section class="card" aria-labelledby="h-prod">
          <div class="card-h">
            <h2 id="h-prod">Pilih Produk</h2>
            <button class="link" type="button" id="reload">Muat ulang</button>
          </div>
          <div class="sbox" style="margin-bottom:12px">
            <svg class="i" aria-hidden="true"><use href="#i-search"/></svg>
            <input id="q" type="search" placeholder="Cari nama atau kode produk" aria-label="Cari produk" maxlength="60" autocomplete="off">
          </div>
          <div class="prod-list" id="prod-list"><p class="empty">Memuat produk…</p></div>
        </section>

        <section class="card kasir-cart" aria-labelledby="h-cart">
          <div class="card-h"><h2 id="h-cart">Keranjang</h2><span class="badge n" id="cart-count">0 item</span></div>
          <div id="cart-items"><p class="empty">Keranjang kosong. Pilih produk di sebelah kiri.</p></div>
          <div class="fld" style="margin-top:12px">
            <label for="cust">Nama pelanggan <span class="opt">(opsional)</span></label>
            <input id="cust" maxlength="100" autocomplete="off" placeholder="Pelanggan Umum">
          </div>
          <div class="total-row"><span>Total</span><b id="total">Rp0</b></div>
          <div class="fld">
            <label>Metode bayar</label>
            <div class="pay-opts" role="radiogroup" aria-label="Metode bayar">
              <label><input type="radio" name="pay" value="Tunai" checked> Tunai</label>
              <label><input type="radio" name="pay" value="QRIS"> QRIS</label>
              <label><input type="radio" name="pay" value="Transfer"> Transfer</label>
            </div>
          </div>
          <div id="cash-box">
            <div class="fld">
              <label for="cash">Uang pelanggan</label>
              <input id="cash" type="number" inputmode="numeric" min="0" step="1" placeholder="0">
            </div>
            <div class="total-row" style="border-top:0;padding-top:0;margin-top:0">
              <span>Kembalian</span><b id="change">Rp0</b>
            </div>
          </div>
          <p class="ferr" id="e-form" role="alert"></p>
          <div class="modal-f">
            <button class="btn line" type="button" id="clear">Kosongkan</button>
            <button class="btn" type="button" id="checkout"><svg class="i" aria-hidden="true"><use href="#i-check"/></svg>Simpan Transaksi</button>
          </div>
        </section>
      </div>

      <button class="cart-fab" type="button" id="cart-fab">
        <span>Keranjang <span class="badge" id="fab-count">0</span></span>
        <span class="total" id="fab-total">Rp0</span>
      </button>

      <div class="sheet-scrim" id="sheet-scrim"></div>

      <section class="cart-sheet" id="cart-sheet" aria-label="Keranjang belanja">
        <div class="sheet-handle"><i></i></div>
        <div class="card-h"><h2>Keranjang</h2><button class="link" type="button" id="sheet-close">Tutup</button></div>
        <div id="cart-items-sheet"><p class="empty">Keranjang kosong.</p></div>
        <div class="fld" style="margin-top:12px">
          <label for="cust-sheet">Nama pelanggan <span class="opt">(opsional)</span></label>
          <input id="cust-sheet" maxlength="100" autocomplete="off" placeholder="Pelanggan Umum">
        </div>
        <div class="total-row"><span>Total</span><b id="total-sheet">Rp0</b></div>
        <div class="fld">
          <label>Metode bayar</label>
          <div class="pay-opts" role="radiogroup" aria-label="Metode bayar">
            <label><input type="radio" name="pay-sheet" value="Tunai" checked> Tunai</label>
            <label><input type="radio" name="pay-sheet" value="QRIS"> QRIS</label>
            <label><input type="radio" name="pay-sheet" value="Transfer"> Transfer</label>
          </div>
        </div>
        <div id="cash-box-sheet">
          <div class="fld">
            <label for="cash-sheet">Uang pelanggan</label>
            <input id="cash-sheet" type="number" inputmode="numeric" min="0" step="1" placeholder="0">
          </div>
          <div class="total-row" style="border-top:0;padding-top:0;margin-top:0">
            <span>Kembalian</span><b id="change-sheet">Rp0</b>
          </div>
        </div>
        <p class="ferr" id="e-form-sheet" role="alert"></p>
        <div class="modal-f">
          <button class="btn line" type="button" id="clear-sheet">Kosongkan</button>
          <button class="btn" type="button" id="checkout-sheet"><svg class="i" aria-hidden="true"><use href="#i-check"/></svg>Simpan Transaksi</button>
        </div>
      </section>
<?php owner_layout_end(['js/kasir.js']); ?>