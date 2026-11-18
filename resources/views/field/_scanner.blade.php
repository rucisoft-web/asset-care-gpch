{{-- Kamera pemindai QR. Halaman pemakai mendefinisikan window.fieldOnScan(teks). --}}
<div class="card" style="padding:10px">
    <div style="position:relative;border-radius:12px;overflow:hidden;background:#111;aspect-ratio:{{ $ratio ?? '3/4' }}">
        <video id="v" playsinline muted style="width:100%;height:100%;object-fit:cover"></video>
        <div style="position:absolute;inset:18% 12%;border:3px solid rgba(255,255,255,.85);border-radius:16px"></div>
    </div>
    <canvas id="c" hidden></canvas>
    <p id="msg" class="muted" style="text-align:center;margin:10px 0 0">Membuka kamera…</p>
</div>
<label class="btn alt" style="margin:0 0 10px;font-weight:600">Foto label (bila kamera tidak terbuka)<input id="file" type="file" accept="image/*" capture="environment" hidden></label>
@push('scripts')
<script src="{{ asset('js/vendor/jsQR.js') }}?v=1.4.0"></script>
<script>
(function () {
  const v = document.getElementById('v'), c = document.getElementById('c'), msg = document.getElementById('msg');
  const go = (t) => { if (navigator.vibrate) navigator.vibrate(60); window.fieldOnScan(t); };
  let det = null; if ('BarcodeDetector' in window) { try { det = new BarcodeDetector({ formats: ['qr_code'] }); } catch (e) {} }
  async function decode(src, w, h) {
    if (!w || !h) return null;
    if (det) { try { const r = await det.detect(src); if (r.length) return r[0].rawValue; } catch (e) {} }
    if (!window.jsQR) return null;
    const s = Math.min(1, 900 / Math.max(w, h)); c.width = Math.round(w * s); c.height = Math.round(h * s);
    const x = c.getContext('2d', { willReadFrequently: true }); x.drawImage(src, 0, 0, c.width, c.height);
    const d = x.getImageData(0, 0, c.width, c.height); const r = jsQR(d.data, d.width, d.height, { inversionAttempts: 'attemptBoth' });
    return r ? r.data : null;
  }
  let done = false;
  async function loop() { if (done) return; if (v.readyState >= 2) { const t = await decode(v, v.videoWidth, v.videoHeight); if (t) { done = true; msg.textContent = 'Terbaca, membuka…'; return go(t); } } setTimeout(loop, 200); }
  if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.isSecureContext) {
    navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false })
      .then(s => { v.srcObject = s; v.play(); msg.textContent = 'Arahkan kotak ke label QR (±15 cm).'; loop(); })
      .catch(e => { msg.textContent = 'Kamera tidak bisa dibuka. Izinkan akses kamera, atau pakai "Foto label".'; });
  } else { msg.textContent = 'Kamera langsung butuh HTTPS. Pakai tombol "Foto label".'; }
  document.getElementById('file').addEventListener('change', (ev) => {
    const f = ev.target.files[0]; if (!f) return; msg.textContent = 'Membaca foto…';
    const img = new Image(); const u = URL.createObjectURL(f);
    img.onload = async () => { const t = await decode(img, img.naturalWidth, img.naturalHeight); URL.revokeObjectURL(u); if (t) go(t); else msg.textContent = 'QR tidak terbaca. Dekatkan kamera dan hindari pantulan.'; };
    img.src = u;
  });
})();
</script>
@endpush
