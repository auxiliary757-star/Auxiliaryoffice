<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';
requireRole('Administrator');
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>SSU Auxiliary Office - Admin Dashboard</title>
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
    <link rel="stylesheet" href="admin.css?v=20261115-full-day-reservations" />
  </head>
  <body>
    <header class="header">
      <nav class="navbar">
        <a href="admin.php" class="logo-area">
          <img src="../assets/LOGO (2).png" alt="SSU Logo" />
          <div>
            <strong>SAMAR STATE UNIVERSITY</strong>
            <span>AUXILIARY OFFICE - ADMIN</span>
          </div>
        </a>
        <button class="menu-btn" id="menuBtn">
          <i class="bi bi-list"></i>
        </button>
        <div class="nav-links" id="navLinks">
          <div class="auth-links">
            <a href="logout.php" class="logout-btn">
              <i class="bi bi-box-arrow-right"></i> Logout
            </a>
          </div>
        </div>
      </nav>
    </header>

    <main id="app">
      <!-- DASHBOARD SECTION -->
      <section
        class="page admin-section core-admin-panel active"
        id="dashboard-section"
      >
        <div class="container">
          <div class="section-title">
            <h2>Admin Dashboard</h2>
            <p>
              Overview of university facility reservations and system metrics.
            </p>
          </div>

          <!-- Metrics Cards Grid -->
          <div class="metrics-grid">
            <div class="card metric-card">
              <div class="about-icon"><i class="bi bi-buildings"></i></div>
              <div>
                    <h3 id="statTotalVenues">0</h3>
                <p>Total Venues</p>
              </div>
            </div>
            <div class="card metric-card">
              <div
                class="about-icon"
                style="background: var(--success-bg); color: var(--success)"
              >
                <i class="bi bi-check-circle"></i>
              </div>
              <div>
                    <h3 id="statAvailable">0</h3>
                <p>Available</p>
              </div>
            </div>
            <div class="card metric-card">
              <div
                class="about-icon"
                style="background: var(--danger-bg); color: var(--danger)"
              >
                <i class="bi bi-lock"></i>
              </div>
              <div>
                    <h3 id="statOccupied">0</h3>
                <p>Occupied</p>
              </div>
            </div>
            <div class="card metric-card">
              <div
                class="about-icon"
                style="background: var(--warning-bg); color: var(--warning)"
              >
                <i class="bi bi-hourglass-split"></i>
              </div>
              <div>
                    <h3 id="statPending">0</h3>
                <p>Pending Requests</p>
              </div>
            </div>
            <div class="card metric-card">
              <div
                class="about-icon"
                style="background: #e0f2fe; color: #0284c7"
              >
                <i class="bi bi-calendar-event"></i>
              </div>
              <div>
                    <h3 id="statReserved">0</h3>
                <p>Reserved</p>
              </div>
            </div>
            <div class="card metric-card">
              <div
                class="about-icon"
                style="background: #f3e8ff; color: #9333ea"
              >
                <i class="bi bi-calendar-day"></i>
              </div>
              <div>
                    <h3 id="statToday">0</h3>
                <p>Today's Reservations</p>
              </div>
            </div>
          </div>

          <!-- Quick Status Legend Bar -->
          <div
            class="card"
            style="
              padding: 20px;
              margin-top: 30px;
              display: flex;
              align-items: center;
              justify-content: space-around;
              flex-wrap: wrap;
              gap: 15px;
            "
          >
            <span style="font-weight: 600; color: var(--primary)"
              >Status Indicators:</span
            >
            <span class="badge available">🟢 Available</span>
            <span class="badge pending">🟠 Pending</span>
            <span class="badge" style="background: #e0f2fe; color: #0284c7"
              >🔵 Reserved</span
            >
            <span class="badge unavailable">🔴 Occupied</span>
          </div>
        </div>
      </section>

      <!-- VENUES SECTION -->
      <section
        class="page admin-section"
        id="venues-section"
        style="display: none"
      >
        <div class="container">
          <div class="section-title">
            <h2>Venue Availability & Management</h2>
            <p>
              Real-time tracking of venue statuses, schedules, and full details.
            </p>
          </div>
          <div class="card form-section facility-editor">
            <h3><i class="bi bi-pencil-square"></i> Add or Edit Facility</h3>
            <div class="form-grid">
              <input type="hidden" id="facilityId" />
              <div class="form-group">
                <label class="form-label">Facility Name</label
                ><input id="facilityName" type="text" />
              </div>
              <div class="form-group">
                <label class="form-label">Location</label
                ><input
                  id="facilityLocation"
                  type="text"
                  value="Samar State University Main Campus"
                />
              </div>
              <div class="form-group">
                <label class="form-label">Image File</label
                ><input
                  id="facilityImage"
                  type="text"
                  value="1exampleimages.jpg"
                />
              </div>
              <div class="form-group">
                <label class="form-label">Capacity</label
                ><input id="facilityCapacity" type="text" />
              </div>
              <div class="form-group">
                <label class="form-label">Equipment</label
                ><input id="facilityEquipment" type="text" />
              </div>
              <div class="form-group full">
                <label class="form-label">Description</label
                ><textarea id="facilityDescription"></textarea>
              </div>
              <div class="form-group full" style="display: flex; gap: 10px">
                <button
                  type="button"
                  class="btn btn-primary"
                  onclick="saveFacility()"
                >
                  <i class="bi bi-save"></i> Save Facility</button
                ><button
                  type="button"
                  class="btn btn-secondary"
                  onclick="clearFacilityForm()"
                >
                  Clear
                </button>
              </div>
            </div>
          </div>
          <div class="facility-grid" id="adminVenueGrid">
            <!-- Injected via JS -->
          </div>
        </div>
      </section>

      <!-- RESERVATIONS MANAGEMENT SECTION -->
      <section
        class="page admin-section core-admin-panel"
        id="reservations-section"
      >
        <div class="container">
          <div class="section-title">
            <h2>Reservation Requests</h2>
            <p>
              Manage, approve, or reject incoming facility reservations with
              double-booking prevention.
            </p>
            <button
              type="button"
              class="btn btn-secondary"
              onclick="switchTab('reservation-logs')"
            >
              View reservation activity logs
            </button>
          </div>
          <div class="card form-section" style="overflow-x: auto">
            <table class="admin-table" id="reservationsTable">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Venue</th>
                  <th>Requester</th>
                  <th>User Type</th>
                  <th>Date & Time</th>
                  <th>Purpose</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <!-- Injected via JS -->
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- ADMIN CALENDAR SECTION -->
      <section
        class="page admin-section core-admin-panel"
        id="calendar-section"
      >
        <div class="container">
          <div class="section-title">
            <h2>Admin Calendar Schedule</h2>
            <p>
              Comprehensive calendar view tracking all venue bookings and
              events.
            </p>
          </div>
          <div class="card calendar-card">
            <div class="calendar-header">
              <h3>Master Schedule Calendar</h3>
              <div style="display: flex; gap: 10px">
                <select id="adminMonthSelect" onchange="renderAdminCalendar()">
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
                <select id="adminYearSelect" onchange="renderAdminCalendar()">
                  <option value="2025">2025</option>
                  <option value="2026" selected>2026</option>
                  <option value="2027">2027</option>
                </select>
              </div>
            </div>
            <div class="calendar" id="adminCalendarGrid"></div>
          </div>
        </div>
      </section>

      <!-- PROPOSAL LETTERS GENERATOR SECTION -->
      <section
        class="page admin-section"
        id="proposals-section"
        style="display: none"
      >
        <div class="container">
          <div class="section-title">
            <h2>Proposal Letter Generator</h2>
            <p>
              Select an approved reservation to automatically construct and
              customize the official institutional proposal.
            </p>
          </div>
          <div class="card form-section">
            <h3>
              <i class="bi bi-file-earmark-medical"></i> Select Approved
              Reservation
            </h3>
            <div class="form-grid">
              <div class="form-group full">
                <label class="form-label">Approved Reservation Item</label>
                <select
                  id="proposalReservationSelect"
                  onchange="loadProposalData()"
                >
                  <!-- Dynamic options -->
                </select>
              </div>
            </div>
          </div>

          <!-- Proposal Live Preview & Editor Card -->
          <div class="card form-section" style="margin-top: 30px">
            <div
              style="
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                flex-wrap: wrap;
                gap: 15px;
              "
            >
              <h3>
                <i class="bi bi-eye"></i> Proposal Letter Preview & Signatories
                Editor (6 Signatories)
              </h3>
              <div style="display: flex; gap: 10px">
                <button class="btn btn-gold" onclick="downloadPDF()">
                  <i class="bi bi-download"></i> Download Proposal
                </button>
                <button class="btn btn-primary" onclick="printProposal()">
                  <i class="bi bi-printer"></i> Print Proposal
                </button>
              </div>
            </div>

            <!-- Editable Signatories Configuration Row (Configured for exactly 6 Signatories) -->
            <div
              style="
                background: var(--bg-light);
                padding: 15px;
                border-radius: 8px;
                margin-bottom: 20px;
                border: 1px solid var(--border);
              "
            >
              <h4
                style="
                  font-size: 14px;
                  color: var(--primary);
                  margin-bottom: 10px;
                "
              >
                <i class="bi bi-pencil-square"></i> Edit Signatory Details (6
                Signatories Required)
              </h4>
              <div
                class="form-grid"
                style="grid-template-columns: repeat(3, 1fr)"
              >
                <div class="form-group">
                  <label class="form-label"
                    >Signatory 1 Name (Prepared By)</label
                  >
                  <input
                    type="text"
                    id="sig1Name"
                    value="Juan Dela Cruz"
                    oninput="updateProposalPreview()"
                  />
                </div>
                <div class="form-group">
                  <label class="form-label">Signatory 1 Position</label>
                  <input
                    type="text"
                    id="sig1Pos"
                    value="Administrative Assistant"
                    oninput="updateProposalPreview()"
                  />
                </div>
                <div class="form-group">
                  <label class="form-label"
                    >Signatory 2 Name (Checked By)</label
                  >
                  <input
                    type="text"
                    id="sig2Name"
                    value="Maria Santos"
                    oninput="updateProposalPreview()"
                  />
                </div>
                <div class="form-group">
                  <label class="form-label">Signatory 2 Position</label>
                  <input
                    type="text"
                    id="sig2Pos"
                    value="Auxiliary Coordinator"
                    oninput="updateProposalPreview()"
                  />
                </div>
                <div class="form-group">
                  <label class="form-label"
                    >Signatory 3 Name (Recommending Approval)</label
                  >
                  <input
                    type="text"
                    id="sig3Name"
                    value="Dr. Arnel B. Baac"
                    oninput="updateProposalPreview()"
                  />
                </div>
                <div class="form-group">
                  <label class="form-label">Signatory 3 Position</label>
                  <input
                    type="text"
                    id="sig3Pos"
                    value="Director of Student Affairs"
                    oninput="updateProposalPreview()"
                  />
                </div>
                <div class="form-group">
                  <label class="form-label"
                    >Signatory 4 Name (Budget Verified)</label
                  >
                  <input
                    type="text"
                    id="sig4Name"
                    value="Atty. Clarissa M. Gomez"
                    oninput="updateProposalPreview()"
                  />
                </div>
                <div class="form-group">
                  <label class="form-label">Signatory 4 Position</label>
                  <input
                    type="text"
                    id="sig4Pos"
                    value="University Accountant"
                    oninput="updateProposalPreview()"
                  />
                </div>
                <div class="form-group">
                  <label class="form-label"
                    >Signatory 5 Name (Reviewed By)</label
                  >
                  <input
                    type="text"
                    id="sig5Name"
                    value="Prof. Victorino P. Ruiz"
                    oninput="updateProposalPreview()"
                  />
                </div>
                <div class="form-group">
                  <label class="form-label">Signatory 5 Position</label>
                  <input
                    type="text"
                    id="sig5Pos"
                    value="Dean of Administration"
                    oninput="updateProposalPreview()"
                  />
                </div>
                <div class="form-group">
                  <label class="form-label"
                    >Signatory 6 Name (Approved By)</label
                  >
                  <input
                    type="text"
                    id="sig6Name"
                    value="Dr. Rolando G. Delorino"
                    oninput="updateProposalPreview()"
                  />
                </div>
                <div class="form-group">
                  <label class="form-label">Signatory 6 Position</label>
                  <input
                    type="text"
                    id="sig6Pos"
                    value="Vice President for Administration"
                    oninput="updateProposalPreview()"
                  />
                </div>
              </div>
            </div>

            <!-- Official SSU Proposal Document Print Wrapper -->
            <div id="printableProposal" class="proposal-document-preview">
              <div class="proposal-letterhead">
                <h3>SAMAR STATE UNIVERSITY</h3>
                <h4>AUXILIARY OFFICE</h4>
                <p>Catbalogan City, Samar, Philippines</p>
              </div>

              <div class="proposal-meta-date">
                <p>
                  <strong>Date Generated:</strong>
                  <span id="pDateGen">August 16, 2026</span>
                </p>
                <p>
                  <strong>Proposal ID:</strong>
                  <span id="pId">PROP-2026-001</span>
                </p>
              </div>

              <div class="proposal-recipient">
                <p>
                  <strong>FOR:</strong> The Vice President for Administration
                </p>
                <p><strong>THROUGH:</strong> Auxiliary Office Head</p>
                <p>
                  <strong>FROM:</strong>
                  <span id="pRequesterName">[Requester Name]</span>,
                  <span id="pRequesterDept">[Department/Office]</span>
                </p>
                <p>
                  <strong>SUBJECT:</strong> Request for Venue Reservation -
                  <span id="pSubjVenue">[Venue Name]</span>
                </p>
              </div>

              <div class="proposal-body-text">
                <p>Greetings!</p>
                <p>
                  The undersigned respectfully requests approval for the
                  reservation of university facilities specified below in
                  connection with our scheduled departmental activity:
                </p>

                <table class="proposal-info-table">
                  <tr>
                    <td><strong>Venue Name:</strong></td>
                    <td id="pVenueName">[Venue Name]</td>
                  </tr>
                  <tr>
                    <td><strong>Location:</strong></td>
                    <td id="pVenueLoc">Samar State University Main Campus</td>
                  </tr>
                  <tr>
                    <td><strong>Reservation Date:</strong></td>
                    <td id="pResDate">[Date]</td>
                  </tr>
                  <tr>
                    <td><strong>Time Slot:</strong></td>
                    <td id="pResTime">[Time]</td>
                  </tr>
                  <tr>
                    <td><strong>Purpose / Event:</strong></td>
                    <td id="pPurpose">[Purpose]</td>
                  </tr>
                  <tr>
                    <td><strong>Expected Participants:</strong></td>
                    <td id="pParticipants">[Count] pax</td>
                  </tr>
                  <tr>
                    <td><strong>Required Facilities / Equipment:</strong></td>
                    <td id="pEquipment">
                      Standard Sound System, Projector, Microphones, Air
                      Conditioning
                    </td>
                  </tr>
                </table>

                <p>
                  We commit to maintain cleanliness, safety, and proper
                  stewardship of the facility throughout the duration of our
                  program. Your favorable approval regarding this request is
                  deeply appreciated.
                </p>
              </div>

              <!-- 6 Signatories Grid Layout -->
              <div class="proposal-signatories-grid">
                <div class="signatory-box">
                  <p><strong>Prepared by:</strong></p>
                  <div class="sig-line"></div>
                  <p><strong id="outSig1Name">Juan Dela Cruz</strong></p>
                  <p><span id="outSig1Pos">Administrative Assistant</span></p>
                  <p>Date: <span id="outPrepDate">August 16, 2026</span></p>
                </div>
                <div class="signatory-box">
                  <p><strong>Checked by:</strong></p>
                  <div class="sig-line"></div>
                  <p><strong id="outSig2Name">Maria Santos</strong></p>
                  <p><span id="outSig2Pos">Auxiliary Coordinator</span></p>
                  <p>Date: ___________</p>
                </div>
                <div class="signatory-box">
                  <p><strong>Recommending Approval:</strong></p>
                  <div class="sig-line"></div>
                  <p><strong id="outSig3Name">Dr. Arnel B. Baac</strong></p>
                  <p>
                    <span id="outSig3Pos">Director of Student Affairs</span>
                  </p>
                  <p>Date: ___________</p>
                </div>
                <div class="signatory-box">
                  <p><strong>Budget Verified:</strong></p>
                  <div class="sig-line"></div>
                  <p>
                    <strong id="outSig4Name">Atty. Clarissa M. Gomez</strong>
                  </p>
                  <p><span id="outSig4Pos">University Accountant</span></p>
                  <p>Date: ___________</p>
                </div>
                <div class="signatory-box">
                  <p><strong>Reviewed by:</strong></p>
                  <div class="sig-line"></div>
                  <p>
                    <strong id="outSig5Name">Prof. Victorino P. Ruiz</strong>
                  </p>
                  <p><span id="outSig5Pos">Dean of Administration</span></p>
                  <p>Date: ___________</p>
                </div>
                <div class="signatory-box">
                  <p><strong>Approved by:</strong></p>
                  <div class="sig-line"></div>
                  <p>
                    <strong id="outSig6Name">Dr. Rolando G. Delorino</strong>
                  </p>
                  <p>
                    <span id="outSig6Pos"
                      >Vice President for Administration</span
                    >
                  </p>
                  <p>Date: ___________</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- COMPLETED RESERVATION LOGS SECTION -->
      <section
        class="page admin-section"
        id="reservation-logs-section"
        style="display: none"
      >
        <div class="container">
          <div class="section-title">
            <h2>Reservation Activity Logs</h2>
            <p>
              Booking requests, approvals, rejections, cancellations, and
              completed reservations are recorded together here.
            </p>
          </div>
          <div class="card form-section" style="overflow-x: auto">
            <table class="admin-table" id="reservationLogsTable">
              <thead>
                <tr>
                  <th>Logged At</th>
                  <th>Activity</th>
                  <th>Reservation ID</th>
                  <th>Venue</th>
                  <th>Requester</th>
                  <th>Reservation Date</th>
                  <th>Reservation Time</th>
                  <th>Actor</th>
                  <th>Details</th>
                </tr>
              </thead>
              <tbody>
                <!-- Injected via JS -->
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- PROPOSAL HISTORY SECTION -->
      <section
        class="page admin-section"
        id="history-section"
        style="display: none"
      >
        <div class="container">
          <div class="section-title">
            <h2>Proposal History</h2>
            <p>
              Archive of generated official proposals with quick print and
              download access.
            </p>
          </div>
          <div class="card form-section" style="overflow-x: auto">
            <table class="admin-table" id="historyTable">
              <thead>
                <tr>
                  <th>Proposal ID</th>
                  <th>Requester</th>
                  <th>Venue</th>
                  <th>Reservation Date</th>
                  <th>Purpose</th>
                  <th>Date Generated</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <!-- Injected via JS -->
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- REPORTS SECTION -->
      <section
        class="page admin-section"
        id="reports-section"
        style="display: none"
      >
        <div class="container">
          <div class="section-title">
            <h2>Auxiliary Reports & Analytics</h2>
            <p>
              Summary statistics on facility utilization and reservation
              frequencies.
            </p>
          </div>
          <div class="about-grid">
            <div class="card about-card">
              <div class="about-icon"><i class="bi bi-pie-chart"></i></div>
              <h3>Utilization Rate</h3>
              <p
                style="
                  font-size: 24px;
                  font-weight: 700;
                  color: var(--primary);
                  margin-top: 10px;
                "
              >
                68.5%
              </p>
              <p style="margin-top: 5px">
                Average across all 3 campus venues this month.
              </p>
            </div>
            <div class="card about-card">
              <div class="about-icon"><i class="bi bi-check2-all"></i></div>
              <h3>Approval Rate</h3>
              <p
                style="
                  font-size: 24px;
                  font-weight: 700;
                  color: var(--success);
                  margin-top: 10px;
                "
              >
                92.3%
              </p>
              <p style="margin-top: 5px">
                Processed reservation requests successfully honored.
              </p>
            </div>
            <div class="card about-card">
              <div class="about-icon"><i class="bi bi-people-fill"></i></div>
              <h3>Total Attendees Served</h3>
              <p
                style="
                  font-size: 24px;
                  font-weight: 700;
                  color: var(--accent);
                  margin-top: 10px;
                "
              >
                1,450+
              </p>
              <p style="margin-top: 5px">
                University students, faculty, and guests accommodated.
              </p>
            </div>
          </div>
        </div>
      </section>

      <!-- SETTINGS SECTION -->
      <section
        class="page admin-section"
        id="settings-section"
        style="display: none"
      >
        <div class="container">
          <div class="section-title">
            <h2>System Settings</h2>
            <p>Configure administrative preferences and office defaults.</p>
          </div>
          <div class="card form-section">
            <h3><i class="bi bi-sliders"></i> Auxiliary Office Parameters</h3>
            <div class="form-grid">
              <div class="form-group">
                <label class="form-label">Office Email Notification</label>
                <input type="email" value="auxiliary@ssu.edu.ph" />
              </div>
              <div class="form-group">
                <label class="form-label">Auto-Approval Threshold</label>
                <select>
                  <option>Manual Review Required for All</option>
                  <option>Auto-approve Student Meetings</option>
                </select>
              </div>
              <div class="form-group full">
                <button
                  type="button"
                  class="btn btn-primary"
                  onclick="alert('Settings saved successfully!')"
                >
                  Save System Changes
                </button>
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
            <img src="../assets/LOGO (2).png" alt="SSU Logo" />
            <div>
              <strong>Samar State University</strong>
              <span>Auxiliary Office - Administration</span>
            </div>
          </div>
          <p>
            Administrative management portal for university facilities and venue
            reservations.
          </p>
        </div>
        <div class="footer-column">
          <h3>Quick Links</h3>
          <a href="#dashboard" onclick="switchTab('dashboard')">Dashboard</a>
          <a href="#venues" onclick="switchTab('venues')">Venues</a>
          <a href="#reservations" onclick="switchTab('reservations')"
            >Reservations</a
          >
          <a href="#calendar" onclick="switchTab('calendar')">Calendar</a>
          <a
            href="#reservation-logs"
            onclick="switchTab('reservation-logs')"
            >Reservation Logs</a
          >
        </div>
        <div class="footer-column">
          <h3>Support</h3>
          <a href="#settings" onclick="switchTab('settings')">Settings</a>
          <a href="logout.php">Logout Session</a>
        </div>
      </div>
      <div class="copyright">
        © 2026 Samar State University Auxiliary Office
      </div>
    </footer>

    <!-- MODAL COMPONENT -->
    <div class="modal" id="modal">
      <div class="modal-content">
        <button class="close-modal" onclick="closeModal()">
          <i class="bi bi-x-lg"></i>
        </button>
        <div id="modalContent"></div>
      </div>
    </div>

    <script src="admin.js?v=20261115-full-day-reservations"></script>
  </body>
</html>
