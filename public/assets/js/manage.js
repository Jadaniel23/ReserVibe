(() => {
  'use strict';
  const $ = (selector) => document.querySelector(selector);
  const message = $('#manage-message');
  let csrf = '';
  const say = (text, error = false) => { message.textContent = text; message.classList.toggle('is-error', error); };
  async function fetchData(url, options = {}) {
    const response = await fetch(url, { credentials: 'same-origin', ...options });
    const body = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(body.message || `Request failed (${response.status}).`);
    return body.data;
  }
  fetchData('/api/csrf-token.php').then((data) => { csrf = data.csrf_token; }).catch((error) => say(error.message, true));
  $('#proof-upload-form').addEventListener('submit', async (event) => {
    event.preventDefault(); const button = event.currentTarget.querySelector('button[type="submit"]'); button.disabled = true;
    try { const result = await fetchData('/api/payment/proof/submit.php', { method: 'POST', headers: { 'X-CSRF-Token': csrf }, body: new FormData(event.currentTarget) }); say(result.message); event.currentTarget.reset(); }
    catch (error) { say(error.message, true); }
    finally { button.disabled = false; }
  });
  $('#manage-reservation-form').addEventListener('submit', async (event) => {
    event.preventDefault(); const data = Object.fromEntries(new FormData(event.currentTarget).entries());
    if (!data.check_in || !data.check_out) { say('Choose both new stay dates.', true); return; }
    try { const result = await fetchData('/api/reservation/manage.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify({ ...data, action: 'reschedule' }) }); say(`Rescheduled. New estimate: ₱${Number(result.quote.total).toLocaleString('en-PH', { minimumFractionDigits: 2 })}.`); }
    catch (error) { say(error.message, true); }
  });
  $('#guest-cancel').addEventListener('click', async () => {
    const reference = $('#manage-reference').value.trim(); const email = $('#manage-email').value.trim();
    if (!reference || !email) { say('Enter the reservation reference and booking email first.', true); return; }
    if (!window.confirm('Cancel this reservation request? The demo will record the cancellation; confirm real resort policies directly.')) return;
    try { const result = await fetchData('/api/reservation/manage.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify({ action: 'cancel', reservation_reference: reference, guest_email: email }) }); say(result.message); }
    catch (error) { say(error.message, true); }
  });
})();
