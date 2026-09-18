document.addEventListener('DOMContentLoaded', function() {
    // We will use FullCalendar.io library here
    var calendarEl = document.getElementById('reservation-calendar');
    if(!calendarEl) return;

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        selectable: true,
        events: '/api/reservations/available', // We will build this API in 'src'
        dateClick: function(info) {
            alert('Selected date: ' + info.dateStr + '. Checking availability...');
        }
    });
    calendar.render();
});
