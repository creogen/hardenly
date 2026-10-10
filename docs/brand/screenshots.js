// Takes the WordPress.org screenshots from a local Playground site: node screenshots.js <out dir>
const path = require('path');
const puppeteer = require('puppeteer-core');

const SITE = 'http://127.0.0.1:9400';
const out = process.argv[2];
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
	const browser = await puppeteer.launch({ executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: true });
	const page = await browser.newPage();
	await page.setViewport({ width: 1280, height: 860, deviceScaleFactor: 2 });

	await page.goto(`${SITE}/wp-admin/admin.php?page=sitewarden`, { waitUntil: 'networkidle0' });
	if (await page.$('#user_login')) {
		// Playground's default test account on this local site.
		await page.type('#user_login', 'admin');
		await page.type('#user_pass', 'password');
		await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('#wp-submit')]);
	}
	await page.waitForSelector('.sitewarden button');

	const runScan = async (capture) => {
		const button = (await page.$$('.sitewarden button')).find(Boolean);
		await button.click();
		await page.waitForSelector('.sitewarden-progress', { timeout: 15000 });
		if (capture) {
			// Let the bar move a little.
			await page.waitForFunction(() => {
				const bar = document.querySelector('[role=progressbar]');
				return bar && Number(bar.getAttribute('aria-valuenow')) >= 40;
			}, { timeout: 60000, polling: 100 });
			await page.evaluate(() => window.scrollTo(0, 0));
			await page.screenshot({ path: path.join(out, 'screenshot-2.png') });
			console.log('wrote screenshot-2.png');
		}
		await page.waitForSelector('.sitewarden-progress', { hidden: true, timeout: 120000 });
		await page.waitForSelector('.sitewarden-score', { timeout: 30000 });
		await sleep(500);
	};

	// The first scan gives the second one results to show, dimmed, while it runs.
	await runScan(false);
	await runScan(true);
	await page.waitForSelector('.sitewarden-history', { timeout: 15000 });
	await page.evaluate(() => window.scrollTo(0, 0));
	await page.screenshot({ path: path.join(out, 'screenshot-1.png') });
	console.log('wrote screenshot-1.png');

	// Settings down to Online services, which has its own screenshot.
	await page.setViewport({ width: 1280, height: 2400, deviceScaleFactor: 2 });
	await page.goto(`${SITE}/wp-admin/admin.php?page=sitewarden-settings`, { waitUntil: 'networkidle0' });
	const end = await page.evaluate(() => {
		const heading = [...document.querySelectorAll('h2')].find((h) => h.textContent.trim() === 'Online services');
		return Math.round(heading.getBoundingClientRect().top - 16);
	});
	await page.screenshot({ path: path.join(out, 'screenshot-3.png'), clip: { x: 0, y: 0, width: 1280, height: end } });
	await page.setViewport({ width: 1280, height: 860, deviceScaleFactor: 2 });
	console.log('wrote screenshot-3.png');

	await page.evaluate(() => {
		const heading = [...document.querySelectorAll('h2')].find((h) => h.textContent.trim() === 'Online services');
		window.scrollTo(0, heading.getBoundingClientRect().top + window.scrollY - 52);
	});
	await sleep(300);
	await page.screenshot({ path: path.join(out, 'screenshot-4.png') });
	console.log('wrote screenshot-4.png');

	await browser.close();
})().catch((e) => {
	console.error(e);
	process.exit(1);
});
