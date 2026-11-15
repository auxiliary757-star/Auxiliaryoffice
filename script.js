const SHARED_RESERVATION_KEY = "ssuReservationSystem";

function normalizeReservationStatus(status) {
  const value = String(status || "PENDING").toUpperCase();
  if (value === "APPROVED" || value === "RESERVED") return "APPROVED";
  if (value === "REJECTED") return "REJECTED";
  if (value === "OCCUPIED") return "OCCUPIED";
  if (value === "CANCELLED") return "CANCELLED";
  return "PENDING";
}

function normalizeReservation(raw = {}) {
  return {
    id:
      raw.id ||
      `RES-${new Date().getFullYear()}-${String(Date.now()).slice(-3)}`,
    user_id: raw.user_id || 0,
    venue: raw.venue || "University Auditorium",
    eventType: raw.eventType || "Seminar",
    date: raw.date || "",
    startTime: raw.startTime || "08:00",
    endTime: raw.endTime || "17:00",
    participants: raw.participants || "N/A",
    reservedBy: raw.reservedBy || "Anonymous Faculty",
    purpose: raw.purpose || "No purpose provided.",
    status: normalizeReservationStatus(raw.status),
    rejectionReason: raw.rejectionReason || null,
    submittedAt: raw.submittedAt || new Date().toISOString(),
    reviewedAt: raw.reviewedAt || null,
    reviewedBy: raw.reviewedBy || null,
    imageUrl: raw.imageUrl || "assets/Auditorium.jpg",
  };
}

function reservationEndTimeIsFuture(reservation, now = new Date()) {
  const end = new Date(`${reservation.date}T${reservation.endTime}:00`);
  return !Number.isNaN(end.getTime()) && end > now;
}

function readSharedReservations() {
  try {
    const raw = JSON.parse(
      localStorage.getItem(SHARED_RESERVATION_KEY) || "[]",
    );
    return Array.isArray(raw) ? raw.map(normalizeReservation) : [];
  } catch (_error) {
    return [];
  }
}

function writeSharedReservations(list) {
  localStorage.setItem(
    SHARED_RESERVATION_KEY,
    JSON.stringify(list.map(normalizeReservation)),
  );
}

function bookingKey(date, venue) {
  return `${date}_${venue}`;
}

function escapeHtml(value) {
  return String(value ?? "").replace(/[&<>"']/g, function (character) {
    return {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#39;",
    }[character];
  });
}

function seedSampleReservations() {
  return;
}

function saveLocalReservation(reservation) {
  const existing = readSharedReservations();
  const normalized = normalizeReservation({
    ...reservation,
    id:
      reservation.id ||
      `RES-${new Date().getFullYear()}-${String(existing.length + 1).padStart(3, "0")}`,
    status: "PENDING",
    submittedAt: new Date().toISOString(),
    reviewedAt: null,
    reviewedBy: null,
    rejectionReason: null,
    imageUrl: reservation.imageUrl || "assets/Auditorium.jpg",
  });

  const filtered = existing.filter(
    (item) =>
      bookingKey(item.date, item.venue) !==
      bookingKey(normalized.date, normalized.venue),
  );
  filtered.push(normalized);
  writeSharedReservations(filtered);
}

function removeLocalReservation(storageKey) {
  const [date, ...venueParts] = storageKey.split("_");
  const venue = venueParts.join("_");
  const existing = readSharedReservations();
  writeSharedReservations(
    existing.filter((item) => !(item.date === date && item.venue === venue)),
  );
}

document.addEventListener("DOMContentLoaded", function () {
  const menuBtn = document.getElementById("menuBtn");
  const navLinks = document.getElementById("navLinks");

  if (menuBtn && navLinks) {
    menuBtn.addEventListener("click", function () {
      navLinks.classList.toggle("show");
    });

    navLinks.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", function () {
        navLinks.classList.remove("show");
      });
    });
  }

  window.facilityReservations = {};
  window.calendarAvailability = [];
  window.calendarAvailabilityById = {};
  window.calendarAvailabilityLoaded = false;
  window.calendarOwnedReservations = {};
  let reservationsRefreshInFlight = false;
  let cancellationConfirmationResolver = null;

  const dateInput = document.getElementById("reservationDate");
  const globalCheckDate = document.getElementById("globalCheckDate");
  const monthSelect = document.getElementById("calendarMonthSelect");
  const yearSelect = document.getElementById("calendarYearSelect");
  const today = new Date();
  const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, "0")}-${String(today.getDate()).padStart(2, "0")}`;

  if (dateInput) dateInput.value = todayStr;
  if (monthSelect) {
    monthSelect.value = String(today.getMonth());
    monthSelect.addEventListener("change", function () {
      if (window.generateFullCalendar) window.generateFullCalendar();
    });
  }
  if (yearSelect) {
    yearSelect.value = String(today.getFullYear());
    yearSelect.addEventListener("change", function () {
      if (window.generateFullCalendar) window.generateFullCalendar();
    });
  }
  if (globalCheckDate) {
    globalCheckDate.value = todayStr;
    globalCheckDate.addEventListener("change", function () {
      if (window.updateFacilityCardStatuses)
        window.updateFacilityCardStatuses();
    });
  }

  async function loadFacilities() {
    const venueSelect = document.getElementById("reservationVenue");
    if (!venueSelect) return;

    let facilities = [];
    try {
      const response = await fetch("api/list_facilities.php", {
        headers: { Accept: "application/json" },
      });
      if (!response.ok) throw new Error("Facilities unavailable.");
      facilities = await response.json();
    } catch (_error) {
      facilities = [];
    }

    venueSelect.innerHTML = facilities
      .map((facility) => `<option value="${facility.name}">${facility.name}</option>`)
      .join("");

    const params = new URLSearchParams(window.location.search);
    const forceVenue = params.get("venue");
    if (forceVenue && facilities.some((facility) => facility.name === forceVenue)) {
      venueSelect.value = forceVenue;
    }
  }

  async function loadReservations() {
    if (reservationsRefreshInFlight) return;
    reservationsRefreshInFlight = true;

    try {
      let reservations = Object.values(window.calendarOwnedReservations);
      let availability = window.calendarAvailability;
      try {
        const headers = { Accept: "application/json" };
        const availabilityResponse = await fetch("api/list_availability.php", {
          headers,
          cache: "no-store",
        });
        if (!availabilityResponse.ok) {
          throw new Error("Shared calendar reservations unavailable.");
        }
        const availabilityData = await availabilityResponse.json();
        availability = Array.isArray(availabilityData) ? availabilityData : [];
        window.calendarAvailabilityLoaded = true;
      } catch (_error) {
        console.error("Shared calendar load failed:", _error);
      }

      try {
        const reservationsResponse = await fetch("api/my_reservations.php", {
          headers: { Accept: "application/json" },
          cache: "no-store",
        });
        if (!reservationsResponse.ok) {
          throw new Error("Your reservations are unavailable.");
        }
        const reservationsData = await reservationsResponse.json();
        reservations = Array.isArray(reservationsData) ? reservationsData : [];
      } catch (_error) {
        console.error("Your reservations load failed:", _error);
      }

      const map = {};
      [...reservations, ...availability].forEach(function (reservation) {
        const normalized = normalizeReservation(reservation);
        map[bookingKey(normalized.date, normalized.venue)] = normalized;
      });

      if (window.calendarAvailabilityLoaded) {
        window.calendarAvailability = availability;
        window.calendarAvailabilityById = Object.fromEntries(
          availability.map((reservation) => [
            reservation.id,
            normalizeReservation(reservation),
          ]),
        );
      }
      window.calendarOwnedReservations = Object.fromEntries(
        reservations.map((reservation) => [reservation.id, normalizeReservation(reservation)]),
      );
      window.facilityReservations = map;
      renderMyReservations(reservations);
      if (window.generateFullCalendar) window.generateFullCalendar();
      if (window.updateFacilityCardStatuses) window.updateFacilityCardStatuses();
    } finally {
      reservationsRefreshInFlight = false;
    }
  }

  window.openReservationFor = function (venueName) {
    const venueSelect = document.getElementById("reservationVenue");
    if (venueSelect) venueSelect.value = venueName;
    document
      .getElementById("reservations")
      ?.scrollIntoView({ behavior: "smooth" });
  };

  window.submitReservation = async function () {
    const submitButton = document.getElementById("submitReservationButton");
    const venueElement = document.getElementById("reservationVenue");
    const eventTypeElement = document.getElementById("reservationEventType");
    const dateElement = document.getElementById("reservationDate");
    const participantsElement = document.getElementById(
      "reservationParticipants",
    );
    const purposeElement = document.getElementById("reservationPurpose");

    if (
      !venueElement ||
      !eventTypeElement ||
      !dateElement ||
      !participantsElement ||
      !purposeElement
    ) {
      window.showReservationError(
        "The reservation form is not available. Please reload the page.",
      );
      return;
    }

    const venue = venueElement.value;
    const eventType = eventTypeElement.value;
    const dateVal = dateElement.value;
    const startTime =
      document.getElementById("reservationStartTime")?.value || "08:00";
    const endTime =
      document.getElementById("reservationEndTime")?.value || "17:00";
    const participants = participantsElement.value || "N/A";
    const purpose = purposeElement.value || "No purpose provided.";

    if (!dateVal) {
      window.showReservationError("Please select a reservation date.");
      return;
    }

    const payload = {
      venue,
      eventType,
      date: dateVal,
      startTime,
      endTime,
      participants,
      purpose,
    };
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.innerHTML =
        '<i class="bi bi-hourglass-split"></i> Saving Reservation...';
    }

    try {
      const response = await fetch("api/create_reservation.php", {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify(payload),
      });
      const data = await response.json();
      if (response.status === 409) {
        if (data.reservation?.status === "REJECTED") {
          await loadReservations();
          window.showReservationError(
            data.message ||
              data.reservation.rejectionReason ||
              "Your request was not booked because this venue is already reserved on that date.",
          );
          return;
        }
        throw new Error(data.message || "This venue is already booked during that time.");
      }
      if (!response.ok || !data.success) {
        throw new Error(data.message || "Unable to save the reservation.");
      }
      await loadReservations();
      window.showReservationMessage();
    } catch (error) {
      window.showReservationError(
        error.message || "Unable to save the reservation.",
      );
    } finally {
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.innerHTML =
          '<i class="bi bi-send"></i> Submit Reservation Request';
      }
    }
  };

  function renderMyReservations(reservations) {
    const tbody = document.querySelector("#myReservationsTable tbody");
    if (!tbody) return;
    if (reservations.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6">You have no reservations yet.</td></tr>';
      return;
    }
    tbody.innerHTML = reservations.map((reservation) => `
      <tr>
        <td><strong>${reservation.id}</strong></td>
        <td>${reservation.venue}</td>
        <td>${reservation.date}<br><small>${reservation.startTime} - ${reservation.endTime}</small></td>
        <td>${reservation.purpose}</td>
        <td><span class="badge ${reservation.status.toLowerCase()}">${reservation.status}</span></td>
        <td>${reservation.submittedAt ? new Date(reservation.submittedAt).toLocaleDateString() : "-"}</td>
      </tr>`).join("");
  }

  window.updateFacilityCardStatuses = function () {
    const cards = document.querySelectorAll(".facility-card");
    if (cards.length === 0) return;

    const now = new Date();
    const currentDate = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}-${String(now.getDate()).padStart(2, "0")}`;
    cards.forEach(function (card) {
      const venueName = card.getAttribute("data-venue");
      const badge = card.querySelector(".status-badge");
      const reserveBtn = card.querySelector(".reserve-action-btn");
      if (!badge) return;

      const isReservedToday = window.calendarAvailability.some(
        (reservation) =>
          reservation.date === currentDate &&
          reservation.venue === venueName,
      );
      if (!isReservedToday) {
        badge.className = "badge status-badge available";
        badge.innerHTML = "🟢 AVAILABLE";
        if (reserveBtn) {
          reserveBtn.disabled = false;
          reserveBtn.style.opacity = "1";
          reserveBtn.innerText = "Reserve Venue";
        }
        return;
      }

      badge.className = "badge status-badge reserved";
      badge.innerHTML = "⚪ RESERVED TODAY";
      if (reserveBtn) {
        reserveBtn.disabled = false;
        reserveBtn.style.opacity = "1";
        reserveBtn.innerText = "Choose Another Date";
      }
    });
  };

  window.generateFullCalendar = function () {
    const calendarGrid = document.getElementById("calendarGrid");
    const monthSelect = document.getElementById("calendarMonthSelect");
    const yearSelect = document.getElementById("calendarYearSelect");
    if (!calendarGrid || !monthSelect || !yearSelect) return;

    const year = Number(yearSelect.value);
    const month = Number(monthSelect.value);
    const firstDay = new Date(year, month, 1).getDay();
    const totalDays = new Date(year, month + 1, 0).getDate();
    const prevMonthDays = new Date(year, month, 0).getDate();

    let html =
      '<div class="calendar-day-name">Sun</div><div class="calendar-day-name">Mon</div><div class="calendar-day-name">Tue</div><div class="calendar-day-name">Wed</div><div class="calendar-day-name">Thu</div><div class="calendar-day-name">Fri</div><div class="calendar-day-name">Sat</div>';

    for (let i = firstDay - 1; i >= 0; i--) {
      html += `<div class="day" style="opacity:0.3;"><span class="day-number">${prevMonthDays - i}</span></div>`;
    }

    for (let day = 1; day <= totalDays; day++) {
      const dateStr = `${year}-${String(month + 1).padStart(2, "0")}-${String(day).padStart(2, "0")}`;
      const bookings = (window.calendarAvailability || []).filter(
        (reservation) => reservation.date === dateStr,
      );
      const ownBookings = Object.values(window.calendarOwnedReservations || {}).filter(
        (reservation) =>
          reservation.date === dateStr &&
          ["PENDING", "REJECTED"].includes(reservation.status) &&
          reservationEndTimeIsFuture(reservation),
      );
      const visibleBookings = [...new Map(
        [...bookings, ...ownBookings].map((reservation) => [
          String(reservation.id),
          reservation,
        ]),
      ).values()].sort((first, second) =>
        first.startTime.localeCompare(second.startTime),
      );
      const bookingEvents = visibleBookings
        .map((reservation) => {
          const venue = escapeHtml(reservation.venue);
          const time = `${escapeHtml(reservation.startTime)}–${escapeHtml(reservation.endTime)}`;
          const status = normalizeReservationStatus(reservation.status);
          const statusClass = status.toLowerCase();
          const title = `${venue}, ${time}, ${escapeHtml(status)}`;
          const isOwnedPendingOrRejected = ownBookings.some(
            (ownedReservation) => ownedReservation.id === reservation.id,
          );
          const reservedClass = isOwnedPendingOrRejected ? "" : " calendar-booking-reserved";
          return `<button type="button" class="calendar-booking${reservedClass} calendar-booking-${statusClass}" data-reservation-id="${escapeHtml(reservation.id)}" title="${title}">${venue}<small>${time} · ${escapeHtml(status)}</small></button>`;
        })
        .join("");
      const hasDateReservation =
        bookings.length > 0 ||
        ownBookings.some((reservation) => reservation.status === "PENDING");
      const reservedClass = hasDateReservation ? " day-reserved" : "";
      const reservedLabel =
        hasDateReservation
          ? '<span class="day-reserved-label">Reserved</span>'
          : "";

      const pendingLabel =
        ownBookings.some((reservation) => reservation.status === "PENDING")
          ? '<span class="day-pending-label">Pending</span>'
          : "";
      const rejectedLabel =
        ownBookings.some((reservation) => reservation.status === "REJECTED")
          ? '<span class="day-rejected-label">Rejected</span>'
          : "";
      html += `<div class="day${reservedClass}"${hasDateReservation ? ' title="This date has a pending or approved reservation"' : ""}><span class="day-number">${day}</span>${reservedLabel}${pendingLabel}${rejectedLabel}${bookingEvents}</div>`;
    }

    const remainder = (firstDay + totalDays) % 7;
    const extra = remainder === 0 ? 0 : 7 - remainder;
    for (let i = 1; i <= extra; i++) {
      html += `<div class="day" style="opacity:0.3;"><span class="day-number">${i}</span></div>`;
    }

    calendarGrid.innerHTML = html;
    calendarGrid.querySelectorAll("[data-reservation-id]").forEach((event) => {
      event.addEventListener("click", () =>
        window.expandReservationDetails(event.dataset.reservationId),
      );
    });
    calendarGrid.querySelectorAll("[data-reservation-key]").forEach((event) => {
      event.addEventListener("click", () =>
        window.expandReservationDetails(event.dataset.reservationKey),
      );
    });
  };

  window.expandReservationDetails = function (storageKey) {
    const res =
      window.calendarOwnedReservations[storageKey] ||
      window.calendarAvailabilityById[storageKey] ||
      window.facilityReservations[storageKey];
    if (!res) return;

    const isOwnedReservation = Boolean(
      res.id && window.calendarOwnedReservations[res.id],
    );
    const modal = document.getElementById("modal");
    const content = document.getElementById("modalContent");
    if (!modal || !content) return;

    const escape = escapeHtml;
    const badgeColor =
      res.status === "PENDING"
        ? "#f79009"
        : res.status === "APPROVED"
          ? "#027a48"
          : res.status === "REJECTED"
            ? "#d92d20"
            : "#1d2939";
    const statusText =
      res.status === "PENDING"
        ? "Pending Approval"
        : res.status === "APPROVED"
          ? "Approved"
          : res.status === "REJECTED"
            ? "Rejected"
            : res.status;
    const statusMessage =
      res.status === "PENDING"
        ? "Your reservation request is currently waiting for review by the SSU Auxiliary Office."
        : res.status === "APPROVED"
          ? "This reservation has been approved by the SSU Auxiliary Office."
          : res.status === "REJECTED"
            ? "Your reservation request has been rejected by the SSU Auxiliary Office."
            : res.status === "CANCELLED"
              ? "This reservation has been cancelled."
            : "Your reservation status has been updated.";

    content.innerHTML = `
            <div style="text-align:left;">
                ${isOwnedReservation ? `<div style="height:180px;background-image:url('${escape(res.imageUrl || "assets/Auditorium.jpg")}');background-size:cover;background-position:center;border-radius:10px;margin-bottom:18px;"></div>` : ""}
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;">
                    <h3 style="margin:0;color:#1d2939;font-size:20px;"><i class="bi bi-building"></i> ${escape(res.venue)}</h3>
                    <span style="background:${badgeColor};color:white;padding:5px 10px;border-radius:999px;font-size:12px;font-weight:700;">${escape(res.status)}</span>
                </div>
                <p><strong>Facility:</strong> ${escape(res.venue)}</p>
                <p><strong>Date:</strong> ${escape(res.date)}</p>
                <p><strong>Time:</strong> ${escape(res.startTime)} - ${escape(res.endTime)}</p>
                ${isOwnedReservation ? `<p><strong>Reserved By:</strong> ${escape(res.reservedBy)}</p>
                <p><strong>Event Type:</strong> ${escape(res.eventType)}</p>
                <p><strong>Participants:</strong> ${escape(res.participants)}</p>
                <p><strong>Purpose:</strong> ${escape(res.purpose)}</p>` : ""}
                <p><strong>Reservation Status:</strong> <span style="color:${badgeColor};font-weight:700;">${statusText}</span></p>
                <p style="margin-top:12px;">${statusMessage}</p>
                ${isOwnedReservation && res.rejectionReason ? `<p style="margin-top:12px;color:#b42318;"><strong>Reason for Rejection:</strong> ${escape(res.rejectionReason)}</p>` : ""}
                <div style="margin-top:20px;display:flex;justify-content:center;gap:12px;">
                    ${isOwnedReservation && ["PENDING", "REJECTED"].includes(res.status) ? `<button class="btn btn-secondary" onclick="cancelReservation('${escape(res.id)}')" style="background-color:#fee4e2;color:#d92d20;border:none;">Cancel Reservation</button>` : ""}
                    <button class="btn btn-primary" onclick="closeModal()">Close</button>
                </div>
            </div>
        `;
    modal.classList.add("show");
  };

  window.cancelReservation = async function (reservationId) {
    const confirmed = await window.showCancellationConfirmation();
    if (!confirmed) return;

    try {
      const response = await fetch("api/cancel_reservation.php", {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({ id: reservationId }),
      });
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.message || "Unable to cancel reservation.");
      closeModal();
      await loadReservations();
      window.showCancellationMessage();
    } catch (error) {
      window.showReservationError(
        error.message || "Unable to cancel reservation.",
        "Unable to Cancel Reservation",
      );
    }
  };

  window.showCancellationConfirmation = function () {
    const modal = document.getElementById("modal");
    const content = document.getElementById("modalContent");
    if (!modal || !content) {
      console.error("Reservation confirmation modal is unavailable.");
      return Promise.resolve(false);
    }

    content.innerHTML = `
      <div style="text-align:center;">
        <i class="bi bi-exclamation-triangle" style="font-size:48px;color:#d92d20;"></i>
        <h2 style="margin:15px 0 8px;color:#001F42;">Cancel Reservation?</h2>
        <p style="color:#667085;line-height:1.6;">Are you sure you want to remove or cancel this reservation?</p>
        <div style="margin-top:20px;display:flex;justify-content:center;gap:12px;flex-wrap:wrap;">
          <button class="btn btn-secondary" style="background:#edf4ff;border:1px solid #d8e2f2;color:#001F42;" onclick="closeModal()">Keep Reservation</button>
          <button class="btn btn-primary" style="background:#d92d20;border-color:#d92d20;" onclick="resolveCancellationConfirmation(true)">Yes, Cancel Reservation</button>
        </div>
      </div>
    `;
    modal.classList.add("show");

    return new Promise((resolve) => {
      cancellationConfirmationResolver = resolve;
    });
  };

  window.resolveCancellationConfirmation = function (confirmed) {
    const resolve = cancellationConfirmationResolver;
    cancellationConfirmationResolver = null;
    window.closeModal();
    if (resolve) resolve(confirmed);
  };

  window.showCancellationMessage = function () {
    const modal = document.getElementById("modal");
    const content = document.getElementById("modalContent");
    if (!modal || !content) {
      console.error("Reservation notification modal is unavailable.");
      return;
    }

    content.innerHTML = `
      <div style="text-align:center;">
        <i class="bi bi-calendar-check" style="font-size:48px;color:#027a48;"></i>
        <h2 style="margin:15px 0 8px;color:#001F42;">Reservation Cancelled</h2>
        <p style="color:#667085;line-height:1.6;">Your reservation was successfully cancelled.</p>
        <button class="btn btn-primary" style="margin-top:20px;" onclick="closeModal()">Okay</button>
      </div>
    `;
    modal.classList.add("show");
  };

  window.showReservationMessage = function () {
    const modal = document.getElementById("modal");
    const content = document.getElementById("modalContent");
    if (!modal || !content) return;

    content.innerHTML = `
            <div style="text-align:center;">
                <i class="bi bi-calendar-check" style="font-size:48px;color:#027a48;"></i>
                <h2 style="margin:15px 0 8px;color:#001F42;">Reservation Submitted Successfully</h2>
                <p style="color:#667085;line-height:1.6;">Your reservation request has been submitted to the SSU Auxiliary Office and is currently waiting for approval.</p>
                <button class="btn btn-primary" style="margin-top:20px;" onclick="closeModal(); location.hash='#availability';">View Calendar</button>
            </div>
        `;
    modal.classList.add("show");
  };

  window.showReservationError = function (
    message,
    title = "Unable to Submit Reservation",
  ) {
    const modal = document.getElementById("modal");
    const content = document.getElementById("modalContent");
    if (!modal || !content) {
      console.error("Reservation notification modal is unavailable.");
      return;
    }

    content.innerHTML = `
      <div style="text-align:center;">
        <i class="bi bi-exclamation-circle" style="font-size:48px;color:#d92d20;"></i>
        <h2 id="reservationErrorTitle" style="margin:15px 0 8px;color:#001F42;"></h2>
        <p id="reservationErrorMessage" style="color:#667085;line-height:1.6;"></p>
        <button class="btn btn-primary" style="margin-top:20px;" onclick="closeModal()">Okay</button>
      </div>
    `;
    content.querySelector("#reservationErrorTitle").textContent = title;
    content.querySelector("#reservationErrorMessage").textContent = message;
    modal.classList.add("show");
  };

  window.closeModal = function () {
    const modal = document.getElementById("modal");
    if (modal) modal.classList.remove("show");
    if (cancellationConfirmationResolver) {
      const resolve = cancellationConfirmationResolver;
      cancellationConfirmationResolver = null;
      resolve(false);
    }
  };

  loadFacilities();
  loadReservations();
  window.setInterval(() => {
    if (!document.hidden) loadReservations();
  }, 5000);
  document.addEventListener("visibilitychange", () => {
    if (!document.hidden) loadReservations();
  });
});
