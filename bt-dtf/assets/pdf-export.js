/**
 * BT Transfers: print-ready PDF export for gang sheet orders.
 *
 * A PDF page cannot be taller than 200 inches (14,400 points). jsPDF clamps a
 * taller page to 200 and still draws the art at full size, so the bottom of a
 * long sheet silently fell off the page. Sheets are now split into as many
 * PDFs as it takes, and a split never runs through a piece: every piece lands
 * whole on exactly one page, at its exact size.
 *
 * Pages are built from each design's own file in the order's production ZIP at
 * native resolution, NOT from the combined PNG. The combined PNG is scaled down
 * to fit browser canvas limits (a 272" sheet came out at 60 DPI), so it is only
 * used for orders that have no ZIP at all.
 *
 * Where the layout comes from, best first:
 *   layout  the customer's exact placements, saved with the order (0.7.0+)
 *   repack  older orders with a ZIP: the manifest's pieces and copy counts are
 *           packed onto 22" rows. Positions differ from the preview, sizes and
 *           counts do not
 *   sheet   no ZIP: the combined PNG is sliced at fully transparent rows, so it
 *           still never cuts through art, but it prints at whatever DPI it was
 *           saved at
 */
(function (root) {
    'use strict';

    var MAX_PAGE_IN = 200;         // PDF page height ceiling, 14,400 pt
    var MAX_ART_DPI = 600;         // above this a piece is resampled down to it
    var MAX_ART_PX  = 100000000;   // per-piece canvas budget, ~100 MP
    var EPS = 1e-6;

    function num(v, d) { var n = parseFloat(v); return isFinite(n) ? n : d; }

    /* -- Pagination ---------------------------------------------------- */

    // Last y, at or below `limit`, where a straight horizontal cut touches no
    // piece. Returns null when there is none in the lower half of the page, so
    // a few oddly staggered pieces don't produce a pile of tiny pages.
    function cleanCut(rest, top, limit, pad) {
        var run = -Infinity, best = null;
        for (var i = 0; i < rest.length; i++) {
            var p = rest[i];
            if (i > 0 && p.y >= run - EPS) {          // clear gap above p
                if (run + pad <= limit + EPS) best = run; else break;
            }
            run = Math.max(run, p.y + p.h);
        }
        if (run + pad <= limit + EPS) return run;      // everything left fits
        if (best !== null && best - top >= MAX_PAGE_IN / 2) return best;
        return null;
    }

    // Splits pieces into pages no taller than MAX_PAGE_IN. Each page keeps the
    // pieces' positions relative to each other, so a page is always a subset of
    // a layout that was already overlap-free. When no clean straight cut exists
    // (staggered columns), the page takes every piece that fits above the limit
    // and the rest start the next page: the break steps around pieces instead
    // of running through them.
    function paginate(items, pad, sheetH) {
        var rest = items.filter(function (p) { return p.w > 0 && p.h > 0; })
                        .sort(function (a, b) { return (a.y - b.y) || (a.x - b.x); });
        var pages = [];
        while (rest.length) {
            // The first page starts at the top of the sheet like the order does.
            var top   = pages.length ? Math.max(0, rest[0].y - pad) : 0;
            var limit = top + MAX_PAGE_IN;
            var cut   = cleanCut(rest, top, limit, pad);
            var take = [], keep = [];
            rest.forEach(function (p) {
                var fits = (cut !== null) ? (p.y + p.h <= cut + EPS)
                                          : (p.y + p.h + pad <= limit + EPS);
                (fits ? take : keep).push(p);
            });
            if (!take.length) { take = [rest[0]]; keep = rest.slice(1); } // piece taller than a page
            var maxB = 0;
            take.forEach(function (p) { maxB = Math.max(maxB, p.y + p.h); });
            var bottom = maxB + pad;
            if (sheetH) {
                // Padding never runs past the sheet, and nothing is ever trimmed off a piece.
                bottom = Math.max(maxB, Math.min(bottom, sheetH));
                // Last page keeps the sheet's ordered length when it fits.
                if (!keep.length) bottom = Math.max(bottom, Math.min(sheetH, limit));
            }
            var h = bottom - top;
            pages.push({ top: top, h: h, items: take, oversize: h > MAX_PAGE_IN + EPS });
            rest = keep;
        }
        return pages;
    }

    /* -- Layout sources ------------------------------------------------ */

    // Orders placed before layouts were saved: pack the manifest's pieces onto
    // rows, tallest first. Rows always leave a clean cut between them.
    function packManifest(manifest, sheetW, margin, pad) {
        var usable = sheetW - pad * 2, pieces = [];
        manifest.forEach(function (row) {
            var w = num(row && row.w, 0), h = num(row && row.h, 0);
            var qty = Math.max(1, parseInt(row && row.qty, 10) || 1);
            if (!(w > 0 && h > 0) || !row.file) return;
            if (w > usable + EPS && h <= usable + EPS) { var t = w; w = h; h = t; }
            for (var q = 0; q < qty; q++) pieces.push({ file: row.file, w: w, h: h, r: 'auto' });
        });
        pieces.sort(function (a, b) { return (b.h - a.h) || (b.w - a.w); });
        var x = pad, y = pad, rowH = 0, out = [];
        pieces.forEach(function (p) {
            if (x > pad + EPS && x + p.w > sheetW - pad + EPS) { y += rowH + margin; x = pad; rowH = 0; }
            out.push({ file: p.file, x: x, y: y, w: p.w, h: p.h, r: p.r });
            x += p.w + margin;
            rowH = Math.max(rowH, p.h);
        });
        return { items: out, sheetH: out.length ? y + rowH + pad : 0 };
    }

    // Decides where the pieces go. Returns null when the order has no ZIP.
    function planFromJob(job) {
        var manifest = Array.isArray(job.manifest) ? job.manifest : [];
        if (!job.zip || !manifest.length) return null;
        var pad = num(job.padding, 0.2), margin = num(job.margin, 0.15);
        var L = job.layout;
        if (L && Array.isArray(L.p) && L.p.length) {
            var items = [], ok = true;
            L.p.forEach(function (p) {
                var row = manifest[parseInt(p.i, 10)];
                if (!row || !row.file) { ok = false; return; }
                items.push({ file: row.file, x: num(p.x, 0), y: num(p.y, 0), w: num(p.w, 0), h: num(p.h, 0), r: p.r ? 1 : 0 });
            });
            if (ok && items.length) {
                var sheetW = num(L.w, num(job.sheetW, 22));
                return { source: 'layout', sheetW: sheetW, pieces: items.length,
                         pages: paginate(items, pad, num(L.h, 0)) };
            }
        }
        var W = num(job.sheetW, 22);
        var packed = packManifest(manifest, W, margin, pad);
        if (!packed.items.length) return null;
        return { source: 'repack', sheetW: W, pieces: packed.items.length,
                 pages: paginate(packed.items, pad, packed.sheetH) };
    }

    // Would the image fit this footprint better turned 90 degrees? Used for
    // repacked pieces, where the manifest does not record rotation. Film
    // orientation does not matter for a transfer, only the size does.
    function shouldRotate(nW, nH, wIn, hIn) {
        var a = nW / nH;
        return Math.abs(Math.log(a / (wIn / hIn))) > Math.abs(Math.log(a / (hIn / wIn))) + 0.01;
    }

    /* -- PDF assembly -------------------------------------------------- */

    // deps.jsPDF: constructor. deps.getArt(file, r, wIn, hIn) resolves to
    // { bytes: Uint8Array PNG already turned to fit the footprint, dpi }.
    function buildPdfs(plan, deps, onProgress) {
        var results = [], lowDpi = Infinity, seq = Promise.resolve();
        plan.pages.forEach(function (page, k) {
            seq = seq.then(function () {
                if (onProgress) onProgress(k, plan.pages.length);
                var pdf = new deps.jsPDF({
                    orientation: page.h >= plan.sheetW ? 'portrait' : 'landscape',
                    unit: 'in', format: [plan.sheetW, page.h], compress: true
                });
                var groups = {}, keys = [];
                page.items.forEach(function (it) {
                    var key = it.file + '|' + it.r + '|' + it.w.toFixed(3) + 'x' + it.h.toFixed(3);
                    if (!groups[key]) { groups[key] = []; keys.push(key); }
                    groups[key].push(it);
                });
                var gseq = Promise.resolve();
                keys.forEach(function (key, gi) {
                    gseq = gseq.then(function () {
                        var first = groups[key][0];
                        return deps.getArt(first.file, first.r, first.w, first.h).then(function (art) {
                            if (art.dpi < lowDpi) lowDpi = art.dpi;
                            var alias = 'p' + k + 'a' + gi;   // one embedded image per design per page
                            groups[key].forEach(function (it) {
                                pdf.addImage(art.bytes, 'PNG', it.x, it.y - page.top, it.w, it.h, alias, 'FAST');
                            });
                        });
                    });
                });
                return gseq.then(function () { results.push({ pdf: pdf, page: page }); });
            });
        });
        return seq.then(function () { return { docs: results, lowDpi: lowDpi }; });
    }

    function fileNames(base, count) {
        base = base || 'gang-sheet';
        var out = [];
        for (var i = 1; i <= count; i++) out.push(count === 1 ? base + '.pdf' : base + '-' + i + 'of' + count + '.pdf');
        return out;
    }

    /* -- Browser side -------------------------------------------------- */

    function canvasToBytes(c) {
        return new Promise(function (resolve, reject) {
            c.toBlob(function (b) {
                if (!b) { reject(new Error('The browser could not export a canvas.')); return; }
                b.arrayBuffer().then(function (ab) { resolve(new Uint8Array(ab)); }, reject);
            }, 'image/png');
        });
    }

    function decodeBlob(blob) {
        return new Promise(function (resolve, reject) {
            var url = URL.createObjectURL(blob), img = new Image();
            img.onload  = function () { URL.revokeObjectURL(url); resolve(img); };
            img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('Could not read an image in the ZIP.')); };
            img.src = url;
        });
    }

    function loadImage(url) {
        return new Promise(function (resolve, reject) {
            var img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload  = function () { resolve(img); };
            img.onerror = function () { reject(new Error('Could not load the sheet image. It may have been moved or deleted.')); };
            img.src = url;
        });
    }

    // Every piece goes through a canvas so odd uploads (16-bit, palette,
    // interlaced, JPEG) all reach jsPDF as plain 8-bit RGBA PNG.
    function makeArtLoader(zip) {
        var blobs = {};
        return function getArt(file, r, wIn, hIn) {
            if (!blobs[file]) {
                var f = zip.file(file);
                if (!f) return Promise.reject(new Error('Missing from the production ZIP: ' + file));
                blobs[file] = f.async('blob');
            }
            return blobs[file].then(decodeBlob).then(function (img) {
                var nW = img.naturalWidth, nH = img.naturalHeight;
                var rot = (r === 'auto') ? shouldRotate(nW, nH, wIn, hIn) : !!r;
                var artW = rot ? hIn : wIn, artH = rot ? wIn : hIn;   // art size before turning
                var s = Math.min(1, artW * MAX_ART_DPI / nW, artH * MAX_ART_DPI / nH, Math.sqrt(MAX_ART_PX / (nW * nH)));
                var sw = Math.max(1, Math.round(nW * s)), sh = Math.max(1, Math.round(nH * s));
                var c = document.createElement('canvas');
                c.width  = rot ? sh : sw;
                c.height = rot ? sw : sh;
                var ctx = c.getContext('2d');
                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = 'high';
                if (rot) { ctx.translate(c.width, 0); ctx.rotate(Math.PI / 2); } // clockwise, same as the builder
                ctx.drawImage(img, 0, 0, sw, sh);
                var dpi = Math.min(c.width / wIn, c.height / hIn);
                return canvasToBytes(c).then(function (bytes) {
                    c.width = c.height = 0;
                    return { bytes: bytes, dpi: dpi };
                });
            });
        };
    }

    // No ZIP: find the fully transparent rows in the combined PNG and treat
    // each run of inked rows as one piece, so the same pagination applies.
    function scanInkBands(img, sheetW) {
        var W = img.naturalWidth, H = img.naturalHeight, ppi = W / sheetW, STRIP = 256;
        var c = document.createElement('canvas');
        c.width = W; c.height = STRIP;
        var ctx = c.getContext('2d', { willReadFrequently: true });
        var bands = [], start = -1;
        for (var y0 = 0; y0 < H; y0 += STRIP) {
            var rows = Math.min(STRIP, H - y0);
            ctx.clearRect(0, 0, W, STRIP);
            ctx.drawImage(img, 0, -y0);
            var d = ctx.getImageData(0, 0, W, rows).data;
            for (var r = 0; r < rows; r++) {
                var ink = false;
                for (var i = r * W * 4 + 3, end = (r + 1) * W * 4; i < end; i += 4) { if (d[i]) { ink = true; break; } }
                var y = y0 + r;
                if (ink && start < 0) start = y;
                else if (!ink && start >= 0) { bands.push({ x: 0, y: start / ppi, w: sheetW, h: (y - start) / ppi }); start = -1; }
            }
        }
        if (start >= 0) bands.push({ x: 0, y: start / ppi, w: sheetW, h: (H - start) / ppi });
        c.width = c.height = 0;
        return { bands: bands, sheetH: H / ppi, ppi: ppi };
    }

    function buildFromSheet(job, jsPDF) {
        var W = num(job.sheetW, 22);
        return loadImage(job.sheet).then(function (img) {
            var scan = scanInkBands(img, W), ppi = scan.ppi;
            var pages = scan.bands.length ? paginate(scan.bands, num(job.padding, 0.2), scan.sheetH)
                                          : [{ top: 0, h: scan.sheetH, items: [] }];
            var docs = [], seq = Promise.resolve();
            pages.forEach(function (page) {
                seq = seq.then(function () {
                    var y0 = Math.round(page.top * ppi);
                    var rows = Math.min(img.naturalHeight - y0, Math.round(page.h * ppi));
                    var c = document.createElement('canvas');
                    c.width = img.naturalWidth; c.height = rows;
                    c.getContext('2d').drawImage(img, 0, -y0);
                    return canvasToBytes(c).then(function (bytes) {
                        c.width = c.height = 0;
                        var hIn = rows / ppi;
                        var pdf = new jsPDF({ orientation: hIn >= W ? 'portrait' : 'landscape', unit: 'in', format: [W, hIn], compress: true });
                        pdf.addImage(bytes, 'PNG', 0, 0, W, hIn, undefined, 'FAST');
                        docs.push({ pdf: pdf, page: { top: page.top, h: hIn, items: page.items, oversize: hIn > MAX_PAGE_IN + EPS } });
                    });
                });
            });
            return seq.then(function () { return { docs: docs, lowDpi: ppi }; });
        });
    }

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
        });
    }

    function run(job, ui) {
        function status(html) { if (ui.status) ui.status.innerHTML = html; }
        var jsPDF = root.jspdf && root.jspdf.jsPDF;
        if (!jsPDF) { status('&#x274C; The PDF library did not load. Check the connection and reload this page.'); return; }

        var plan = planFromJob(job), note = '', fromSheet = false;
        var work;
        if (plan) {
            if (!root.JSZip) { status('&#x274C; The ZIP library did not load. Check the connection and reload this page.'); return; }
            status('&#x23F3; Downloading the production files&hellip;');
            work = fetch(job.zip, { credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) throw new Error('Could not download the production ZIP (HTTP ' + r.status + ').'); return r.arrayBuffer(); })
                .then(function (buf) { return root.JSZip.loadAsync(buf); })
                .then(function (zip) {
                    if (plan.source === 'repack') {
                        note = 'This order was placed before layouts were saved, so its pieces were re-laid onto rows. '
                             + 'Every piece and copy count is the same as the order, but the arrangement differs from the customer preview.';
                    }
                    return buildPdfs(plan, { jsPDF: jsPDF, getArt: makeArtLoader(zip) }, function (k, n) {
                        status('&#x1F4C4; Building PDF ' + (k + 1) + ' of ' + n + '&hellip;');
                    });
                })
                .catch(function (err) {
                    if (!job.sheet) throw err;
                    note = 'Built from the combined sheet image because the production files failed (' + esc(err.message) + '). '
                         + 'That image may be well under 300 DPI on a long sheet.';
                    status('&#x23F3; Falling back to the combined sheet image&hellip;');
                    fromSheet = true;
                    return buildFromSheet(job, jsPDF);
                });
        } else if (job.sheet) {
            status('&#x23F3; Loading the sheet image&hellip;');
            note = 'This order has no production ZIP, so the PDF comes from the combined sheet image at whatever resolution it was saved.';
            fromSheet = true;
            work = buildFromSheet(job, jsPDF);
        } else {
            status('&#x274C; This order has no production files.');
            return;
        }

        work.then(function (res) {
            var names = fileNames(job.name, res.docs.length), html = '', links = [];
            res.docs.forEach(function (d, i) {
                var blob = d.pdf.output('blob'), url = URL.createObjectURL(blob);
                links.push({ url: url, name: names[i] });
                html += '<li style="margin:6px 0"><a href="' + url + '" download="' + esc(names[i]) + '" style="font-weight:bold">'
                      + '&#x1F4C4; ' + esc(names[i]) + '</a> <span style="color:#666">22 &times; ' + d.page.h.toFixed(2) + ' in'
                      + (fromSheet ? '' : ', ' + d.page.items.length + ' pieces') + '</span>'
                      + (d.page.oversize ? ' <strong style="color:#c0392b">One piece is taller than a PDF page allows, so this page is cut off. Print that piece from the ZIP.</strong>' : '')
                      + '</li>';
            });
            if (ui.list) ui.list.innerHTML = html;
            var msg = '&#x2713; ' + (res.docs.length === 1 ? 'PDF ready.' : res.docs.length + ' PDFs ready. The sheet was longer than one PDF page allows, so it was split between pieces.');
            if (isFinite(res.lowDpi)) msg += ' Lowest art resolution: <strong>' + Math.round(res.lowDpi) + ' DPI</strong>.';
            if (note) msg += '<br><span style="color:#8a6100">' + note + '</span>';
            msg += '<br><span style="color:#666;font-size:13px">If your browser asks to allow multiple downloads, allow it, or click each file below.</span>';
            status(msg);
            links.forEach(function (l, i) {
                setTimeout(function () {
                    var a = document.createElement('a');
                    a.href = l.url; a.download = l.name;
                    document.body.appendChild(a); a.click(); a.remove();
                }, i * 700);
            });
        }).catch(function (err) {
            status('&#x274C; ' + esc(err && err.message ? err.message : err));
        });
    }

    var api = { MAX_PAGE_IN: MAX_PAGE_IN, paginate: paginate, packManifest: packManifest,
                planFromJob: planFromJob, shouldRotate: shouldRotate, buildPdfs: buildPdfs,
                fileNames: fileNames, run: run };
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
    else root.BTDTF_PDF = api;
})(typeof window !== 'undefined' ? window : this);
