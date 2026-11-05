# bt-dtf tests

    cd tests && npm i && node pdf-passthrough.test.js

`pdf-passthrough.test.js` is the one that matters: it proves a customer's
uploaded PDF reaches the production PDF **as their file**, not as a picture of
it, and that every piece lands exactly where the layout says.

It drives the real `assets/pdf-export.js` in a real headless browser against a
real ZIP. It needs `playwright`, `pdf-lib`, `jszip` and `pdfjs-dist`, and skips
cleanly with a message if any of them is missing, so it never fails a machine
that simply has not installed them.

What it would catch:

* the exporter falling back to the preview PNG (the test plants a deliberately
  1x1 PNG beside the PDF - if that ever reaches the output, resolution is gone)
* art being rasterized on its way into the PDF
* a wrong rotation anchor, which shifts a turned piece by its own width or
  height and is invisible until something prints in the wrong place
