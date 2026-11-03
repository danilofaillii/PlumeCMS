const { chromium } = require('playwright'); const fs = require('fs');
(async () => {
  const b = await chromium.launch();
  for (const [base, dir] of [['http://127.0.0.1:8696', '/tmp/claude-0/-home-user/0d5dd4fc-ebef-58e5-ad4b-ed8d21c17852/scratchpad/cw-prima'], ['http://127.0.0.1:8694', '/tmp/claude-0/-home-user/0d5dd4fc-ebef-58e5-ad4b-ed8d21c17852/scratchpad/cw-dopo']]) {
    fs.mkdirSync(dir, { recursive: true });
    const p = await b.newPage({ viewport: { width: 1280, height: 900 } });
    for (const u of ['/tutti-i-widget/', '/', '/contatti/']) {
      await p.goto(base + u, { waitUntil: 'networkidle' });
      const els = await p.locator('[class*="ed-w-"]').all();
      let i = 0;
      for (const e of els) { const cls = (await e.getAttribute('class')).match(/ed-w-[a-z_0-9]+/)[0]; i++; try { await e.screenshot({ path: dir + '/' + u.replace(/\//g, '_') + String(i).padStart(3, '0') + '-' + cls + '.png', animations: 'disabled' }); } catch (x) {} }
    }
    await p.close();
  }
  await b.close();
})();
