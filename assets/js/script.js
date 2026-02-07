// Sidebar toggle if needed (for future sliding menu)
const sidebar = document.getElementById('sidebar');

function toggleSidebar() {
  sidebar.classList.toggle('collapsed');
}
// ===== Appointments JS =====
document.addEventListener('DOMContentLoaded', () => {
  const appointmentForm = document.getElementById('book-appointment-form');
  const tableBody = document.querySelector('.appointments-table tbody');

  // Load existing appointments from localStorage or initialize
  let appointments = JSON.parse(localStorage.getItem('appointments')) || [
    {
      date: '2026-01-20',
      time: '10:00 AM',
      doctor: 'Dr. Smith',
      status: 'Confirmed'
    },
    {
      date: '2026-02-10',
      time: '09:00 AM',
      doctor: 'Dr. Allen',
      status: 'Pending'
    }
  ];

  // Function to render appointments table
  function renderAppointments() {
    tableBody.innerHTML = '';
    appointments.forEach((appt, index) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${appt.date}</td>
        <td>${appt.time}</td>
        <td>${appt.doctor}</td>
        <td>${appt.status}</td>
        <td>
          <button class="reschedule-btn" data-index="${index}">Reschedule</button>
          <button class="cancel-btn" data-index="${index}">Cancel</button>
        </td>
      `;
      tableBody.appendChild(tr);
    });
  }

  // Initial render
  renderAppointments();

  // Handle booking new appointment
  appointmentForm.addEventListener('submit', e => {
    e.preventDefault();

    const doctor = document.getElementById('doctor').value;
    const clinic = document.getElementById('clinic').value; // optional for future
    const date = document.getElementById('date').value;
    const time = document.getElementById('time').value;

    if (!doctor || !date || !time) return alert('Please fill all fields.');

    appointments.push({
      date,
      time,
      doctor,
      status: 'Pending'
    });

    localStorage.setItem('appointments', JSON.stringify(appointments));
    renderAppointments();
    appointmentForm.reset();
    alert('Appointment booked successfully!');
  });

  // Handle reschedule / cancel actions
  tableBody.addEventListener('click', e => {
    const index = e.target.dataset.index;
    if (e.target.classList.contains('cancel-btn')) {
      if (confirm('Are you sure you want to cancel this appointment?')) {
        appointments.splice(index, 1);
        localStorage.setItem('appointments', JSON.stringify(appointments));
        renderAppointments();
      }
    }
    if (e.target.classList.contains('reschedule-btn')) {
      const newDate = prompt('Enter new date (YYYY-MM-DD):', appointments[index].date);
      const newTime = prompt('Enter new time (HH:MM AM/PM):', appointments[index].time);
      if (newDate && newTime) {
        appointments[index].date = newDate;
        appointments[index].time = newTime;
        appointments[index].status = 'Pending'; // mark as pending after reschedule
        localStorage.setItem('appointments', JSON.stringify(appointments));
        renderAppointments();
        alert('Appointment rescheduled successfully!');
      }
    }
  });
});
document.getElementById("loginForm").addEventListener("submit", function(e){
  e.preventDefault();

  const role = document.getElementById("role").value;

  if (role === "guardian") {
    window.location.href = "child_dashboard.html";
  }
  else if (role === "doctor") {
    window.location.href = "doctor_dashboard.html";
  }
  else if (role === "admin") {
    window.location.href = "admin_dashboard.html";
  }
});
