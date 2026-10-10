# Listing artwork

Sources for the WordPress.org listing assets in `.wordpress-org/`.

| File | What |
| --- | --- |
| `icon.svg` | Plugin icon: the dashboard's shield in admin blue on navy. Copied as is to `.wordpress-org/icon.svg`. |
| `banner.svg` | Banner. The bottom left stays empty: WordPress.org writes the plugin name there. |
| `render-art.js` | Renders `icon-128x128.png`, `icon-256x256.png`, `banner-772x250.png` and `banner-1544x500.png`. |
| `screenshots.js` | Takes `screenshot-1.png` to `screenshot-4.png` (captions in `readme.txt`) from a local Playground site. |

Both scripts drive the Google Chrome installed on the machine with `puppeteer-core`, which is not a project
dependency:

```bash
npm install --no-save puppeteer-core@23
node docs/brand/render-art.js docs/brand .wordpress-org

# In another terminal: make playground (or npx @wp-playground/cli server --auto-mount --login --port=9400)
node docs/brand/screenshots.js .wordpress-org
```

Screenshots are 1280×860 CSS pixels at 2× (the Settings one is taller, down to "Online services"). Retake them
when the screens change.
