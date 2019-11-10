/**
 * 基于puppeteer进行截屏操作
 *
 * 使用方法：
 * $ node screenshot.js --url=要截屏的网址 --save_path=图片缓存路径 --name=图片文件名
 * @type {exports|*}
 */

const puppeteer = require('puppeteer');
const argv = require('yargs').argv;
const url = argv.url;
const save_path = argv.save_path;
const name = argv.name;

(async () => {
    try {
        const browser = await puppeteer.launch({
            headless: true,
            args: ['--no-sandbox']
        });
        const page = await browser.newPage();
        await page.setViewport({
            width: 1366,
            height: 768,
            deviceScaleFactor: 2, // 此参数越大，截图质量越高，文件也越大，一般设置为2已经很好了
        });
        await page.goto(url, {
            timeout: 66000, // 超时时间，单位为毫秒
            waitUntil: 'load'
        });
        await page.waitFor(2000); // 延迟2秒等待画图完成
        await page.screenshot({
            path: save_path + '/' + name,
            type: 'png'
        });

        await browser.close();
    } catch (e) {
        console.log(e);
        await browser.close();
    }
})();
