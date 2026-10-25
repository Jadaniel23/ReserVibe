(() => {
  'use strict';
  const $ = (selector) => document.querySelector(selector);
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  let token = '';
  let reservations = [];
  let activeReservation = null;
  const role = document.body.dataset.staffRole || 'staff';
  const say = (message, error = false) => {
    const node = $('#staff-message');
    if (!node) return;
    node.textContent = message;
    node.classList.toggle('is-error', error);
  };
  async function request(url, options = {}) {
    const response = await fetch(url, { credentials: 'same-origin', ...options });
    const body = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(body.message || `Request failed (${response.status}).`);
    return body.data;
  }
  async function post(url, data) {
    return request(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token }, body: JSON.stringify(data) });
  }
  const money = (value) => '₱' + Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  function date(value) { return value ? esc(value) : '—'; }
  function calendarFailure(data) {
    return data?.calendar_sync?.status === 'failed' ? data.calendar_sync.message : '';
  }

  async function loadSummary() {
    const data = await request('/api/admin/summary.php');
    const cards = [
      ['Active reservations', data.active_reservations], ['Today arrivals', data.arrivals_today],
      ['Today departures', data.departures_today], ['Upcoming arrivals', data.upcoming_arrivals],
      ['Upcoming departures', data.upcoming_departures], ['Pending proofs', data.pending_proofs],
      ['Recorded payments', money(data.total_recorded)], ['Outstanding balance', money(data.total_outstanding)],
    ];
    $('#staff-kpis').innerHTML = cards.map(([label, value]) => `<article class="staff-kpi"><span>${esc(label)}</span><strong>${esc(value)}</strong></article>`).join('');
  }

  function renderReservations() {
    const tbody = $('#reservation-list');
    const query = ($('#reservation-search')?.value || '').trim().toLowerCase();
    const filtered = reservations.filter((r) => `${r.reference} ${r.guest_name} ${r.guest_email} ${r.accommodation_name}`.toLowerCase().includes(query));
    tbody.innerHTML = filtered.map((r) => `<tr>
      <td>${date(r.check_in)} → ${date(r.check_out)}<br><small>${esc(r.reference)}</small></td>
      <td>${esc(r.guest_name)}<br><small>${esc(r.guest_phone || '')}</small></td>
      <td>${esc(r.accommodation_name)}</td><td>${esc(String(r.status || '').replaceAll('_', ' '))}</td>
      <td>${money(r.amount_paid)} paid<br><small>${money(r.balance_due)} due</small></td>
      <td>${esc(r.guest_status || 'reserved')}<br>${r.guest_status === 'arrived' ? `<button class="btn btn--ghost btn--sm guest-status" data-id="${Number(r.id)}" data-status="left">Mark left</button>` : (r.guest_status === 'reserved' ? `<button class="btn btn--ghost btn--sm guest-status" data-id="${Number(r.id)}" data-status="arrived">Mark arrived</button>` : '')}</td>
    </tr>`).join('') || '<tr><td colspan="6">No reservations found.</td></tr>';
    const select = $('#payment-reservation');
    if (select) select.innerHTML = '<option value="">Choose reservation</option>' + reservations.filter((r) => r.status !== 'cancelled' && Number(r.balance_due) > 0).map((r) => `<option value="${Number(r.id)}">${esc(r.reference)} · ${esc(r.guest_name)} · ${money(r.balance_due)} due</option>`).join('');
    tbody.querySelectorAll('.guest-status').forEach((button) => button.addEventListener('click', async () => {
      try { await post('/api/reservation/guest-status.php', { reservation_id: Number(button.dataset.id), guest_status: button.dataset.status }); await refresh(); say('Guest status updated.'); }
      catch (error) { say(error.message, true); }
    }));
  }
  async function loadReservations() {
    const data = await request('/api/reservation/list.php');
    reservations = data.reservations || [];
    renderReservations();
  }

  async function loadProofs() {
    const node = $('#proof-list');
    const data = await request('/api/payment/proof/list.php?status=pending');
    const proofs = data.proofs || [];
    node.innerHTML = proofs.map((p) => {
      const reservation = reservations.find((item) => item.reference === p.reservation_reference);
      return `<article class="staff-proof"><img class="staff-proof__image" src="/api/payment/proof/file.php?id=${Number(p.id)}" alt="Receipt for ${esc(p.reservation_reference)}"><div><strong>${esc(p.reservation_reference)}</strong><p>${esc(reservation?.guest_name || '')} · ${esc(reservation?.accommodation_name || '')}</p><p>${money(p.amount)} · ${esc(p.original_name || 'Uploaded receipt')}</p><button class="btn btn--primary btn--sm proof-decision" data-id="${Number(p.id)}" data-decision="approve">Approve proof</button> <button class="btn btn--ghost btn--sm proof-decision" data-id="${Number(p.id)}" data-decision="reject">Reject</button></div></article>`;
    }).join('') || '<p>No payment proofs waiting for review.</p>';
    node.querySelectorAll('.proof-decision').forEach((button) => button.addEventListener('click', async () => {
      const decision = button.dataset.decision;
      const note = decision === 'reject' ? (window.prompt('Reason for rejecting this receipt?') || '') : '';
      try { const data = await post('/api/payment/proof/review.php', { proof_id: Number(button.dataset.id), decision, note }); await refresh(); say(calendarFailure(data) || `Proof ${decision === 'approve' ? 'approved' : 'rejected'}.`); }
      catch (error) { say(error.message, true); }
    }));
  }

  async function loadLedger() {
    const data = await request('/api/payment/ledger/list.php');
    $('#ledger-list').innerHTML = (data.payments || []).map((p) => `<tr><td>${date((p.created_at || '').slice(0, 10))}</td><td>${esc(p.reservation_reference)}</td><td>${money(p.amount)}</td><td>${esc(p.method)}</td><td>${esc(p.source)}</td><td>${esc(p.recorded_by)}</td></tr>`).join('') || '<tr><td colspan="6">No payments have been recorded.</td></tr>';
  }

  async function loadRequests() {
    const node = $('#change-request-list');
    const data = await request('/api/reservation/change-request/list.php?status=pending');
    const items = data.requests || [];
    node.innerHTML = items.map((r) => `<article class="staff-request"><div><strong>${esc(r.reservation_reference)} · ${esc(r.action)}</strong><p>${esc(r.guest_name)} · ${esc(r.accommodation_name)}</p><p>Current: ${date(r.current_check_in)} → ${date(r.current_check_out)}</p>${r.action === 'reschedule' ? `<p>Requested: ${date(r.requested_check_in)} → ${date(r.requested_check_out)}</p>` : ''}<p><strong>Reason:</strong> ${esc(r.reason)}</p><small>Submitted ${date((r.created_at || '').slice(0, 10))}</small></div><div><button class="btn btn--primary btn--sm request-review" data-id="${Number(r.id)}" data-decision="approve">Approve</button> <button class="btn btn--ghost btn--sm request-review" data-id="${Number(r.id)}" data-decision="reject">Decline</button></div></article>`).join('') || '<p>No guest change requests are waiting for review.</p>';
    node.querySelectorAll('.request-review').forEach((button) => button.addEventListener('click', async () => {
      const decision = button.dataset.decision;
      const note = decision === 'reject' ? (window.prompt('Optional reason for declining?') || '') : '';
      try { const data = await post('/api/reservation/change-request/review.php', { request_id: Number(button.dataset.id), decision, note }); await refresh(); say(calendarFailure(data) || (decision === 'approve' ? 'Request approved and reservation updated.' : 'Request declined.')); }
      catch (error) { say(error.message, true); }
    }));
  }

  async function loadCatalog() {
    if (role !== 'admin') return;
    const data = await request('/api/admin/catalog/list.php');
    const tbody = $('#catalog-admin-list');
    tbody.innerHTML = (data.accommodations || []).map((room) => `<tr><td>${esc(room.name)}</td><td>${esc(room.type)}</td><td>${Number(room.capacity)}</td><td>${money(room.rate)}</td><td>${room.active ? 'Visible' : 'Hidden'}</td><td><button class="btn btn--ghost btn--sm edit-package" data-id="${Number(room.id)}">Edit</button></td></tr>`).join('');
    tbody.querySelectorAll('.edit-package').forEach((button) => button.addEventListener('click', () => {
      const room = data.accommodations.find((item) => Number(item.id) === Number(button.dataset.id));
      const form = $('#catalog-admin-form');
      ['id', 'name', 'type', 'capacity', 'rate', 'description'].forEach((key) => { const input = form.elements.namedItem(key); if (input) input.value = room[key] ?? ''; });
      form.elements.namedItem('active').checked = Boolean(room.active);
      form.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }));
  }

  async function loadWalkInOptions() {
    const data = await request('/api/catalog/list.php');
    const select = $('#walkin-package');
    select.innerHTML = (data.accommodations || []).map((room) => `<option value="${Number(room.id)}">${esc(room.name)} · ${money(room.rate)}</option>`).join('');
    const addons = $('#walkin-addons');
    addons.innerHTML = (data.addons || []).map((item) => `<label class="staff-addon"><input type="checkbox" name="walkin_addon" value="${esc(item.code)}"> ${esc(item.label)} (+${money(item.rate)})</label>`).join('');
  }

  async function loadCalendar() {
    const host = $('#staff-calendar');
    if (!host || typeof FullCalendar === 'undefined') return;
    const calendar = new FullCalendar.Calendar(host, {
      initialView: 'dayGridMonth',
      events: '/api/availability/calendar.php',
      eventClick: async (info) => {
        if (info.event.extendedProps.kind !== 'reservation') return;
        try {
          const data = await request('/api/reservation/list.php');
          const reservation = (data.reservations || []).find((item) => item.reference === info.event.extendedProps.reference);
          if (!reservation) throw new Error('Reservation details are no longer available.');
          activeReservation = reservation;
          $('#reservation-modal-title').textContent = reservation.reference;
          $('#reservation-modal-details').innerHTML = `<p><strong>Guest:</strong> ${esc(reservation.guest_name)}</p><p><strong>Contact:</strong> ${esc(reservation.guest_email)} · ${esc(reservation.guest_phone)}</p><p><strong>Package:</strong> ${esc(reservation.accommodation_name)}</p><p><strong>Dates:</strong> ${date(reservation.check_in)} → ${date(reservation.check_out)}</p><p><strong>Status:</strong> ${esc(reservation.status)}</p><p><strong>Payment:</strong> ${money(reservation.amount_paid)} paid · ${money(reservation.balance_due)} due</p>`;
          $('#staff-reschedule-in').value = reservation.check_in;
          $('#staff-reschedule-out').value = reservation.check_out;
          $('#staff-reschedule-form').classList.add('hidden');
          $('#reservation-modal').style.display = 'flex';
          $('#reservation-modal').setAttribute('aria-hidden', 'false');
          $('#staff-cancel-reservation').hidden = reservation.guest_status !== 'reserved' || reservation.status === 'cancelled';
          $('#staff-edit-reservation').hidden = reservation.guest_status !== 'reserved' || reservation.status === 'cancelled';
        } catch (error) { say(error.message, true); }
      },
    });
    calendar.render();
    window.staffCalendar = calendar;
  }

  async function loadQrCodes() {
    const data = await request('/api/admin/payment-qr/list.php');
    const select = $('#payment-qr-method');
    select.innerHTML = (data.codes || []).map((item) => `<option value="${esc(item.method)}">${esc(item.label)}</option>`).join('');
    $('#payment-qr-list').innerHTML = (data.codes || []).map((item) => `<span class="staff-qr-status">${esc(item.label)}: ${item.configured ? 'QR uploaded' : 'No QR uploaded'}${item.configured ? ` <img class="staff-qr-preview" src="${esc(item.url)}" alt="${esc(item.label)} payment QR"><button type="button" class="btn btn--ghost btn--sm remove-qr" data-method="${esc(item.method)}">Remove</button>` : ''}</span>`).join('') || '<p>No proof-based payment methods configured.</p>';
    document.querySelectorAll('.remove-qr').forEach((button) => button.addEventListener('click', async () => {
      if (!window.confirm('Remove this payment QR image?')) return;
      try { await post('/api/admin/payment-qr/save.php', { method: button.dataset.method, action: 'remove' }); await loadQrCodes(); say('Payment QR removed.'); }
      catch (error) { say(error.message, true); }
    }));
  }

  async function loadGoogleStatus() {
    if (role !== 'admin') return;
    const status = await request('/api/calendar/google/status.php');
    $('#calendar-status').textContent = !status.configured ? 'Add Google OAuth credentials to .env to enable connection.' : (status.connected ? 'Connected. Confirmed reservations sync automatically after confirmations and staff changes; use Sync reservations to retry.' : 'Credentials configured; connect the owner account to enable automatic sync.');
  }

  async function refresh() {
    const reservationsResult = await Promise.allSettled([loadReservations()]);
    const results = await Promise.allSettled([loadSummary(), loadProofs(), loadLedger(), loadRequests(), loadCatalog(), loadWalkInOptions(), loadQrCodes(), loadGoogleStatus()]);
    results.unshift(...reservationsResult);
    const failed = results.find((result) => result.status === 'rejected');
    if (failed) say(failed.reason.message || 'Some desk information could not be loaded.', true);
    if (window.staffCalendar) window.staffCalendar.refetchEvents();
  }

  document.addEventListener('DOMContentLoaded', async () => {
    try { token = (await request('/api/csrf-token.php')).csrf_token; }
    catch (error) { say(error.message, true); return; }
    await Promise.allSettled([refresh(), loadCalendar()]);
    $('#refresh-reservations')?.addEventListener('click', refresh);
    $('#refresh-proofs')?.addEventListener('click', refresh);
    $('#refresh-change-requests')?.addEventListener('click', refresh);
    $('#reservation-search')?.addEventListener('input', renderReservations);
    $('#reservation-modal-close')?.addEventListener('click', () => { $('#reservation-modal').style.display = 'none'; });
    $('#staff-edit-reservation')?.addEventListener('click', () => $('#staff-reschedule-form').classList.toggle('hidden'));
    $('#staff-cancel-reservation')?.addEventListener('click', async () => {
      if (!activeReservation || !window.confirm(`Cancel ${activeReservation.reference}?`)) return;
      try { const data = await post('/api/reservation/manage.php', { action: 'cancel', reservation_reference: activeReservation.reference }); $('#reservation-modal').style.display = 'none'; await refresh(); say(calendarFailure(data) || 'Reservation cancelled.'); }
      catch (error) { say(error.message, true); }
    });
    $('#staff-reschedule-form')?.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!activeReservation) return;
      try {
        const data = await post('/api/reservation/manage.php', { action: 'reschedule', reservation_reference: activeReservation.reference, check_in: $('#staff-reschedule-in').value, check_out: $('#staff-reschedule-out').value });
        $('#reservation-modal').style.display = 'none'; await refresh(); say(calendarFailure(data) || 'Reservation dates updated.');
      } catch (error) { say(error.message, true); }
    });
    $('#staff-logout')?.addEventListener('click', async () => {
      try { await post('/api/auth/logout.php', {}); window.location.assign('?action=staff-login'); }
      catch (error) { say(error.message, true); }
    });
    $('#walk-in-form')?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const data = Object.fromEntries(new FormData(event.currentTarget).entries());
      data.guests = Number(data.guests); data.accommodation_id = Number(data.accommodation_id);
      data.addons = Array.from(event.currentTarget.querySelectorAll('[name="walkin_addon"]:checked')).map((input) => input.value);
      try { const result = await post('/api/reservation/walk-in.php', data); event.currentTarget.reset(); await refresh(); say(calendarFailure(result) || 'Walk-in reservation recorded.'); }
      catch (error) { say(error.message, true); }
    });
    $('#payment-form')?.addEventListener('submit', async (event) => {
      event.preventDefault(); const data = Object.fromEntries(new FormData(event.currentTarget).entries());
      data.reservation_id = Number(data.reservation_id); data.amount = Number(data.amount);
      try { const result = await post('/api/payment/ledger/record.php', data); event.currentTarget.reset(); await refresh(); say(calendarFailure(result) || 'Payment recorded.'); }
      catch (error) { say(error.message, true); }
    });
    $('#catalog-admin-form')?.addEventListener('submit', async (event) => {
      event.preventDefault(); const form = event.currentTarget; const data = Object.fromEntries(new FormData(form).entries());
      data.active = form.elements.namedItem('active').checked;
      try { await post('/api/admin/catalog/save.php', data); form.reset(); form.elements.namedItem('id').value = ''; await loadCatalog(); say('Package saved.'); }
      catch (error) { say(error.message, true); }
    });
    $('#cancel-catalog-edit')?.addEventListener('click', () => { $('#catalog-admin-form').reset(); $('#catalog-id').value = ''; });
    $('#payment-qr-form')?.addEventListener('submit', async (event) => {
      event.preventDefault(); const form = event.currentTarget; const body = new FormData(form); body.append('method', $('#payment-qr-method').value);
      try {
        const response = await fetch('/api/admin/payment-qr/save.php', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-Token': token }, body });
        const result = await response.json(); if (!response.ok) throw new Error(result.message || 'QR upload failed.');
        form.reset(); await loadQrCodes(); say('Payment QR uploaded.');
      } catch (error) { say(error.message, true); }
    });
    $('#sync-calendar')?.addEventListener('click', async () => {
      try { const data = await post('/api/calendar/google/sync.php', {}); say(data.message || 'Google Calendar synced.'); }
      catch (error) { say(error.message, true); }
    });
    $('#refresh-catalog')?.addEventListener('click', () => loadCatalog().catch((error) => say(error.message, true)));
  });
})();
