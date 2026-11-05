/**
 * A customer's PDF reaches the press as THEIR file, not a picture of it.
 *
 * Drives the real assets/pdf-export.js in a real headless browser against a
 * real production ZIP. Skips cleanly when the dev dependencies are absent.
 */
'use strict';
const path = require('path');
const fs   = require('fs');
const http = require('http');

const ROOT = path.resolve(__dirname, '..');

let chromium, PDFDocument, rgb, JSZip;
try {
  ({ chromium }      = require('playwright'));
  ({ PDFDocument, rgb } = require('pdf-lib'));
  JSZip              = require('jszip');
  require.resolve('pdfjs-dist/build/pdf.min.mjs');
} catch (e) {
  console.log('SKIP  pdf-passthrough: dev dependencies missing. cd tests && npm i');
  process.exit(0);
}

let failures = 0;
function ok(label, cond, extra) {
  console.log((cond ? '  PASS  ' : '  FAIL  ') + label + (extra ? '   ' + extra : ''));
  if (!cond) failures++;
}

const TYPES = { '.html':'text/html', '.js':'text/javascript', '.mjs':'text/javascript', '.map':'application/json' };
function serve(dir, port) {
  return new Promise(resolve => {
    const s = http.createServer((req, res) => {
      const f = path.join(dir, decodeURIComponent(req.url.split('?')[0]));
      fs.readFile(f, (e, d) => {
        if (e) { res.writeHead(404); res.end('not found'); return; }
        res.writeHead(200, { 'Content-Type': TYPES[path.extname(f)] || 'application/octet-stream' });
        res.end(d);
      });
    });
    s.listen(port, () => resolve(s));
  });
}

(async () => {
  // node_modules has to be reachable from the page, so serve the repo root
  // with a symlink to the test deps.
  const link = path.join(ROOT, 'node_modules');
  let madeLink = false;
  if (!fs.existsSync(link)) {
    try { fs.symlinkSync(path.join(__dirname, 'node_modules'), link, 'dir'); madeLink = true; }
    catch (e) { console.log('SKIP  pdf-passthrough: could not reach test deps.'); process.exit(0); }
  }
  const server = await serve(ROOT, 8731);
  const browser = await chromium.launch({ args: ['--no-sandbox'] });

  try {
    const page = await browser.newPage();
    const pageErrors = [];
    page.on('pageerror', e => pageErrors.push(String(e)));
    await page.goto('http://localhost:8731/tests/page.html');

    ok('pdf-lib loads from the plugin, not a CDN',
       await page.evaluate(() => !!(window.PDFLib && window.PDFLib.PDFDocument && window.PDFLib.degrees)));
    ok('the exporter loads',
       await page.evaluate(() => !!(window.BTDTF_PDF && window.BTDTF_PDF.buildPdfs)));

    /* ---- 1. the customer's PDF is placed, not redrawn ---------------- */
    const src = await PDFDocument.create();
    const sp  = src.addPage([6 * 72, 8 * 72]);
    sp.drawRectangle({ x: 0, y: 0, width: 6 * 72, height: 8 * 72, color: rgb(0, 0, 0) });
    const srcPdf = await src.save();

    // A deliberately useless 1x1 preview PNG. If the exporter ever falls back
    // to the raster, it shows up here instead of being a subtle quality loss.
    const png1x1 = Buffer.from(
      '89504e470d0a1a0a0000000d4948445200000001000000010802000000907753de' +
      '0000000c4944415408d76360000002000154a24f9e0000000049454e44ae426082', 'hex');

    const zip = new JSZip();
    zip.file('01_Orig_x1.pdf', srcPdf);
    zip.file('01_Orig_x1.png', png1x1);
    const zipB64 = (await zip.generateAsync({ type: 'nodebuffer' })).toString('base64');

    const run = (plan) => page.evaluate(async ([zipB64, plan]) => {
      const bin = Uint8Array.from(atob(zipB64), c => c.charCodeAt(0));
      const z = await JSZip.loadAsync(bin);
      const pdfs = {};
      const res = await window.BTDTF_PDF.buildPdfs(plan, {
        PDFLib: window.PDFLib,
        getArt: function (it) {
          if (it.pdf && z.file(it.pdf)) {
            if (!pdfs[it.pdf]) pdfs[it.pdf] = z.file(it.pdf).async('uint8array');
            return pdfs[it.pdf].then(b => ({ kind: 'pdf', file: it.pdf, bytes: b,
                                             pageIndex: Math.max(0, (parseInt(it.pdfPage, 10) || 1) - 1) }));
          }
          return z.file(it.file).async('uint8array').then(b => ({ kind: 'png', bytes: b, dpi: 1 }));
        }
      }, null);
      const by = res.docs[0].bytes;
      let s = '';
      for (let i = 0; i < by.length; i += 0x8000) s += String.fromCharCode.apply(null, by.subarray(i, i + 0x8000));
      return { b64: btoa(s), passthrough: res.passthrough, docs: res.docs.length };
    }, [zipB64, plan]);

    const SLOT = { x: 2, y: 1, w: 6, h: 8 };
    const planFor = (r) => ({
      source: 'layout', sheetW: 22, pieces: 1,
      pages: [{ top: 0, h: 12, items: [{ file: '01_Orig_x1.png', pdf: '01_Orig_x1.pdf', pdfPage: 1,
                                          x: SLOT.x, y: SLOT.y, w: SLOT.w, h: SLOT.h, r: r }] }]
    });

    const flat = await run(planFor(0));
    const raw  = Buffer.from(flat.b64, 'base64').toString('latin1');
    ok('the piece is counted as passed through', flat.passthrough === 1, 'passthrough=' + flat.passthrough);
    ok('output keeps the page as a form XObject', /\/Subtype\s*\/Form/.test(raw));
    ok('output contains NO rasterized image',     !/\/Subtype\s*\/Image/.test(raw));
    ok('the 1x1 preview PNG was not substituted', !/\/Width\s+1[^0-9]/.test(raw));

    /* ---- 2. every piece lands exactly in its slot -------------------- */
    for (const r of [0, 1]) {
      const out = await run(planFor(r));
      const box = await page.evaluate(async (b64) => {
        const pdfjs = await import('/node_modules/pdfjs-dist/build/pdf.min.mjs');
        pdfjs.GlobalWorkerOptions.workerSrc = '/node_modules/pdfjs-dist/build/pdf.worker.min.mjs';
        const data = Uint8Array.from(atob(b64), c => c.charCodeAt(0));
        const doc  = await pdfjs.getDocument({ data }).promise;
        const pg   = await doc.getPage(1);
        const v    = pg.getViewport({ scale: 1 });
        const c    = document.createElement('canvas');
        c.width = Math.round(v.width); c.height = Math.round(v.height);
        const ctx = c.getContext('2d');
        ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height);
        await pg.render({ canvasContext: ctx, viewport: v, background: 'white' }).promise;
        const d = ctx.getImageData(0, 0, c.width, c.height).data;
        let minX = 1e9, minY = 1e9, maxX = -1, maxY = -1;
        for (let y = 0; y < c.height; y++) for (let x = 0; x < c.width; x++) {
          const i = (y * c.width + x) * 4;
          if (d[i] < 128 && d[i+1] < 128 && d[i+2] < 128) {
            if (x < minX) minX = x; if (x > maxX) maxX = x;
            if (y < minY) minY = y; if (y > maxY) maxY = y;
          }
        }
        return { minX, minY, maxX, maxY };
      }, out.b64);

      const left = box.minX / 72, top = box.minY / 72;
      const w = (box.maxX + 1) / 72 - left, h = (box.maxY + 1) / 72 - top;
      const near = (a, b) => Math.abs(a - b) < 0.05;
      ok(`piece lands exactly in its slot (rotated=${r})`,
         near(left, SLOT.x) && near(top, SLOT.y) && near(w, SLOT.w) && near(h, SLOT.h),
         `left ${left.toFixed(2)} top ${top.toFixed(2)} ${w.toFixed(2)}x${h.toFixed(2)}in`);
    }

    /* ---- 3. an order packed before 0.9.0 still exports --------------- */
    const legacy = {
      source: 'layout', sheetW: 22, pieces: 1,
      pages: [{ top: 0, h: 12, items: [{ file: '01_Orig_x1.png', pdf: '', pdfPage: 1,
                                          x: 1, y: 1, w: 2, h: 2, r: 0 }] }]
    };
    const old = await run(legacy);
    ok('an order with no PDF in its ZIP still builds', old.docs === 1 && old.passthrough === 0,
       'docs=' + old.docs + ' passthrough=' + old.passthrough);

    if (pageErrors.length) { console.log('\n  browser errors:\n   ' + pageErrors.join('\n   ')); failures++; }
  } finally {
    await browser.close();
    server.close();
    if (madeLink) { try { fs.unlinkSync(link); } catch (e) {} }
  }

  console.log(failures ? `\n${failures} FAILED` : '\nall passed');
  process.exit(failures ? 1 : 0);
})();
