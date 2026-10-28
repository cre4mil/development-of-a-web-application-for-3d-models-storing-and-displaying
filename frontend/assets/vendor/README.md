# Third-party front-end files

Stored in the project so the site works with no internet connection.
Files are byte-for-byte copies of the published packages; pages load them
with the same SRI `integrity` hashes the CDN links used, so a changed file
is refused by the browser.

| Folder | Source | License |
|---|---|---|
| `three@0.130.0/` | npm `three` 0.130.0 (`build/three.min.js` + the `examples/js` loaders used by `viewer.js`) | MIT |
| `bootstrap@5.3.3/` | npm `bootstrap` 5.3.3 (`dist/css/bootstrap.min.css`, `dist/js/bootstrap.bundle.min.js`) | MIT |
| `bootstrap-icons@1.11.1/` | npm `bootstrap-icons` 1.11.1 (`font/`) | MIT, see `LICENSE` |
| `chart.js@4.4.0/` | npm `chart.js` 4.4.0 (`dist/chart.umd.min.js`) | MIT |
| `qrcode-generator@1.4.4/` | npm `qrcode-generator` 1.4.4 (`qrcode.js`) | MIT |
| `fonts/` | Google Fonts: Inter (400–800) and Noto Sans Thai (400–700), woff2 | SIL OFL 1.1, see `OFL-*.txt` |

To upgrade a library, download the new version into a new `name@version/`
folder, update the path and the `integrity` hash where it is referenced
(`templates/layout.php`, `templates/pages/embed.php`, `assets/js/viewer.js`,
`assets/js/earnings.js`, `assets/js/model.js`), then delete the old folder.
