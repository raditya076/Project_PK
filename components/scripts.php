<?php
/**
 * ====================================================
 * FILE: components/scripts.php
 * FUNGSI: Komponen penutup halaman.
 * Berisi: Bootstrap JS, script custom, penutup </body></html>
 *
 * Selalu include file ini di BAWAH setiap halaman (sebelum akhir file).
 * ====================================================
 */
?>

    <!-- Bootstrap 5 JS Bundle (termasuk Popper.js untuk dropdown, modal, tooltip) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    /**
     * FUNGSI: Animasi sederhana saat kartu kos muncul (fade in dari bawah)
     * Menggunakan Intersection Observer API untuk efek scroll reveal
     */
    const cards = document.querySelectorAll('.kos-card');
    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.style.opacity    = '1';
                    entry.target.style.transform  = 'translateY(0)';
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        cards.forEach(function(card, index) {
            // Set kondisi awal (tersembunyi)
            card.style.opacity   = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.4s ease ' + (index * 0.07) + 's, transform 0.4s ease ' + (index * 0.07) + 's';
            observer.observe(card);
        });
    }
    </script>

    <!-- ============================================================
         CUSTOM CONFIRM DIALOG — Menggantikan browser native confirm()
         Berlaku global di semua halaman via scripts.php
         ============================================================ -->
    <div id="kosta-confirm-overlay" aria-modal="true" role="dialog" aria-labelledby="kosta-confirm-title">
        <div id="kosta-confirm-box">
            <div id="kosta-confirm-icon">&#9888;&#65039;</div>
            <h3 id="kosta-confirm-title">Konfirmasi</h3>
            <p  id="kosta-confirm-msg"></p>
            <div id="kosta-confirm-btns">
                <button id="kosta-btn-cancel" type="button">Batal</button>
                <button id="kosta-btn-ok"     type="button">Ya, Lanjutkan</button>
            </div>
        </div>
    </div>

    <style>
    /* ── Kosta' Custom Confirm Dialog ──────────────────────────────
       PENTING: Jangan pakai display:flex !important di sini.
       Default: none. Dialog tampil hanya saat JS menambah .kosta-show
    ─────────────────────────────────────────────────────────────── */
    #kosta-confirm-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(28, 28, 28, 0.55);
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
        z-index: 99999;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }
    #kosta-confirm-overlay.kosta-show {
        display: flex;
        animation: kosta-fade-in 0.18s ease;
    }
    @keyframes kosta-fade-in {
        from { opacity: 0; }
        to   { opacity: 1; }
    }
    @keyframes kosta-pop-in {
        from { opacity: 0; transform: scale(0.90) translateY(14px); }
        to   { opacity: 1; transform: scale(1)    translateY(0); }
    }
    #kosta-confirm-box {
        background: #FFFFFF;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.18), 0 4px 16px rgba(0,0,0,0.08);
        padding: 36px 32px 28px;
        max-width: 400px;
        width: 100%;
        text-align: center;
        font-family: 'Plus Jakarta Sans', sans-serif;
        border: 1.5px solid #E8E6E3;
        animation: kosta-pop-in 0.24s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    #kosta-confirm-icon {
        font-size: 42px;
        margin-bottom: 14px;
        line-height: 1;
    }
    #kosta-confirm-title {
        font-size: 17px;
        font-weight: 800;
        color: #1C1C1C;
        letter-spacing: -0.3px;
        margin-bottom: 8px;
        font-family: 'Plus Jakarta Sans', sans-serif;
    }
    #kosta-confirm-msg {
        font-size: 13px;
        color: #6B6B6B;
        line-height: 1.65;
        margin-bottom: 24px;
        font-family: 'Plus Jakarta Sans', sans-serif;
    }
    #kosta-confirm-btns {
        display: flex;
        gap: 10px;
        justify-content: center;
    }
    #kosta-btn-cancel,
    #kosta-btn-ok {
        flex: 1;
        padding: 11px 20px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 700;
        font-family: 'Plus Jakarta Sans', sans-serif;
        cursor: pointer;
        transition: all 0.18s ease;
        outline: none;
    }
    #kosta-btn-cancel {
        border: 1.5px solid #E8E6E3;
        background: #FAF7F2;
        color: #6B6B6B;
    }
    #kosta-btn-cancel:hover {
        border-color: #C50000;
        color: #C50000;
        background: #FFF5F5;
    }
    #kosta-btn-ok {
        border: none;
        background: #C50000;
        color: #FFFFFF;
        box-shadow: 0 3px 10px rgba(197,0,0,0.28);
    }
    #kosta-btn-ok:hover {
        background: #E70000;
        transform: translateY(-1px);
        box-shadow: 0 5px 14px rgba(197,0,0,0.35);
    }
    #kosta-btn-ok:active  { transform: translateY(0); }
    #kosta-btn-ok.danger  { background: #b91c1c; }
    #kosta-btn-ok.danger:hover  { background: #991b1b; }
    #kosta-btn-ok.success { background: #15803d; box-shadow: 0 3px 10px rgba(21,128,61,0.3); }
    #kosta-btn-ok.success:hover { background: #166534; }
    </style>

    <script>
    (function () {
        /* ── State ── */
        var _cb         = null;   /* Callback aktif */
        var _keyHandler = null;   /* Escape handler */

        /* ── Referensi elemen ── */
        var overlay, iconEl, titleEl, msgEl, btnOk, btnCan;

        function getEls() {
            overlay = document.getElementById('kosta-confirm-overlay');
            iconEl  = document.getElementById('kosta-confirm-icon');
            titleEl = document.getElementById('kosta-confirm-title');
            msgEl   = document.getElementById('kosta-confirm-msg');
            btnOk   = document.getElementById('kosta-btn-ok');
            btnCan  = document.getElementById('kosta-btn-cancel');
        }

        /* ── Tutup dialog ── */
        function closeDialog(confirmed) {
            if (!overlay) return;
            overlay.classList.remove('kosta-show');
            if (_keyHandler) {
                document.removeEventListener('keydown', _keyHandler);
                _keyHandler = null;
            }
            var cb = _cb;
            _cb = null;
            if (confirmed && cb) cb();
        }

        /* ── Pasang event listener SEKALI setelah DOM siap ── */
        document.addEventListener('DOMContentLoaded', function () {
            getEls();

            btnOk.addEventListener('click',  function () { closeDialog(true);  });
            btnCan.addEventListener('click', function () { closeDialog(false); });
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) closeDialog(false);
            });

            /* ── Auto-intercept form onsubmit="return confirm(...)" ── */
            document.querySelectorAll('form[onsubmit]').forEach(function (form) {
                var attr  = form.getAttribute('onsubmit') || '';
                var m     = attr.match(/return\s+confirm\((['"])(.+?)\1\)/);
                if (!m) return;
                var msg = m[2];
                form.removeAttribute('onsubmit');
                form.addEventListener('submit', function handler(e) {
                    e.preventDefault();
                    window.kostaConfirm(msg, function () {
                        form.removeEventListener('submit', handler);
                        form.submit();
                    });
                });
            });

            /* ── Auto-intercept onclick="return confirm(...)" ── */
            document.querySelectorAll('[onclick]').forEach(function (el) {
                var attr = el.getAttribute('onclick') || '';
                var m    = attr.match(/return\s+confirm\((['"])(.+?)\1\)/);
                if (!m) return;
                var msg = m[2];
                el.removeAttribute('onclick');
                el.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var target = el;
                    window.kostaConfirm(msg, function () {
                        if (target.tagName === 'A') {
                            window.location.href = target.href;
                        } else {
                            var frm = target.closest('form');
                            if (frm) frm.submit();
                        }
                    });
                });
            });
        });

        /* ── API Global ── */
        /**
         * window.kostaConfirm(msg, callback, opts?)
         *
         * opts: { title, icon, okText, okClass: 'danger'|'success' }
         */
        window.kostaConfirm = function (msg, callback, opts) {
            if (!overlay) getEls();
            opts = opts || {};
            iconEl.textContent  = opts.icon   || '\u26A0\uFE0F';
            titleEl.textContent = opts.title  || 'Konfirmasi';
            msgEl.textContent   = msg;
            btnOk.textContent   = opts.okText || 'Ya, Lanjutkan';
            btnOk.className     = opts.okClass || '';
            _cb = callback;
            if (_keyHandler) document.removeEventListener('keydown', _keyHandler);
            _keyHandler = function (e) { if (e.key === 'Escape') closeDialog(false); };
            document.addEventListener('keydown', _keyHandler);
            overlay.classList.add('kosta-show');
            setTimeout(function () { if (btnCan) btnCan.focus(); }, 60);
        };
    })();
    </script>

</body>
</html>
