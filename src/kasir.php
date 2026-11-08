<?php
require __DIR__ . '/includes/owner_layout.php';
$u = owner_page_guard($pdo);
owner_layout_start($u, 'kasir', 'Kasir', 'Catat penjualan · ' . e($u['business_name']));
?>
      <style>
        .kasir {
          display: grid;
          grid-template-columns: minmax(0, 1fr) minmax(0, 380px);
          gap: 24px;
          align-items: start;
        }
        
        .kasir-cart {
          position: sticky;
          top: 90px;
          display: flex;
          flex-direction: column;
        }
        
        .cart-body {
          max-height: 200px;
          overflow-y: auto;
          margin-bottom: 16px;
          position: relative;
        }
        
        .cart-body .empty {
          display: flex;
          align-items: center;
          justify-content: center;
          height: 100px;
          text-align: center;
          margin: 0;
          color: var(--mu);
          font-size: 0.9rem;
        }

        .prod-list {
          display: grid;
          gap: 10px;
          max-height: calc(100vh - 220px);
          overflow-y: auto;
          padding-right: 4px;
        }
        .prod {
          display: flex;
          align-items: center;
          gap: 14px;
          padding: 12px;
          border: 1px solid var(--bd);
          border-radius: 14px;
          background: #fff;
          cursor: pointer;
          text-align: left;
          width: 100%;
          transition: border-color .15s, background .15s;
        }
        .prod:hover {
          border-color: var(--g);
          background: var(--gl);
        }
        .prod[disabled] {
          opacity: .5;
          cursor: not-allowed;
        }
        .prod b {
          display: block;
          font-size: .95rem;
          color: var(--tx);
        }
        .prod small {
          color: var(--mu);
          font-size: .82rem;
        }
        .prod .pimg {
          width: 48px;
          height: 48px;
          border-radius: 10px;
        }
        .prod .val {
          font-weight: 700;
          color: var(--gd);
          white-space: nowrap;
        }
        
        .cart-item {
          display: flex;
          align-items: center;
          justify-content: space-between;
          gap: 8px;
          padding: 10px 0;
          border-bottom: 1px solid var(--bd);
        }
        .cart-item:last-child {
          border-bottom: 0;
        }
        .cart-item .grow {
          flex: 1;
          min-width: 0;
          overflow: hidden;
        }
        .cart-item b {
          display: block;
          font-size: .9rem;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
        }
        .cart-item small {
          color: var(--mu);
          font-size: .8rem;
        }
        .qty {
          display: flex;
          align-items: center;
          border: 1px solid var(--bd);
          border-radius: 8px;
          overflow: hidden;
          background: var(--bg);
          flex-shrink: 0;
        }
        .qty button {
          width: 28px;
          height: 28px;
          border: 0;
          background: #fff;
          color: var(--gd);
          font-weight: 800;
          font-size: 1rem;
          cursor: pointer;
        }
        .qty button:hover {
          background: var(--gl);
        }
        .qty input {
          width: 36px;
          height: 28px;
          border: 0;
          border-left: 1px solid var(--bd);
          border-right: 1px solid var(--bd);
          text-align: center;
          font: inherit;
          font-size: .85rem;
          background: #fff;
        }
        .cart-item .link.danger {
          flex-shrink: 0;
          font-size: .8rem;
          padding: 4px 8px;
          border-radius: 8px;
          white-space: nowrap;
        }
        
        .pay-opts {
          display: flex;
          gap: 10px;
          margin: 8px 0 16px;
        }
        .pay-opts label {
          flex: 1;
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 8px;
          padding: 10px;
          border: 1.5px solid #CBD5E1;
          border-radius: 12px;
          cursor: pointer;
          font-weight: 600;
          font-size: .9rem;
          transition: all .15s;
        }
        .pay-opts input {
          accent-color: var(--g);
          margin: 0;
          outline: none !important;
          box-shadow: none !important;
        }
        .pay-opts label:focus,
        .pay-opts label:focus-within,
        .pay-opts label:active,
        .pay-opts input:focus,
        .pay-opts input:focus-visible,
        .pay-opts input:active {
          outline: none !important;
          box-shadow: none !important;
          border-radius: 12px !important;
        }
        .pay-opts label:has(input:checked) {
          border-color: var(--g);
          background: var(--gl);
          color: var(--gd);
          outline: none !important;
        }
        
        .change-row {
          display: flex;
          align-items: baseline;
          gap: 12px;
        }

        #cash-box, #cash-box-sheet {
          min-height: 120px;
        }

        .cart-fab {
          display: none;
          position: fixed;
          left: 16px;
          right: 16px;
          bottom: 16px;
          z-index: 35;
          padding: 14px 18px;
          border: 0;
          border-radius: 14px;
          background: var(--g);
          color: #fff;
          font-weight: 700;
          font-size: 1rem;
          box-shadow: 0 8px 24px rgba(22,163,74,.35);
          align-items: center;
          justify-content: space-between;
          gap: 12px;
          cursor: pointer;
        }
        .cart-fab:active {
          transform: translateY(1px);
        }
        .cart-fab .badge {
          background: rgba(255,255,255,.25);
          color: #fff;
          font-size: .85rem;
          padding: 2px 10px;
          border-radius: 999px;
        }
        .cart-fab .total {
          font-size: 1.05rem;
        }
        .sheet-scrim {
          display: none;
          position: fixed;
          inset: 0;
          z-index: 38;
          background: rgba(15,23,42,.45);
          opacity: 0;
          transition: opacity .2s;
          pointer-events: none;
        }
        .sheet-scrim.on {
          opacity: 1;
          pointer-events: auto;
        }
        .cart-sheet {
          display: none;
        }

        @media (max-width: 1024px) {
          .kasir {
            grid-template-columns: 1fr;
          }
          .kasir-cart {
            display: none;
          }
          .prod-list {
            max-height: none;
            grid-template-columns: repeat(2, 1fr);
          }
          .prod {
            flex-direction: column;
            align-items: flex-start;
            padding: 14px;
            min-height: 100px;
          }
          .prod .pimg {
            width: 56px;
            height: 56px;
          }
          .prod .val {
            margin-top: 4px;
          }
          .cart-fab {
            display: flex;
          }
          .sheet-scrim {
            display: block;
          }
          .cart-sheet {
            display: block;
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 40;
            background: #fff;
            border-radius: 20px 20px 0 0;
            box-shadow: 0 -8px 32px rgba(15,23,42,.18);
            transform: translateY(100%);
            transition: transform .25s ease-out;
            max-height: 85vh;
            overflow-y: auto;
            padding: 0 20px 24px;
          }
          .cart-sheet.open {
            transform: none;
          }
          .cart-sheet .sheet-handle {
            position: sticky;
            top: 0;
            z-index: 1;
            display: flex;
            justify-content: center;
            padding: 12px 0 8px;
            background: #fff;
            border-radius: 20px 20px 0 0;
          }
          .cart-sheet .sheet-handle i {
            width: 40px;
            height: 4px;
            border-radius: 99px;
            background: #CBD5E1;
          }
        }

        @media (max-width: 600px) {
          .prod-list {
            grid-template-columns: 1fr;
          }
          .prod {
            flex-direction: row;
            min-height: 80px;
          }
          #cash-box-sheet { min-height: 120px; }
        }

        @media (any-pointer: coarse) {
          .qty button {
            width: 36px;
            height: 36px;
            font-size: 1.2rem;
          }
          .qty input {
            height: 36px;
            width: 44px;
            font-size: 1rem;
          }
          .prod {
            min-height: 88px;
          }
          .btn {
            min-height: 48px;
          }
          .link {
            padding: 10px 14px;
          }
          button, a {
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
          }
        }
      </style>

      <div class="note-e" id="err" role="alert" hidden></div>

      <div class="kasir">
        <section class="card" aria-labelledby="h-prod">
          <div class="card-h">
            <h2 id="h-prod">Pilih Produk</h2>
            <div style="display:flex; gap:8px;">
              <button class="link" type="button" id="reload">Muat ulang</button>
              <button class="btn" type="button" id="btn-scan-kasir" style="padding:6px 12px;"><svg class="i" aria-hidden="true"><use href="#i-camera"/></svg> Scan</button>
            </div>
          </div>
          <div class="sbox" style="margin-bottom:16px">
            <svg class="i" aria-hidden="true"><use href="#i-search"/></svg>
            <input id="q" type="search" placeholder="Cari nama atau kode produk" aria-label="Cari produk" maxlength="60" autocomplete="off">
          </div>
          <div class="prod-list" id="prod-list"><p class="empty">Memuat produk…</p></div>
        </section>

        <section class="card kasir-cart" aria-labelledby="h-cart">
          <div class="card-h">
            <h2 id="h-cart">Keranjang</h2>
            <span class="badge n" id="cart-count">0 item</span>
          </div>
          
          <div class="cart-body" id="cart-items">
            <p class="empty">Keranjang kosong. Pilih produk.</p>
          </div>

          <div class="fld" style="margin-bottom:12px">
            <input id="cust" maxlength="100" autocomplete="off" placeholder="Nama pelanggan (opsional)">
          </div>

          <div class="total-row" style="margin-top:0">
            <span>Total</span>
            <b id="total" style="font-size:1.4rem;color:var(--gd)">Rp0</b>
          </div>

          <div class="fld" style="margin-bottom:8px">
            <label>Metode bayar</label>
            <div class="pay-opts" role="radiogroup" aria-label="Metode bayar">
              <label><input type="radio" name="pay" value="Tunai" checked> Tunai</label>
              <label><input type="radio" name="pay" value="QRIS"> QRIS</label>
            </div>
          </div>

          <div id="cash-box">
            <div class="fld">
              <label for="cash">Uang pelanggan <span class="opt">(wajib diisi)</span></label>
              <input id="cash" type="text" inputmode="numeric" placeholder="0" style="font-size:1.1rem; font-weight:700;">
            </div>
            <div class="total-row change-row" style="border-top:0;padding-top:0;margin-top:0;margin-bottom:12px;">
              <span id="change-label">Kembalian</span> <b id="change" style="color:var(--warn)">Rp0</b>
            </div>
          </div>

          <p class="ferr" id="e-form" role="alert"></p>
          
          <div class="modal-f" style="margin-top:16px; display:flex; flex-direction:column; gap:8px;">
            <button class="btn" type="button" id="checkout" style="width:100%; min-height:48px; font-size:1rem;" disabled><svg class="i" aria-hidden="true"><use href="#i-check"/></svg>Bayar</button>
            <button class="btn line" type="button" id="clear" style="width:100%">Kosongkan</button>
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
        
        <div id="cart-items-sheet" style="max-height:200px; overflow-y:auto; margin-bottom:16px;">
          <p class="empty" style="display:flex; align-items:center; justify-content:center; height:100px; text-align:center; margin:0; color:var(--mu); font-size:0.9rem;">Keranjang kosong.</p>
        </div>
        
        <div class="fld" style="margin-top:16px">
          <label for="cust-sheet">Nama pelanggan <span class="opt">(opsional)</span></label>
          <input id="cust-sheet" maxlength="100" autocomplete="off" placeholder="Pelanggan Umum">
        </div>
        
        <div class="total-row change-row"><span>Total</span> <b id="total-sheet" style="color:var(--gd)">Rp0</b></div>
        
        <div class="fld">
          <label>Metode bayar</label>
          <div class="pay-opts" role="radiogroup" aria-label="Metode bayar">
            <label><input type="radio" name="pay-sheet" value="Tunai" checked> Tunai</label>
            <label><input type="radio" name="pay-sheet" value="QRIS"> QRIS</label>
          </div>
        </div>
        
        <div id="cash-box-sheet">
          <div class="fld">
            <label for="cash-sheet">Uang pelanggan <span class="opt">(wajib diisi)</span></label>
            <input id="cash-sheet" type="text" inputmode="numeric" placeholder="0" style="font-size:1.1rem; font-weight:700;">
          </div>
          <div class="total-row change-row" style="border-top:0;padding-top:0;margin-top:0;">
            <span id="change-label-sheet">Kembalian</span> <b id="change-sheet" style="color:var(--warn)">Rp0</b>
          </div>
        </div>
        
        <p class="ferr" id="e-form-sheet" role="alert"></p>
        
        <div class="modal-f" style="display:flex; flex-direction:column; gap:8px; margin-top:16px;">
          <button class="btn" type="button" id="checkout-sheet" style="width:100%; min-height:48px;" disabled><svg class="i" aria-hidden="true"><use href="#i-check"/></svg>Bayar</button>
          <button class="btn line" type="button" id="clear-sheet" style="width:100%">Kosongkan</button>
        </div>
      </section>
<?php owner_layout_end(['js/kasir.js']); ?>