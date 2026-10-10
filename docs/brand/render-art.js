// Renders docs/brand/*.svg to the PNG sizes WordPress.org needs: node render-art.js <brand dir> <out dir>
const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer-core');

const [brand, out] = process.argv.slice(2);
const jobs = [
	['icon.svg', 'icon-128x128.png', 128, 128],
	['icon.svg', 'icon-256x256.png', 256, 256],
	['banner.svg', 'banner-772x250.png', 772, 250],
	['banner.svg', 'banner-1544x500.png', 1544, 500],
];

(async () => {
	const browser = await puppeteer.launch({ executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: true });
	const page = await browser.newPage();
	for (const [src, dest, w, h] of jobs) {
		const svg = fs.readFileSync(path.join(brand, src), 'utf8').replace(/width="\d+" height="\d+"/, `width="${w}" height="${h}"`);
		await page.setViewport({ width: w, height: h, deviceScaleFactor: 1 });
		await page.setContent(`<!doctype html><html><body style="margin:0;background:#0B1622">${svg}</body></html>`);
		await page.screenshot({ path: path.join(out, dest), clip: { x: 0, y: 0, width: w, height: h } });
		console.log('wrote', dest);
	}
	await browser.close();
})();
