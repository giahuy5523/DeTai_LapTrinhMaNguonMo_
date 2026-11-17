async (page) => {
  const email = __QA_EMAIL__;
  const password = __QA_PASSWORD__;
  const productId = __QA_PRODUCT_ID__;
  const base = 'http://127.0.0.1:8023';
  await page.context().clearCookies();
  await page.goto(base + '/login');
  await page.locator('[name=email]').fill(email);
  await page.locator('[name=password]').fill(password);
  await page.locator('[name=remember]').check();
  await Promise.all([page.waitForURL(base + '/'), page.getByRole('button', { name: 'Đăng nhập', exact: true }).click()]);
  const oldRemember = (await page.context().cookies()).find(cookie => cookie.name.startsWith('remember_'));
  if (!oldRemember) throw new Error('Remember cookie missing');
  let token = await page.locator('[name=_token]').first().inputValue();
  await page.request.post(base + '/gio-hang/them/' + productId, { form: { _token: token, quantity: '3' }, maxRedirects: 0 });
  await page.goto(base + '/checkout');
  const first = await page.locator('[name=checkout_version]').inputValue();
  await page.goto(base + '/checkout');
  const second = await page.locator('[name=checkout_version]').inputValue();
  token = await page.locator('[name=_token]').first().inputValue();
  if (first === second) throw new Error('Expected latest-confirmation policy');
  const submit = version => page.request.post(base + '/checkout', { data: {
    _token: token, checkout_version: version, customer_name: 'Lifecycle HTTP', phone: '0900000000',
    address: 'Test address', payment_method: 'cod'
  }, headers: { Accept: 'application/json' }, maxRedirects: 0 });
  const stale = await submit(first);
  if (stale.status() !== 422 || !(await stale.json()).errors?.cart) throw new Error('Stale tab was not rejected');
  const current = await submit(second);
  if (current.status() !== 302 || !current.headers().location.endsWith('/checkout/thanh-cong')) throw new Error('Latest tab failed');
  await page.goto(base + '/gio-hang');
  const cancelForm = page.locator('form[action*="/don-hang/"][action$="/huy"]');
  const cancel = await page.request.post(await cancelForm.getAttribute('action'), { form: {
    _token: await cancelForm.locator('[name=_token]').inputValue(), _method: 'PATCH'
  }, maxRedirects: 0 });
  if (cancel.status() !== 302) throw new Error('Cancellation failed');
  const logoutForm = page.locator('form[action$="/logout"]');
  const logout = await page.request.post(base + '/logout', { form: {
    _token: await logoutForm.locator('[name=_token]').inputValue()
  }, maxRedirects: 0 });
  if (logout.status() !== 302) throw new Error('Logout failed');
  const replayContext = await page.context().browser().newContext();
  try {
    await replayContext.addCookies([oldRemember]);
    const replay = await replayContext.newPage();
    await replay.goto(base + '/gio-hang');
    if (!replay.url().endsWith('/login')) throw new Error('Old remember cookie still authenticates after logout');
  } finally { await replayContext.close(); }
  return { result: 'PASS', staleTab: 422, currentTab: 302, cancelled: true, loggedOut: true,
    oldRememberCookieRejected: true };
}
