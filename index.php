<?php
declare(strict_types=1);
require_once __DIR__ . '/config/auth.php';
$user = requireLogin();
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SSU Auxiliary Reservation System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap"
      rel="stylesheet"
    />
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    />
    <link rel="stylesheet" href="style.css?v=20261115-full-day-reservations" />
  </head>
  <body>
    <header class="header">
      <nav class="navbar">
        <a href="#home" class="logo-area">
          <img src="assets/LOGO (2).png" alt="Samar State University logo" />
          <div>
            <strong>SAMAR STATE UNIVERSITY</strong>
                <span>AUXILIARY OFFICE - USER DASHBOARD</span>
          </div>
        </a>
        <button class="menu-btn" id="menuBtn">
          <i class="bi bi-list"></i>
        </button>
        <div class="nav-links" id="navLinks">
          <a href="#home">Home</a>
          <a href="#facilities">Facilities</a>
          <a href="#reservations">Reservations</a>
          <a href="#about">About Us</a>
          <a href="#contact">Contact</a>
          <div class="auth-links">
                <span style="color: var(--muted); font-size: .82rem;">Hi, <?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></span>
                <a href="logout.php" class="logout-btn">
              <i class="bi bi-box-arrow-right"></i> Logout
            </a>
          </div>
        </div>
      </nav>
    </header>

    <main id="app">
      <!-- HERO -->
      <section class="hero" id="home">
        <div class="hero-container">
          <div class="hero-text">
            <span class="hero-label">
              OFFICIAL UNIVERSITY FACILITY PORTAL
            </span>
            <h1>
              Auxiliary<br />
              Reservation
            </h1>
            <p>
              Reserve university facilities and venues easily, quickly, and
              conveniently. Check availability and submit your reservation
              request online.
            </p>
            <div class="hero-buttons">
              <a href="#facilities" class="btn btn-gold">
                <i class="bi bi-building"></i>
                Browse Facilities
              </a>
              <a href="#availability" class="btn btn-secondary">
                <i class="bi bi-calendar-check"></i>
                Check Availability
              </a>
            </div>
          </div>
        </div>
      </section>

      <!-- FACILITIES -->
      <section class="page" id="facilities">
        <div class="container">
          <div class="section-title">
            <h2>Find the Right Venue</h2>
            <p>Explore university facilities available for reservation.</p>
          </div>
          <div class="facility-grid">
            <!-- FACILITY 1 -->
            <div class="facility-card card" data-venue="University Auditorium">
              <div
                class="facility-image"
                style="background-image: url(&quot;assets/Auditorium.jpg&quot;)"
              >
                <span class="badge status-badge available"> Available </span>
              </div>
              <div class="facility-body">
                <h3>University Auditorium</h3>
                <div class="location">
                  <i class="bi bi-geo-alt"></i>
                  Samar State University
                </div>
                <p>
                  A spacious venue suitable for seminars, meetings, programs,
                  and university events.
                </p>
                <div class="meta">
                  <span>
                    <i class="bi bi-people"></i>
                    Large Capacity
                  </span>
                  <span>
                    <i class="bi bi-building"></i>
                    Indoor
                  </span>
                </div>
                <button
                  class="btn btn-primary btn-block reserve-action-btn"
                  onclick="openReservationFor('University Auditorium')"
                >
                  Reserve Venue
                </button>
              </div>
            </div>

            <!-- FACILITY 2 -->
            <div class="facility-card card" data-venue="Function Hall">
              <div
                class="facility-image"
                style="
                  background-image: url(&quot;assets/function Hall.jpg&quot;);
                "
              >
                <span class="badge status-badge available"> Available </span>
              </div>
              <div class="facility-body">
                <h3>Function Hall</h3>
                <div class="location">
                  <i class="bi bi-geo-alt"></i>
                  Samar State University
                </div>
                <p>
                  A flexible venue for meetings, workshops, training sessions,
                  and special university activities.
                </p>
                <div class="meta">
                  <span>
                    <i class="bi bi-people"></i>
                    Medium Capacity
                  </span>
                  <span>
                    <i class="bi bi-building"></i>
                    Indoor
                  </span>
                </div>
                <button
                  class="btn btn-primary btn-block reserve-action-btn"
                  onclick="openReservationFor('Function Hall')"
                >
                  Reserve Venue
                </button>
              </div>
            </div>

            <!-- FACILITY 3 -->
            <div class="facility-card card" data-venue="Open Activity Area">
              <div
                class="facility-image"
                style="
                  background-image: url(&quot;771820532_1070341262108450_967357750020224919_n.jpg&quot;);
                "
              >
                <span class="badge status-badge available"> Available </span>
              </div>
              <div class="facility-body">
                <h3>Open Activity Area</h3>
                <div class="location">
                  <i class="bi bi-geo-alt"></i>
                  Samar State University
                </div>
                <p>
                  An open space suitable for university activities, gatherings,
                  exhibits, and outdoor events.
                </p>
                <div class="meta">
                  <span>
                    <i class="bi bi-people"></i>
                    Large Capacity
                  </span>
                  <span>
                    <i class="bi bi-tree"></i>
                    Outdoor
                  </span>
                </div>
                <button
                  class="btn btn-primary btn-block reserve-action-btn"
                  onclick="openReservationFor('Open Activity Area')"
                >
                  Reserve Venue
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- AVAILABILITY -->
      <section class="page" id="availability">
        <div class="container">
          <div class="section-title">
            <h2>Check Availability</h2>
            <p>Check venue availability before submitting your reservation.</p>
          </div>
          <div class="card calendar-card">
            <div class="calendar-header">
              <h3>Facility Availability</h3>
              <div style="display: flex; gap: 10px">
                <select
                  id="calendarMonthSelect"
                  onchange="generateFullCalendar()"
                >
                  <option value="0">January</option>
                  <option value="1">February</option>
                  <option value="2">March</option>
                  <option value="3">April</option>
                  <option value="4">May</option>
                  <option value="5">June</option>
                  <option value="6">July</option>
                  <option value="7" selected>August</option>
                  <option value="8">September</option>
                  <option value="9">October</option>
                  <option value="10">November</option>
                  <option value="11">December</option>
                </select>
                <select
                  id="calendarYearSelect"
                  onchange="generateFullCalendar()"
                >
                  <option value="2025">2025</option>
                  <option value="2026" selected>2026</option>
                  <option value="2027">2027</option>
                  <option value="2028">2028</option>
                  <option value="2029">2029</option>
                  <option value="2030">2030</option>
                </select>
              </div>
            </div>
            <div class="calendar" id="calendarGrid">
              <!-- Dynamic full month calendar injected via JS -->
            </div>
            <div class="calendar-legend">
              <span class="calendar-legend-marker" aria-hidden="true"></span>
              <span>Approved or occupied reservation</span>
              <span class="calendar-legend-marker calendar-legend-pending" aria-hidden="true"></span>
              <span>Your pending request</span>
              <span class="calendar-legend-marker calendar-legend-rejected" aria-hidden="true"></span>
              <span>Your rejected request</span>
              <span class="calendar-legend-marker calendar-legend-available" aria-hidden="true"></span>
              <span>Available</span>
            </div>
          </div>
        </div>
      </section>

      <!-- RESERVATIONS -->
      <section class="page" id="reservations">
        <div class="container">
          <div class="section-title">
            <h2>Make a Reservation</h2>
            <p>Submit your facility reservation request.</p>
          </div>
          <div class="card form-section">
            <h3>
              <i class="bi bi-calendar-plus"></i>
              Reservation Information
            </h3>
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label"> Select Venue </label>
                <select id="reservationVenue">
                  <option value="University Auditorium">
                    University Auditorium
                  </option>
                  <option value="Function Hall">Function Hall</option>
                  <option value="Open Activity Area">Open Activity Area</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label"> Event Type </label>
                <select id="reservationEventType">
                  <option>Seminar</option>
                  <option>Meeting</option>
                  <option>Training</option>
                  <option>University Program</option>
                  <option>Other</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label"> Reservation Date </label>
                <input type="date" id="reservationDate" />
              </div>
              <div class="form-group">
                <label class="form-label">Start Time</label>
                <input type="time" id="reservationStartTime" value="08:00" />
              </div>
              <div class="form-group">
                <label class="form-label">End Time</label>
                <input type="time" id="reservationEndTime" value="17:00" />
              </div>
              <div class="form-group">
                <label class="form-label"> Number of Participants </label>
                <input
                  type="number"
                  id="reservationParticipants"
                  placeholder="Enter number"
                  min="1"
                />
              </div>
              <div class="form-group full">
                <label class="form-label"> Purpose of Reservation </label>
                <textarea
                  id="reservationPurpose"
                  placeholder="Describe the purpose of your reservation..."
                ></textarea>
              </div>
              <div class="form-group full">
                <button
                  type="button"
                  class="btn btn-primary btn-block"
                  id="submitReservationButton"
                  onclick="submitReservation()"
                >
                  <i class="bi bi-send"></i>
                  Submit Reservation Request
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="page" id="my-reservations">
        <div class="container">
          <div class="section-title">
            <h2>My Reservations</h2>
            <p>Track your submitted requests and their approval status.</p>
          </div>
          <div class="card user-reservations-card">
            <div class="table-scroll">
              <table class="user-reservations-table" id="myReservationsTable">
                <thead><tr><th>Request</th><th>Venue</th><th>Date &amp; Time</th><th>Purpose</th><th>Status</th><th>Submitted</th></tr></thead>
                <tbody><tr><td colspan="6">Loading reservations...</td></tr></tbody>
              </table>
            </div>
          </div>
        </div>
      </section>

      <!-- ABOUT -->
      <section class="page" id="about">
        <div class="container">
          <div class="section-title">
            <h2>About the Auxiliary Office</h2>
            <p>
              Supporting the university community through organized facility
              management.
            </p>
          </div>
          <div class="about-grid">
            <div class="card about-card">
              <div class="about-icon">
                <i class="bi bi-calendar-check"></i>
              </div>
              <h3>Easy Reservations</h3>
              <p>
                Make facility reservation requests through a simple online
                process.
              </p>
            </div>
            <div class="card about-card">
              <div class="about-icon">
                <i class="bi bi-clock"></i>
              </div>
              <h3>Real-Time Availability</h3>
              <p>
                View facility schedules and check availability before making a
                request.
              </p>
            </div>
            <div class="card about-card">
              <div class="about-icon">
                <i class="bi bi-shield-check"></i>
              </div>
              <h3>Organized Management</h3>
              <p>
                Help reduce scheduling conflicts and improve facility
                management.
              </p>
            </div>
          </div>
        </div>
      </section>

      <!-- CONTACT -->
      <section class="page" id="contact">
        <div class="container">
          <div class="section-title">
            <h2>Contact Us</h2>
            <p>
              Need assistance with a reservation? Contact the Auxiliary Office.
            </p>
          </div>
          <div class="contact-grid">
            <div class="contact-list">
              <div class="card contact-card">
                <div class="about-icon">
                  <i class="bi bi-envelope"></i>
                </div>
                <div>
                  <h3>Email</h3>
                  <p>auxiliary@ssu.edu.ph</p>
                </div>
              </div>
              <div class="card contact-card">
                <div class="about-icon">
                  <i class="bi bi-telephone"></i>
                </div>
                <div>
                  <h3>Contact Number</h3>
                  <p>Contact the SSU Auxiliary Office</p>
                </div>
              </div>
              <div class="card contact-card">
                <div class="about-icon">
                  <i class="bi bi-geo-alt"></i>
                </div>
                <div>
                  <h3>Office Location</h3>
                  <p>Samar State University</p>
                </div>
              </div>
            </div>
            <div class="card form-section">
              <h3>Send Us a Message</h3>
              <div class="form-grid">
                <div class="form-group">
                  <label class="form-label"> Name </label>
                  <input type="text" placeholder="Your name" />
                </div>
                <div class="form-group">
                  <label class="form-label"> Email </label>
                  <input type="email" placeholder="Your email" />
                </div>
                <div class="form-group full">
                  <label class="form-label"> Message </label>
                  <textarea placeholder="Write your message..."></textarea>
                </div>
                <div class="form-group full">
                  <button
                    type="button"
                    class="btn btn-primary"
                    onclick="alert('Message sent successfully!')"
                  >
                    Send Message
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    </main>

    <footer>
      <div class="footer-container">
        <div class="footer-column">
          <div class="footer-logo">
            <img src="assets/LOGO (2).png" alt="Samar State University logo" />
            <div>
              <strong>Samar State University</strong>
              <span>Auxiliary Office</span>
            </div>
          </div>
          <p>
            Online facility and venue reservation interface for the Samar State
            University community.
          </p>
        </div>

        <div class="footer-column">
          <h3>Quick Links</h3>
          <a href="#facilities">Facilities</a>
          <a href="#availability">Reservations</a>
          <a href="#about">About Us</a>
          <a href="#contact">Contact</a>
        </div>
        <div class="footer-column">
          <h3>Contact</h3>
          <a href="#contact"><i class="bi bi-envelope"></i> Email</a>
          <a href="#contact"><i class="bi bi-telephone"></i> Contact Number</a>
          <a href="#contact"><i class="bi bi-geo-alt"></i> Office Location</a>
        </div>
      </div>
      <div class="copyright">
        © 2026 Samar State University Auxiliary Office
      </div>
    </footer>

    <div class="modal" id="modal">
      <div class="modal-content">
        <button class="close-modal" onclick="closeModal()">
          <i class="bi bi-x-lg"></i>
        </button>
        <div id="modalContent"></div>
      </div>
    </div>

    <script src="script.js?v=20261115-reservation-error-modal"></script>
  </body>
</html>
