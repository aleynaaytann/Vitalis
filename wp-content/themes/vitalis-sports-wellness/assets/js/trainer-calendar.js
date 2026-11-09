(function () {
    'use strict';
    var config = window.VitalisTrainerCalendar;
    var calendar = document.getElementById('vitalis-trainer-calendar');
    if (!config || !calendar) return;

    var now = new Date((config.today || new Date().toISOString().slice(0, 10)) + 'T12:00:00');
    var activeMonth = new Date(now.getFullYear(), now.getMonth(), 1);
    var minMonth = new Date(now.getFullYear(), now.getMonth(), 1);
    var maxParts = (config.maxMonth || '').split('-');
    var maxMonth = maxParts.length === 2 ? new Date(Number(maxParts[0]), Number(maxParts[1]) - 1, 1) : new Date(now.getFullYear(), now.getMonth() + 4, 1);
    var monthSelect = document.getElementById('vitalis-trainer-month');
    var yearSelect = document.getElementById('vitalis-trainer-year');
    var prev = document.getElementById('vitalis-trainer-prev');
    var next = document.getElementById('vitalis-trainer-next');
    var selectedDate = document.getElementById('vitalis-trainer-selected-date');
    var slotSummary = document.getElementById('vitalis-trainer-slot-summary');
    var slotsElement = document.getElementById('vitalis-trainer-slots');
    var dateInput = document.getElementById('vitalis-trainer-date');
    var timeInput = document.getElementById('vitalis-trainer-time');
    var submit = document.getElementById('vitalis-trainer-submit');
    var typeSelect = document.getElementById('vitalis-trainer-type');
    var groupField = document.querySelector('.trainer-group-size-field');
    var groupSize = document.getElementById('vitalis-trainer-group-size');
    var selectedDay = '';
    var requestNumber = 0;
    var slotRequestNumber = 0;

    function monthValue(date) {
        return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
    }

    function isBeforeToday(date) {
        var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        var value = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        return value < today;
    }

    function updatePeriodControls() {
        monthSelect.replaceChildren();
        for (var month = 0; month < 12; month += 1) {
            var value = new Date(activeMonth.getFullYear(), month, 1);
            var monthOption = document.createElement('option');
            monthOption.value = String(month + 1);
            monthOption.textContent = config.monthNames[month];
            monthOption.disabled = monthValue(value) < monthValue(minMonth) || monthValue(value) > monthValue(maxMonth);
            monthSelect.appendChild(monthOption);
        }
        monthSelect.value = String(activeMonth.getMonth() + 1);
        yearSelect.replaceChildren();
        for (var year = minMonth.getFullYear(); year <= maxMonth.getFullYear(); year += 1) {
            var yearOption = document.createElement('option');
            yearOption.value = String(year);
            yearOption.textContent = String(year);
            yearSelect.appendChild(yearOption);
        }
        yearSelect.value = String(activeMonth.getFullYear());
        prev.disabled = monthValue(activeMonth) <= monthValue(minMonth);
        next.disabled = monthValue(activeMonth) >= monthValue(maxMonth);
    }

    function post(action, values) {
        var body = new URLSearchParams();
        body.set('action', action);
        body.set('nonce', config.nonce);
        Object.keys(values).forEach(function (key) { body.set(key, values[key]); });
        return fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body.toString() })
            .then(function (response) { return response.json(); })
            .then(function (response) { if (!response || !response.success) throw new Error((response && response.data && response.data.message) || config.error); return response.data; });
    }

    function setNotice(target, message) {
        target.replaceChildren();
        var paragraph = document.createElement('p');
        paragraph.textContent = message;
        target.appendChild(paragraph);
    }

    function renderMonth(data) {
        var days = data.days || [];
        var daysInMonth = days.length;
        var firstWeekday = daysInMonth ? days[0].weekday : 1;
        updatePeriodControls();
        calendar.replaceChildren();
        (config.weekdays || []).forEach(function (weekday) {
            var label = document.createElement('span');
            label.className = 'trainer-calendar-weekday';
            label.textContent = weekday;
            calendar.appendChild(label);
        });
        for (var blank = 1; blank < firstWeekday; blank += 1) {
            var empty = document.createElement('span');
            empty.className = 'trainer-calendar-empty';
            empty.setAttribute('aria-hidden', 'true');
            calendar.appendChild(empty);
        }
        days.forEach(function (day) {
            var date = new Date(activeMonth.getFullYear(), activeMonth.getMonth(), day.day);
            var dateValue = monthValue(activeMonth) + '-' + String(day.day).padStart(2, '0');
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'trainer-calendar-day is-' + day.status;
            if (selectedDay === dateValue) button.classList.add('is-selected');
            button.disabled = day.status === 'closed' || isBeforeToday(date);
            button.setAttribute('aria-label', dateValue + ', ' + day.label + ', ' + day.available + ' müsait saat');
            var number = document.createElement('strong');
            number.textContent = String(day.day);
            var status = document.createElement('span');
            status.className = 'trainer-calendar-day-status';
            status.textContent = day.status === 'closed' ? 'Kapalı' : day.status === 'full' ? 'Dolu' : day.status === 'partial' ? 'Kısmen dolu' : 'Müsait';
            var count = document.createElement('small');
            count.textContent = day.status === 'closed' ? '—' : day.available + '/' + day.total + ' saat';
            button.append(number, status, count);
            button.addEventListener('click', function () { chooseDate(dateValue, day); });
            calendar.appendChild(button);
        });
    }

    function loadMonth() {
        var current = ++requestNumber;
        calendar.setAttribute('aria-busy', 'true');
        setNotice(calendar, config.loading);
        post('vitalis_trainer_calendar_month', { trainer_id: config.trainerId, month: monthValue(activeMonth) })
            .then(function (data) { if (current === requestNumber) renderMonth(data); })
            .catch(function () { if (current === requestNumber) setNotice(calendar, config.error); })
            .finally(function () { if (current === requestNumber) calendar.removeAttribute('aria-busy'); });
    }

    function chooseDate(value, day) {
        var current = ++slotRequestNumber;
        selectedDay = value;
        dateInput.value = value;
        timeInput.value = '';
        submit.disabled = true;
        submit.textContent = 'Önce uygun bir saat seçin →';
        var parsed = new Date(value + 'T12:00:00');
        selectedDate.textContent = new Intl.DateTimeFormat('tr-TR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(parsed);
        slotSummary.textContent = day.available + ' / ' + day.total + ' saat uygun';
        slotsElement.setAttribute('aria-busy', 'true');
        setNotice(slotsElement, 'Saatler kontrol ediliyor…');
        renderMonthSelection();
        post('vitalis_trainer_calendar_slots', { trainer_id: config.trainerId, date: value })
            .then(function (data) { if (current === slotRequestNumber) renderSlots(data); })
            .catch(function () { if (current === slotRequestNumber) setNotice(slotsElement, config.error); })
            .finally(function () { if (current === slotRequestNumber) slotsElement.removeAttribute('aria-busy'); });
    }

    function renderMonthSelection() {
        Array.prototype.forEach.call(calendar.querySelectorAll('.trainer-calendar-day'), function (button) {
            button.classList.toggle('is-selected', button.getAttribute('aria-label').indexOf(selectedDay + ',') === 0);
        });
    }

    function renderSlots(data) {
        slotsElement.replaceChildren();
        var slots = data.slots || [];
        if (!slots.length) { setNotice(slotsElement, data.message || 'Bu gün için çalışma saati bulunmuyor.'); return; }
        slots.forEach(function (slot) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'trainer-time-slot ' + (slot.available ? 'is-available' : 'is-busy');
            button.disabled = !slot.available;
            button.textContent = slot.time + (slot.available ? ' · Seç' : ' · Dolu');
            if (!slot.available && slot.demo) {
                var demo = document.createElement('small');
                demo.textContent = 'Demo doluluk';
                button.appendChild(demo);
            }
            if (slot.available) {
                button.addEventListener('click', function () {
                    Array.prototype.forEach.call(slotsElement.querySelectorAll('.trainer-time-slot'), function (item) { item.classList.remove('is-selected'); });
                    button.classList.add('is-selected');
                    timeInput.value = slot.time;
                    submit.disabled = false;
                    submit.textContent = slot.time + ' için randevu talebi gönder →';
                });
            }
            slotsElement.appendChild(button);
        });
        if (!slots.some(function (slot) { return slot.available; })) {
            slotSummary.textContent = 'Bu günün saatleri dolu';
        }
    }

    function changeMonth(date) {
        activeMonth = date;
        updatePeriodControls();
        clearSelection();
        loadMonth();
    }
    monthSelect.addEventListener('change', function () {
        changeMonth(new Date(activeMonth.getFullYear(), Number(monthSelect.value) - 1, 1));
    });
    yearSelect.addEventListener('change', function () {
        var targetMonth = new Date(Number(yearSelect.value), activeMonth.getMonth(), 1);
        if (monthValue(targetMonth) < monthValue(minMonth)) targetMonth = new Date(minMonth);
        if (monthValue(targetMonth) > monthValue(maxMonth)) targetMonth = new Date(maxMonth);
        changeMonth(targetMonth);
    });
    prev.addEventListener('click', function () {
        if (monthValue(activeMonth) <= monthValue(minMonth)) return;
        changeMonth(new Date(activeMonth.getFullYear(), activeMonth.getMonth() - 1, 1));
    });
    next.addEventListener('click', function () {
        if (monthValue(activeMonth) >= monthValue(maxMonth)) return;
        changeMonth(new Date(activeMonth.getFullYear(), activeMonth.getMonth() + 1, 1));
    });

    function clearSelection() {
        slotRequestNumber += 1;
        selectedDay = '';
        dateInput.value = '';
        timeInput.value = '';
        selectedDate.textContent = 'Önce uygun bir gün seçin';
        slotSummary.textContent = '';
        submit.disabled = true;
        submit.textContent = 'Önce takvimden saat seçin →';
        setNotice(slotsElement, 'Günün saatleri burada görünür.');
    }

    function updateType() {
        var isGroup = typeSelect.value === 'group';
        groupField.hidden = !isGroup;
        groupSize.required = isGroup;
    }
    typeSelect.addEventListener('change', updateType);
    updateType();

    document.querySelectorAll('[data-session-choice]').forEach(function (link) {
        link.addEventListener('click', function () { typeSelect.value = link.getAttribute('data-session-choice'); updateType(); });
    });

    var form = document.querySelector('.trainer-appointment-form');
    form.addEventListener('submit', function (event) {
        if (!dateInput.value || !timeInput.value) {
            event.preventDefault();
            slotsElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
            slotSummary.textContent = 'Lütfen takvimden müsait bir saat seçin.';
        }
    });

    updatePeriodControls();
    loadMonth();
}());
