document.addEventListener('DOMContentLoaded', function () {
    var calendarEl = document.getElementById('reservation-calendar');
    if (!calendarEl || typeof FullCalendar === 'undefined') return;
    var accommodation = document.getElementById('calendar-accommodation');
    var result = document.getElementById('calendar-result');
    var calendar;

    fetch('/api/catalog/list.php', { credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (body) {
            var rooms = body && body.data && body.data.accommodations ? body.data.accommodations : [];
            accommodation.innerHTML = '';
            rooms.forEach(function (room) {
                var option = document.createElement('option');
                option.value = String(room.id);
                option.textContent = room.name;
                accommodation.appendChild(option);
            });
            if (!rooms.length) throw new Error('No packages are available.');
            calendar.render();
        })
        .catch(function (error) { result.textContent = error.message || 'Could not load packages.'; });

    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        events: function (info, success, failure) {
            var url = '/api/availability/calendar.php?start=' + encodeURIComponent(info.startStr.slice(0, 10))
                + '&end=' + encodeURIComponent(info.endStr.slice(0, 10))
                + '&accommodation_id=' + encodeURIComponent(accommodation.value || '');
            fetch(url).then(function (response) { return response.json(); })
                .then(success).catch(failure);
        },
        eventClick: function (info) {
            var kind = info.event.extendedProps.kind === 'hold' ? 'Temporarily held' : 'Reserved';
            result.textContent = kind + ': ' + info.event.title + ' (' + info.event.startStr.slice(0, 10)
                + ' to ' + (info.event.endStr ? info.event.endStr.slice(0, 10) : info.event.startStr.slice(0, 10)) + ').';
        },
        dateClick: function (info) {
            var checkOut = new Date(info.dateStr + 'T12:00:00');
            checkOut.setDate(checkOut.getDate() + 1);
            var checkoutDate = checkOut.getFullYear() + '-' + String(checkOut.getMonth() + 1).padStart(2, '0') + '-' + String(checkOut.getDate()).padStart(2, '0');
            result.textContent = 'Checking ' + info.dateStr + '…';
            fetch('/api/availability/check.php?accommodation_id=' + encodeURIComponent(accommodation.value)
                + '&check_in=' + encodeURIComponent(info.dateStr) + '&check_out=' + encodeURIComponent(checkoutDate))
                .then(function (response) { return response.json(); })
                .then(function (body) {
                    if (!body.success) throw new Error(body.message || 'Availability check failed.');
                    result.textContent = body.data.available
                        ? 'Available for ' + body.data.accommodation.name + ' on ' + info.dateStr + '. Select a package to book.'
                        : 'Unavailable for ' + body.data.accommodation.name + ' on ' + info.dateStr + '. Choose another date.';
                }).catch(function (error) { result.textContent = error.message; });
        }
    });
    accommodation.addEventListener('change', function () { calendar.refetchEvents(); result.textContent = 'Select a date to check availability.'; });
});
