async (page) => {
  // Substitute placeholders into an ignored private copy; never commit credentials.
  const email = __QA_EMAIL__;
  const password = __QA_PASSWORD__;
  const productId = __QA_PRODUCT_ID__;
  const base = 'http://127.0.0.1:8023';
  await page.context().clearCookies();
  await page.goto(base + '/login');
  await page.locator('[name=email]').fill(email);
  await page.locator('[name=password]').fill(password);
  await Promise.all([page.waitForURL(base + '/'), page.getByRole('button', { name: 'Đăng nhập', exact: true }).click()]);
  const measurements = [];
  for (const width of [320, 390, 768, 1440]) {
    await page.setViewportSize({ width, height: 900 });
    await page.goto(base + '/');
    if (width < 992) {
      await page.getByRole('button', { name: 'Mở menu' }).click();
      await page.locator('#shopNav.show').waitFor();
    }
    const size = await page.evaluate(() => ({ viewport: innerWidth, actual: document.documentElement.scrollWidth }));
    measurements.push({ width, ...size });
    if (size.actual !== size.viewport) throw new Error('Long account name overflow: ' + JSON.stringify(measurements));
  }
  let token = await page.locator('input[name=_token]').first().inputValue();
  for (const id of [String(productId), '000' + productId]) {
    const response = await page.request.post(base + '/gio-hang/them/' + id, {
      form: { _token: token, quantity: '3' }, maxRedirects: 0
    });
    if (response.status() !== 302) throw new Error('Cart add response: ' + response.status());
  }
  await page.goto(base + '/gio-hang');
  if (await page.locator('input[name=quantity]').count() !== 1
      || await page.locator('input[name=quantity]').inputValue() !== '3') throw new Error('Alias created multiple/excess cart lines');
  await page.goto(base + '/checkout');
  token = await page.locator('input[name=_token]').first().inputValue();
  const version = await page.locator('[name=checkout_version]').inputValue();
  const response = await page.request.post(base + '/checkout', { form: {
    _token: token, checkout_version: version, customer_name: 'HTTP acceptance', phone: '0900000000',
    address: 'Test address', payment_method: 'cod'
  }, maxRedirects: 0 });
  if (response.status() !== 302 || !response.headers().location.endsWith('/checkout/thanh-cong')) throw new Error('Checkout failed');
  await page.goto(base + '/gio-hang');
  const cancelForm = page.locator('form[action*="/don-hang/"][action$="/huy"]');
  const action = await cancelForm.getAttribute('action');
  token = await cancelForm.locator('[name=_token]').inputValue();
  const cancel = await page.request.post(action, { form: { _token: token, _method: 'PATCH' }, maxRedirects: 0 });
  if (cancel.status() !== 302) throw new Error('Cancel failed');
  return { result: 'PASS', longName: 255, measurements, aliasCart: 'one line, quantity 3', purchaseAndCancel: true };
}
