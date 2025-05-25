const puppeteer = require('puppeteer');

async function scrapeGoogleMapsReviews(url, maxReviews = 10) {
  const browser = await puppeteer.launch({ headless: true });
  const page = await browser.newPage();

  await page.goto(url, { waitUntil: 'networkidle2', timeout: 60000 });

  // Tunggu tombol review muncul
  await page.waitForSelector('button[jsaction*="pane.reviewChart.moreReviews"]', { timeout: 15000 });
  await page.click('button[jsaction*="pane.reviewChart.moreReviews"]');

  // Tunggu panel review muncul
  await page.waitForSelector('.jftiEf', { timeout: 15000 });

  // Scroll untuk load lebih banyak review
  let lastHeight = await page.evaluate('document.querySelectorAll(".jftiEf").length');
  while (true) {
    await page.evaluate('document.querySelector(".m6QErb.DxyBCb.kA9KIf.dS8AEf").scrollBy(0, 1000)');
    await page.waitForTimeout(1000);
    let newHeight = await page.evaluate('document.querySelectorAll(".jftiEf").length');
    if (newHeight >= maxReviews || newHeight === lastHeight) break;
    lastHeight = newHeight;
  }

  // Ambil review
  const reviews = await page.evaluate((maxReviews) => {
    const reviewNodes = Array.from(document.querySelectorAll('.jftiEf'));
    return reviewNodes.slice(0, maxReviews).map(node => {
      const author = node.querySelector('.d4r55')?.innerText || '';
      const rating = node.querySelector('.kvMYJc')?.getAttribute('aria-label')?.replace(/[^0-9.]/g, '') || '';
      const date = node.querySelector('.rsqaWe')?.innerText || '';
      const text = node.querySelector('.wiI7pd')?.innerText || '';
      const profile_photo = node.querySelector('.NBa7we')?.src || '';
      return { author, rating, date, text, profile_photo };
    });
  }, maxReviews);

  await browser.close();
  return reviews;
}

// Contoh penggunaan:
const url = 'https://www.google.com/maps/place/Justus+Steak+House+Dago/@-6.9013135,107.6117378,19z/data=!4m6!3m5!1s0x2e68e7a9a38fc7dd:0x23e6bc3656efabe7!8m2!3d-6.9013135!4d107.6117378!16s%2Fg%2F11fj7_v5ty';
scrapeGoogleMapsReviews(url, 10).then(reviews => {
  console.log(JSON.stringify(reviews, null, 2));
}).catch(console.error); 