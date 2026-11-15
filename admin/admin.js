const SHARED_RESERVATION_KEY = 'ssuReservationSystem';

let venuesData = [];

let reservationsData = [];
let reservationsRequestInFlight = false;
let reservationLogsData = [];

const VENUE_IMAGE_FALLBACKS = {
    'University Auditorium': '../assets/Auditorium.jpg',
    'Function Hall': '../assets/function Hall.jpg',
    'Open Activity Area': '../assets/bg-img.jpg'
};

let proposalHistoryData = [];

function normalizeReservationStatus(status) {
    const value = String(status || 'PENDING').toUpperCase();
    if (value === 'RESERVED' || value === 'APPROVED') return 'APPROVED';
    if (value === 'REJECTED') return 'REJECTED';
    if (value === 'OCCUPIED') return 'OCCUPIED';
    if (value === 'CANCELLED') return 'CANCELLED';
    return 'PENDING';
}

function normalizeReservation(raw = {}) {
    return {
        id: String(raw.id || Date.now()),
        venue: raw.venue || 'University Auditorium',
        venueImage: raw.imageUrl || raw.venueImage || '1exampleimages.jpg',
        requester: raw.reservedBy || raw.requester || 'Anonymous',
        userType: raw.userType || raw.eventType || 'Reservation Request',
        date: raw.date || '',
        time: `${raw.startTime || '08:00'} - ${raw.endTime || '17:00'}`,
        purpose: raw.purpose || 'No purpose provided.',
        participants: raw.participants || 'N/A',
        equipment: raw.equipment || 'See reservation notes',
        status: normalizeReservationStatus(raw.status),
        rejectionReason: raw.rejectionReason || null,
        reviewedAt: raw.reviewedAt || null,
        reviewedBy: raw.reviewedBy || null,
        submittedAt: raw.submittedAt || new Date().toISOString(),
        reservedBy: raw.reservedBy || raw.requester || 'Anonymous',
        imageUrl: raw.imageUrl || raw.venueImage || '1exampleimages.jpg'
    };
}

function readSharedReservations() {
    try {
        const raw = JSON.parse(localStorage.getItem(SHARED_RESERVATION_KEY) || '[]');
        return Array.isArray(raw) ? raw.map(normalizeReservation) : [];
    } catch (_error) {
        return [];
    }
}

function writeSharedReservations(list) {
    localStorage.setItem(SHARED_RESERVATION_KEY, JSON.stringify(list.map(item => ({
        ...item,
        status: normalizeReservationStatus(item.status),
        imageUrl: item.imageUrl || item.venueImage || '1exampleimages.jpg',
        reservedBy: item.reservedBy || item.requester || 'Anonymous',
        eventType: item.eventType || item.userType || 'Reservation Request'
    }))));
}

function bookingKey(date, venue) {
    return `${date}_${venue}`;
}

function reservationTimeBounds(reservation) {
    const [startTime, endTime] = String(reservation.time || '').split(' - ');
    const start = new Date(`${reservation.date}T${startTime}:00`);
    const end = new Date(`${reservation.date}T${endTime}:00`);
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return null;
    return { start, end };
}

function isApprovedReservation(reservation) {
    return ['Approved', 'Occupied'].includes(toLabelStatus(reservation.status));
}

function reservationIsActiveNow(reservation, now = new Date()) {
    const bounds = reservationTimeBounds(reservation);
    return isApprovedReservation(reservation) && bounds !== null && bounds.start <= now && now < bounds.end;
}

function reservationHasEnded(reservation, now = new Date()) {
    const bounds = reservationTimeBounds(reservation);
    return isApprovedReservation(reservation) && bounds !== null && bounds.end <= now;
}

function reservationIsVisibleInCalendar(reservation, now = new Date()) {
    const status = toLabelStatus(reservation.status);
    const bounds = reservationTimeBounds(reservation);
    return status !== 'Cancelled' && status !== 'Rejected' && bounds !== null && bounds.end > now;
}

function toLabelStatus(value) {
    const normalized = normalizeReservationStatus(value);
    if (normalized === 'APPROVED') return 'Approved';
    if (normalized === 'REJECTED') return 'Rejected';
    if (normalized === 'PENDING') return 'Pending';
    if (normalized === 'OCCUPIED') return 'Occupied';
    if (normalized === 'CANCELLED') return 'Cancelled';
    return 'Pending';
}

function getVenueImage(reservation) {
    const configuredImage = reservation.imageUrl || reservation.venueImage;
    const venue = venuesData.find(item => item.name === reservation.venue);
    let image = VENUE_IMAGE_FALLBACKS[reservation.venue] || configuredImage;

    if (!VENUE_IMAGE_FALLBACKS[reservation.venue] && (!image || image === '1exampleimages.jpg')) {
        image = venue && venue.image;
    }

    if (!image || image === '1exampleimages.jpg') {
        image = VENUE_IMAGE_FALLBACKS[reservation.venue];
    }

    if (image && image.startsWith('assets/')) {
        return `../${image}`;
    }

    return image || '../assets/bg-img.jpg';
}

function refreshAdminViews() {
    renderDashboardStats();
    renderVenuesGrid();
    renderReservationsTable();
    renderAdminCalendar();
    renderReservationLogs();
    populateProposalSelect();
    loadProposalData();
}

document.addEventListener('DOMContentLoaded', () => {
    const menuBtn = document.getElementById('menuBtn');
    const navLinks = document.getElementById('navLinks');
    if (menuBtn && navLinks) {
        menuBtn.addEventListener('click', () => {
            navLinks.classList.toggle('show');
        });
    }

    const currentDate = new Date();
    const monthSelect = document.getElementById('adminMonthSelect');
    const yearSelect = document.getElementById('adminYearSelect');
    if (monthSelect) monthSelect.value = String(currentDate.getMonth());
    if (yearSelect && [...yearSelect.options].some(option => option.value === String(currentDate.getFullYear()))) {
        yearSelect.value = String(currentDate.getFullYear());
    }

    loadFacilities();
    loadReservations();
    window.setInterval(() => {
        if (!document.hidden) loadReservations();
    }, 15000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) loadReservations();
    });
    renderHistoryTable();
});

async function loadFacilities() {
    try {
        const response = await fetch('../api/list_facilities.php', { headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (Array.isArray(data) && data.length > 0) {
            venuesData = data.map(facility => ({
                id: Number(facility.id),
                name: facility.name,
                location: facility.location,
                image: facility.image,
                capacity: facility.capacity,
                description: facility.description,
                facilities: facility.equipment,
                status: 'available',
                currentReservation: null
            }));
            renderDashboardStats();
            renderVenuesGrid();
        }
    } catch (_error) {
        console.error('Facility load failed:', _error);
    }
}

async function loadReservations() {
    if (reservationsRequestInFlight) return;
    reservationsRequestInFlight = true;
    let loadedFromDatabase = false;

    try {
        const response = await fetch('../api/list_reservations.php', {
            cache: 'no-store',
            headers: { Accept: 'application/json' }
        });
        if (!response.ok) throw new Error('Unable to load reservations.');

        const data = await response.json();
        reservationsData = Array.isArray(data) ? data.map(item => normalizeReservation(item)) : [];
        loadedFromDatabase = true;
    } catch (_error) {
        console.error('Falling back to local reservation store:', _error);
        reservationsData = readSharedReservations();
    }

    const shared = readSharedReservations();
    if (!loadedFromDatabase && shared.length > 0 && reservationsData.length === 0) {
        reservationsData = shared;
    }

    try {
        const response = await fetch('../api/list_reservation_logs.php', {
            cache: 'no-store',
            headers: { Accept: 'application/json' }
        });
        if (!response.ok) throw new Error('Unable to load reservation activity logs.');
        const data = await response.json();
        reservationLogsData = Array.isArray(data) ? data : [];
    } catch (error) {
        console.error('Reservation activity log load failed:', error);
        reservationLogsData = [];
    }

    reservationsRequestInFlight = false;
    refreshAdminViews();
}

function renderDashboardStats() {
    const occupiedVenues = new Set(
        reservationsData
            .filter(reservation => reservationIsActiveNow(reservation))
            .map(reservation => reservation.venue)
    );
    const occupied = occupiedVenues.size;
    const reserved = 0;
    const pending = reservationsData.filter(r => toLabelStatus(r.status) === 'Pending').length;
    const available = Math.max(venuesData.length - reserved - occupied, 0);
    const now = new Date();
    const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    const todayCount = reservationsData.filter(r => r.date === today && ['Approved', 'Occupied'].includes(toLabelStatus(r.status))).length;

    document.getElementById('statTotalVenues').innerText = venuesData.length;
    document.getElementById('statAvailable').innerText = available;
    document.getElementById('statOccupied').innerText = occupied;
    document.getElementById('statPending').innerText = pending;
    document.getElementById('statReserved').innerText = reserved;
    document.getElementById('statToday').innerText = todayCount;
}

function renderVenuesGrid() {
    const grid = document.getElementById('adminVenueGrid');
    if (!grid) return;
    grid.innerHTML = '';

    venuesData.forEach(v => {
        const currentReservation = reservationsData.find(r => r.venue === v.name && reservationIsActiveNow(r));
        const venueStatus = currentReservation ? 'occupied' : 'available';
        const badgeClass = venueStatus === 'available' ? 'available' : 'unavailable';
        const badgeText = venueStatus === 'available' ? '🟢 Available' : '🔴 Occupied';
        const venueImage = v.image && v.image.startsWith('assets/') ? `../${v.image}` : (v.image || '../assets/bg-img.jpg');

        const card = document.createElement('div');
        card.className = 'facility-card card';
        card.innerHTML = `
            <div class="facility-image" style="background-image: url('${venueImage}');">
                <span class="badge ${badgeClass}">${badgeText}</span>
            </div>
            <div class="facility-body">
                <h3>${v.name}</h3>
                <div class="location"><i class="bi bi-geo-alt"></i> ${v.location}</div>
                <p>${v.description}</p>
                <div class="meta">
                    <span><i class="bi bi-people"></i> ${v.capacity}</span>
                </div>
                <div style="background: var(--bg-light); padding: 10px; border-radius: 8px; font-size: 12px; margin-top: 5px;">
                    <p><strong>Status Info:</strong> ${currentReservation ? 'Occupied by ' + currentReservation.requester + ' (' + currentReservation.date + ')' : 'Ready for reservation bookings.'}</p>
                </div>
                <button class="btn btn-secondary" style="margin-top: 12px;" onclick="editFacility(${v.id})"><i class="bi bi-pencil"></i> Edit Facility</button>
            </div>
        `;
        grid.appendChild(card);
    });
}

function renderReservationsTable() {
    const tbody = document.querySelector('#reservationsTable tbody');
    if (!tbody) return;
    tbody.innerHTML = '';

    reservationsData.filter(res => !reservationHasEnded(res)).forEach(res => {
        const status = toLabelStatus(res.status);
        const statusBadge = status === 'Approved' ? 'badge available' : (status === 'Rejected' || status === 'Cancelled' ? 'badge unavailable' : 'badge pending');
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><strong>${res.id}</strong></td>
            <td>${res.venue}</td>
            <td>${res.requester}</td>
            <td>${res.userType}</td>
            <td>${res.date}<br><small style="color:var(--text-muted);">${res.time}</small></td>
            <td>${res.purpose}</td>
            <td><span class="${statusBadge}">${status}</span></td>
            <td>
                <div style="display: flex; gap: 6px;">
                    <button class="btn btn-secondary" style="padding: 5px 10px; font-size: 12px;" onclick="viewReservationDetails('${res.id}')"><i class="bi bi-eye"></i></button>
                    ${status === 'Pending' ? `
                        <button class="btn btn-success" style="padding: 5px 10px; font-size: 12px;" onclick="approveReservation('${res.id}')"><i class="bi bi-check-lg"></i></button>
                        <button class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;" onclick="rejectReservation('${res.id}')"><i class="bi bi-x-lg"></i></button>
                    ` : ''}
                    ${status !== 'Cancelled' ? `<button class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;" onclick="cancelReservationAdmin('${res.id}')">Cancel</button>` : ''}
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
}

async function updateReservationStatus(id, status) {
    const current = reservationsData.find(r => String(r.id) === String(id));
    if (!current) return;

    const normalizedStatus = ['APPROVED', 'REJECTED', 'CANCELLED'].includes(status) ? status : 'PENDING';
    let savedToDatabase = false;
    try {
        const response = await fetch('../api/update_reservation_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, status: normalizedStatus })
        });

        const data = await response.json();
        if (response.status === 409) {
            alert(data.message || 'This venue already has an approved reservation on that date.');
            await loadReservations();
            return;
        }
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Unable to update reservation.');
        }
        savedToDatabase = true;
    } catch (_error) {
        console.warn('Database update failed. Updating shared local reservation store instead.', _error);
    }

    if (savedToDatabase) {
        await loadReservations();
        alert(status === 'APPROVED' ? 'Reservation approved and saved.' : status === 'CANCELLED' ? 'Reservation cancelled and saved.' : 'Reservation rejected and saved.');
        return;
    }

    const nextList = readSharedReservations().map(item => {
        if (String(item.id) === String(id)) {
            return { ...item, status: normalizedStatus, reviewedAt: new Date().toISOString(), reviewedBy: 'admin' };
        }
        return item;
    });

    writeSharedReservations(nextList);
    reservationsData = nextList.map(item => normalizeReservation(item));
    refreshAdminViews();
    alert(status === 'APPROVED' ? 'Reservation approved and saved.' : status === 'CANCELLED' ? 'Reservation cancelled and saved.' : 'Reservation rejected and saved.');
}

function approveReservation(id) {
    updateReservationStatus(id, 'APPROVED');
}

function rejectReservation(id) {
    updateReservationStatus(id, 'REJECTED');
}

function cancelReservationAdmin(id) {
    if (confirm('Cancel this reservation?')) {
        updateReservationStatus(id, 'CANCELLED');
    }
}

function viewReservationDetails(id) {
    const res = reservationsData.find(r => String(r.id) === String(id));
    if (!res) return;

    const modal = document.getElementById('modal');
    const content = document.getElementById('modalContent');
    const status = toLabelStatus(res.status);
    const venueImage = getVenueImage(res);
    content.innerHTML = `
        <img class="reservation-detail-image" src="${venueImage}" alt="${res.venue} venue" onerror="this.onerror=null; this.src='../assets/bg-img.jpg';">
        <h3 style="color: var(--primary); margin-bottom: 15px;"><i class="bi bi-journal-text"></i> Reservation Details</h3>
        <p><strong>Reservation ID:</strong> ${res.id}</p>
        <p><strong>Venue:</strong> ${res.venue}</p>
        <p><strong>Requester:</strong> ${res.requester} (${res.userType})</p>
        <p><strong>Date & Time:</strong> ${res.date} | ${res.time}</p>
        <p><strong>Purpose:</strong> ${res.purpose}</p>
        <p><strong>Participants:</strong> ${res.participants} pax</p>
        <p><strong>Equipment Needed:</strong> ${res.equipment}</p>
        <p><strong>Current Status:</strong> <span class="badge ${status === 'Approved' ? 'available' : (status === 'Rejected' || status === 'Cancelled' ? 'unavailable' : 'pending')}">${status}</span></p>
        <button class="btn btn-primary btn-block" style="margin-top: 20px;" onclick="closeModal()">Close Details</button>
    `;
    modal.classList.add('show');
}

function closeModal() {
    const modal = document.getElementById('modal');
    if (modal) modal.classList.remove('show');
}

function renderAdminCalendar() {
    const grid = document.getElementById('adminCalendarGrid');
    if (!grid) return;
    const monthSelect = document.getElementById('adminMonthSelect');
    const yearSelect = document.getElementById('adminYearSelect');
    if (!monthSelect || !yearSelect) return;

    const month = Number(monthSelect.value);
    const year = Number(yearSelect.value);
    const firstDay = new Date(year, month, 1).getDay();
    const totalDays = new Date(year, month + 1, 0).getDate();
    const previousMonthDays = new Date(year, month, 0).getDate();
    let html = '<div class="calendar-day-name">Sun</div><div class="calendar-day-name">Mon</div><div class="calendar-day-name">Tue</div><div class="calendar-day-name">Wed</div><div class="calendar-day-name">Thu</div><div class="calendar-day-name">Fri</div><div class="calendar-day-name">Sat</div>';

    for (let i = firstDay - 1; i >= 0; i--) {
        html += `<div class="day" style="opacity:0.3;"><span class="day-number">${previousMonthDays - i}</span></div>`;
    }

    for (let day = 1; day <= totalDays; day++) {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const matchingReservations = reservationsData.filter(
            reservation => reservation.date === dateStr && reservationIsVisibleInCalendar(reservation)
        );
        let events = '';

        matchingReservations.forEach((reservation, index) => {
            const status = toLabelStatus(reservation.status);
            const background = status === 'Pending' ? '#6b7280' : status === 'Rejected' ? '#d92d20' : '#15803d';
            events += `<div class="day-event admin-calendar-event" data-reservation-index="${index}" style="background:${background};" title="${reservation.venue}: ${reservation.purpose}">${reservation.venue} · ${status}</div>`;
        });

        const reservedClass = matchingReservations.length > 0 ? ' day-reserved' : '';
        html += `<div class="day${reservedClass}" data-calendar-date="${dateStr}"><span class="day-number">${day}</span>${events}</div>`;
    }

    const remainder = (firstDay + totalDays) % 7;
    const trailingDays = remainder === 0 ? 0 : 7 - remainder;
    for (let day = 1; day <= trailingDays; day++) {
        html += `<div class="day" style="opacity:0.3;"><span class="day-number">${day}</span></div>`;
    }

    grid.innerHTML = html;
    grid.querySelectorAll('[data-calendar-date]').forEach(dayElement => {
        const matchingReservations = reservationsData.filter(
            reservation => reservation.date === dayElement.dataset.calendarDate && reservationIsVisibleInCalendar(reservation)
        );
        if (matchingReservations.length > 0) {
            dayElement.addEventListener('click', () => viewReservationDetails(matchingReservations[0].id));
        }
    });
}

function renderReservationLogs() {
    const tbody = document.querySelector('#reservationLogsTable tbody');
    if (!tbody) return;
    tbody.replaceChildren();

    if (reservationLogsData.length === 0) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 9;
        cell.textContent = 'No reservation activity has been logged yet.';
        row.appendChild(cell);
        tbody.appendChild(row);
        return;
    }

    const eventLabels = {
        booking_submitted: 'Booking submitted',
        approved: 'Approved',
        rejected: 'Rejected',
        cancelled: 'Cancelled',
        occupied: 'Marked occupied',
        completed: 'Reservation completed'
    };

    reservationLogsData.forEach(log => {
        const row = document.createElement('tr');
        [
            log.created_at,
            eventLabels[log.event_type] || log.event_type,
            log.reservation_code,
            log.venue,
            log.requester,
            log.reservation_date,
            `${String(log.start_time).slice(0, 5)} - ${String(log.end_time).slice(0, 5)}`,
            log.actor,
            log.details
        ].forEach(value => {
            const cell = document.createElement('td');
            cell.textContent = String(value ?? '');
            row.appendChild(cell);
        });
        tbody.appendChild(row);
    });
}

function populateProposalSelect() {
    const select = document.getElementById('proposalReservationSelect');
    if (!select) return;
    select.innerHTML = '';

    const approvedList = reservationsData.filter(r => toLabelStatus(r.status) === 'Approved');
    if (approvedList.length === 0) {
        select.innerHTML = '<option>No approved reservations available</option>';
        return;
    }

    approvedList.forEach(r => {
        const opt = document.createElement('option');
        opt.value = r.id;
        opt.innerText = `${r.id} - ${r.requester} (${r.venue} on ${r.date})`;
        select.appendChild(opt);
    });
}

function loadProposalData() {
    const select = document.getElementById('proposalReservationSelect');
    if (!select) return;

    const selectId = select.value;
    const res = reservationsData.find(r => String(r.id) === String(selectId)) || reservationsData.find(r => toLabelStatus(r.status) === 'Approved');
    if (!res) return;

    document.getElementById('pId').innerText = 'PROP-' + res.id;
    document.getElementById('pDateGen').innerText = 'August 16, 2026';
    document.getElementById('pRequesterName').innerText = res.requester;
    document.getElementById('pRequesterDept').innerText = res.userType;
    document.getElementById('pSubjVenue').innerText = res.venue;
    document.getElementById('pVenueName').innerText = res.venue;
    document.getElementById('pResDate').innerText = res.date;
    document.getElementById('pResTime').innerText = res.time;
    document.getElementById('pPurpose').innerText = res.purpose;
    document.getElementById('pParticipants').innerText = res.participants + ' pax';
    document.getElementById('pEquipment').innerText = res.equipment;

    updateProposalPreview();
}

function updateProposalPreview() {
    document.getElementById('outSig1Name').innerText = document.getElementById('sig1Name').value;
    document.getElementById('outSig1Pos').innerText = document.getElementById('sig1Pos').value;
    document.getElementById('outSig2Name').innerText = document.getElementById('sig2Name').value;
    document.getElementById('outSig2Pos').innerText = document.getElementById('sig2Pos').value;
    document.getElementById('outSig3Name').innerText = document.getElementById('sig3Name').value;
    document.getElementById('outSig3Pos').innerText = document.getElementById('sig3Pos').value;
    document.getElementById('outSig4Name').innerText = document.getElementById('sig4Name').value;
    document.getElementById('outSig4Pos').innerText = document.getElementById('sig4Pos').value;
    document.getElementById('outSig5Name').innerText = document.getElementById('sig5Name').value;
    document.getElementById('outSig5Pos').innerText = document.getElementById('sig5Pos').value;
    document.getElementById('outSig6Name').innerText = document.getElementById('sig6Name').value;
    document.getElementById('outSig6Pos').innerText = document.getElementById('sig6Pos').value;
}

function printProposal() {
    window.print();
}

function downloadPDF() {
    const venueName = document.getElementById('pVenueName').innerText.replace(/\s+/g, '_');
    const dateStr = document.getElementById('pResDate').innerText;
    const filename = `SSU_Venue_Proposal_${venueName}_${dateStr}.pdf`;
    alert(`Downloading PDF document:\nFilename: ${filename}\n(Simulated frontend PDF generation with 6 signatories successful)`);
}

function renderHistoryTable() {
    const tbody = document.querySelector('#historyTable tbody');
    if (!tbody) return;
    tbody.innerHTML = '';

    proposalHistoryData.forEach(h => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><strong>${h.proposalId}</strong></td>
            <td>${h.requester}</td>
            <td>${h.venue}</td>
            <td>${h.date}</td>
            <td>${h.purpose}</td>
            <td>${h.generatedDate}</td>
            <td><span class="badge available">${h.status}</span></td>
            <td>
                <div style="display: flex; gap: 6px;">
                    <button class="btn btn-secondary" style="padding: 5px 10px; font-size: 12px;" onclick="printProposal()"><i class="bi bi-printer"></i></button>
                    <button class="btn btn-gold" style="padding: 5px 10px; font-size: 12px;" onclick="downloadPDF()"><i class="bi bi-download"></i></button>
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
}

function editFacility(id) {
    const facility = venuesData.find(item => item.id === id);
    if (!facility) return;
    document.getElementById('facilityId').value = facility.id;
    document.getElementById('facilityName').value = facility.name;
    document.getElementById('facilityLocation').value = facility.location;
    document.getElementById('facilityImage').value = facility.image;
    document.getElementById('facilityCapacity').value = facility.capacity;
    document.getElementById('facilityEquipment').value = facility.facilities;
    document.getElementById('facilityDescription').value = facility.description;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function clearFacilityForm() {
    document.getElementById('facilityId').value = '';
    document.getElementById('facilityName').value = '';
    document.getElementById('facilityLocation').value = 'Samar State University Main Campus';
    document.getElementById('facilityImage').value = '1exampleimages.jpg';
    document.getElementById('facilityCapacity').value = '';
    document.getElementById('facilityEquipment').value = '';
    document.getElementById('facilityDescription').value = '';
}

async function saveFacility() {
    const payload = {
        id: document.getElementById('facilityId').value,
        name: document.getElementById('facilityName').value,
        location: document.getElementById('facilityLocation').value,
        image: document.getElementById('facilityImage').value,
        capacity: document.getElementById('facilityCapacity').value,
        equipment: document.getElementById('facilityEquipment').value,
        description: document.getElementById('facilityDescription').value
    };
    try {
        const response = await fetch('../api/save_facility.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Unable to save facility.');
        await loadFacilities();
        clearFacilityForm();
        alert('Facility saved permanently.');
    } catch (error) {
        alert(error.message || 'Unable to save facility.');
    }
}

function switchTab(tabId) {
    const coreTabs = ['dashboard', 'reservations', 'calendar'];
    document.querySelectorAll('.admin-section').forEach(sec => {
        const keepCorePanel = coreTabs.includes(tabId) && sec.classList.contains('core-admin-panel');
        sec.style.display = keepCorePanel ? 'block' : 'none';
        sec.classList.toggle('active', keepCorePanel);
    });

    document.querySelectorAll('.nav-item').forEach(item => {
        item.classList.remove('active');
    });

    const targetSection = document.getElementById(tabId + '-section');
    if (targetSection) {
        targetSection.style.display = 'block';
        targetSection.classList.add('active');
    }

    const activeButton = document.querySelector(`.nav-item[data-tab="${tabId}"]`);
    if (activeButton) activeButton.classList.add('active');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}