async (page) => {
  const results = [];
  for (const kind of ['nul-password', 'array-email']) {
    const context = await page.context().browser().newContext();
    try {
      const tab = await context.newPage();
      await tab.goto('http://127.0.0.1:8023/register');
      const token = await tab.locator('input[name="_token"]').inputValue();
      const url = 'http://127.0.0.1:8023/register';
      if (kind === 'nul-password') {
        const password = 'secret\u0000password';
        const response = await context.request.post(url, {
          data: { _token: token, name: 'Acceptance invalid input', email: 'nul-http@example.test', password, password_confirmation: password },
          headers: { Accept: 'application/json' }, maxRedirects: 0
        });
        results.push({ case: kind, expected: 422, actual: response.status() });
        if (response.status() !== 422) throw new Error('NUL password must be rejected by validation');
      } else {
        const response = await context.request.post(url, {
          form: { _token: token, 'email[]': 'invalid' },
          headers: { Referer: url, Accept: 'text/html' }, maxRedirects: 0
        });
        const formResponse = await tab.goto(url);
        results.push({ case: kind, submit: response.status(), expectedForm: 200, actualForm: formResponse.status() });
        if (response.status() !== 302 || formResponse.status() !== 200) throw new Error('Invalid array input broke registration form');
      }
    } finally { await context.close(); }
  }
  console.log(JSON.stringify(results));
  return results;
}
