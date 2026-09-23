// Records one fixture for DocumentComposerParityTest: a form session is played
// on the real invoice form and the request it sends is cut down to its money
// fields. The request is answered with a 422, so nothing is stored.
//
// 1. Serve the app on 127.0.0.1:8310 with the Parity tax types, customers and
//    item the test makes, sign in (playwright-cli -s=parity), and set the
//    fixture's `settings` on the company.
// 2. Replace __SPEC__ with the fixture's `form` object and run
//      playwright-cli -s=parity run-code --filename capture.js --raw
// 3. Store the result as the fixture's `submitted`.
async page => {
  const spec = __SPEC__;
  const esc = s => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  await page.route('**/api/v1/invoices', route => route.request().method() === 'POST'
    ? route.fulfill({ status: 422, contentType: 'application/json', body: JSON.stringify({ message: 'parity capture' }) })
    : route.continue());
  await page.setViewportSize({ width: 1440, height: 2000 });
  await page.goto('http://127.0.0.1:8310/admin/invoices/create');
  await page.waitForTimeout(600);
  await page.getByRole('button', { name: /Customer Select a customer/ }).click();
  await page.getByRole('button', { name: spec.customer }).click();
  await page.waitForTimeout(800);
  if (spec.exchange_rate) {
    const rate = page.getByRole('textbox', { name: /Exchange Rate/i }).or(page.getByRole('spinbutton', { name: /Exchange Rate/i })).first();
    await rate.click(); await rate.press('Control+a'); await rate.pressSequentially(String(spec.exchange_rate));
  }
  if (spec.inclusive) {
    await page.getByRole('switch', { name: 'Inclusive taxes' }).click();
  }
  const rowOf = i => page.getByRole('row').filter({ has: page.getByRole('button', { name: new RegExp('^Move item ' + (i + 1) + ' of') }) }).first();
  for (const [i, line] of spec.lines.entries()) {
    if (i > 0) await page.getByRole('button', { name: 'Add New Item' }).click();
    await page.waitForTimeout(200);
    const row = rowOf(i);
    const combo = row.getByRole('combobox', { name: 'Type or click to select an item' });
    await combo.click();
    if (line.item) {
      await combo.fill(line.item);
      await page.getByRole('option', { name: line.item }).click();
      await page.waitForTimeout(400);
    } else {
      await combo.fill(line.name);
      await page.keyboard.press('Escape');
    }
    await row.getByRole('spinbutton', { name: 'Quantity' }).fill(String(line.qty));
    if (line.price) {
      const p = row.getByRole('textbox', { name: 'Price' });
      await p.click(); await p.press('Control+a'); await p.pressSequentially(line.price);
    }
    if (line.discount) {
      await row.getByRole('button', { name: /^Discount type/ }).click();
      await page.getByRole('menuitem', { name: line.discount_type === 'percentage' ? 'Percentage' : 'Fixed' }).click();
      const d = row.getByRole('textbox', { name: 'Discount' });
      await d.click(); await d.press('Control+a'); await d.pressSequentially(String(line.discount));
    }
    for (const [k, tax] of (line.taxes || []).entries()) {
      await row.getByRole('combobox', { name: 'Tax' }).nth(k).click();
      await page.getByRole('option', { name: new RegExp('^' + esc(tax) + ' - ') }).click();
      await page.waitForTimeout(300);
    }
  }
  if (spec.discount) {
    await page.getByRole('button', { name: /^Discount type/ }).last().click();
    await page.getByRole('menuitem', { name: spec.discount_type === 'percentage' ? 'Percentage' : 'Fixed' }).click();
    const d = page.getByRole('textbox', { name: 'Discount' }).last();
    await d.click(); await d.press('Control+a'); await d.pressSequentially(String(spec.discount));
  }
  for (const tax of (spec.taxes || [])) {
    await page.getByRole('button', { name: 'Add Tax' }).click();
    await page.getByRole('button', { name: new RegExp('^' + esc(tax) + ' ') }).evaluate(el => el.click());
    await page.waitForTimeout(300);
  }
  await page.waitForTimeout(800);
  const req = page.waitForRequest(r => /\/api\/v1\/invoices$/.test(r.url()) && r.method() === 'POST');
  await page.getByRole('button', { name: 'Save Invoice' }).click();
  const captured = await Promise.race([req, page.waitForTimeout(5000).then(() => null)]);
  if (!captured) {
    const errs = (await page.locator('body').ariaSnapshot()).split('\n').filter(l => /alert|error|required|invalid|must/i.test(l)).slice(0, 20).join('\n');
    return JSON.stringify({ error: 'no POST', errs });
  }
  const body = JSON.parse(captured.postData());
  const tax = t => ({ name: t.name, percent: t.percent, calculation_type: t.calculation_type, fixed_amount: t.fixed_amount, compound_tax: t.compound_tax, amount: t.amount });
  const keep = ['currency_id', 'exchange_rate', 'discount', 'discount_type', 'discount_val', 'tax_per_item', 'discount_per_item', 'tax_included', 'sub_total', 'tax', 'total'];
  const out = Object.fromEntries(keep.map(k => [k, body[k] ?? null]));
  out.taxes = (body.taxes || []).map(tax);
  out.items = body.items.map(it => ({
    name: it.name, quantity: it.quantity, price: it.price, discount: it.discount, discount_type: it.discount_type,
    discount_val: it.discount_val, tax: it.tax, total: it.total,
    taxes: (it.taxes || []).filter(t => t.tax_type_id).map(tax),
  }));
  return JSON.stringify(out);
}
