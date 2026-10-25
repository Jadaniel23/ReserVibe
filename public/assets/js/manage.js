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
    if (!data.check_in || !data.check_out || !data.reason.trim()) { say('Choose both dates and provide a reason for the request.', true); return; }
    try { const result = await fetchData('/api/reservation/manage.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify({ ...data, action: 'request_reschedule' }) }); say(result.message); }
    catch (error) { say(error.message, true); }
  });
  $('#guest-cancel').addEventListener('click', async () => {
    const reference = $('#manage-reference').value.trim(); const email = $('#manage-email').value.trim();
    if (!reference || !email) { say('Enter the reservation reference and booking email first.', true); return; }
    const reason = $('#manage-reason').value.trim();
    if (!reason) { say('Enter a reason for the cancellation request.', true); return; }
    if (!window.confirm('Send this cancellation request to resort staff for review?')) return;
    try { const result = await fetchData('/api/reservation/manage.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify({ action: 'request_cancel', reservation_reference: reference, guest_email: email, reason }) }); say(result.message); }
    catch (error) { say(error.message, true); }
  });
  $('#check-change-status').addEventListener('click', async () => {
    const reference = $('#manage-reference').value.trim(); const email = $('#manage-email').value.trim();
    if (!reference || !email) { say('Enter the reservation reference and booking email first.', true); return; }
    try {
      const data = await fetchData('/api/reservation/change-request/status.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify({ reference, email }) });
      const status = $('#change-request-status');
      status.textContent = data.requests.length ? data.requests.map((item) => {
        let result = `${item.action}: ${item.status}`;
        if (item.status === 'approved' && item.action === 'reschedule') result += ` — updated dates ${item.current_check_in} to ${item.current_check_out}; balance ${Number(item.balance_due || 0).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' })}`;
        if (item.status === 'approved' && item.action === 'cancel') result += ` — reservation ${item.reservation_status}${item.cancellation_policy_result === 'deposit_non_refundable' ? '; deposit policy applies' : ''}`;
        if (item.review_note) result += ' — ' + item.review_note;
        return result;
      }).join(' · ') : 'No change requests found.';
      say('Request status loaded.');
    } catch (error) { say(error.message, true); }
  });
})();
