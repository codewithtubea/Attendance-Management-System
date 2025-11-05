/* ---------------------------
   Data fetching & UI binding
   --------------------------- */

/*
  The dashboard will try to fetch real data from:
    /api/getAttendance  (example)
  If that fails (no server yet), it uses fallback mock data so UI works now.
  For Activity 03 you will replace the endpoint with your PHP script URL.
*/

const API_ENDPOINT = '/api/getAttendance'; // replace with real endpoint when available

// --- mock data (used if fetch fails) ---
const mockData = {
  student: { name: "Tracy", id: "S12345" },
  stats: { total: 12, attended: 11, missed: 1, upcoming: 2 },
  sessions: [
    { date: "2025-10-02", title: "Intro to Web Tech", type: "Lecture", status: "Present", note: "" },
    { date: "2025-10-04", title: "Web Lab 1", type: "Lab", status: "Absent", note: "Bring Arduino kit" },
    { date: "2025-10-06", title: "Data Structures", type: "Lecture", status: "Present", note: "" },
    { date: "2025-10-09", title: "Database Lab", type: "Lab", status: "Present", note: "SQL practice" }
  ]
};

// util to format date nicely
function friendlyDate(d){
  const dt = new Date(d);
  return dt.toLocaleDateString(undefined, { month:'short', day:'numeric', year:'numeric' });
}

async function loadAttendance(){
  let data = null;
  try {
    const res = await fetch(API_ENDPOINT, {cache:'no-store'});
    if (!res.ok) throw new Error('No server data');
    data = await res.json();
  } catch(err){
    // fallback to mock data if fetch fails
    data = mockData;
  }
  bindDataToUI(data);
}

function bindDataToUI(data){
  // header / hero
  document.querySelector('.hero-left h1').textContent = `Welcome back, ${data.student?.name || 'Student'} 👋`;

  // stats
  document.getElementById('totalSessions').textContent = data.stats.total;
  document.getElementById('attendedCount').textContent = data.stats.attended;
  document.getElementById('missedCount').textContent = data.stats.missed;
  document.getElementById('upcomingCount').textContent = data.stats.upcoming;

  // attendance pct and progress bar
  const pct = Math.round((data.stats.attended / Math.max(1, data.stats.total)) * 100);
  document.getElementById('attendancePct').textContent = `${pct}%`;
  const progressBar = document.getElementById('progressBar');
  if (progressBar) progressBar.setAttribute('stroke-dasharray', `${pct},100`);

  // recent sessions
  const recentScroll = document.getElementById('recentScroll');
  recentScroll.innerHTML = '';
  data.sessions.forEach(s => {
    const card = document.createElement('div');
    card.className = 'session-card';
    const html = `
      <strong>${s.title}</strong>
      <div class="muted">${friendlyDate(s.date)}</div>
      <div class="meta">
        <div class="badges">
          <span class="badge ${s.type.toLowerCase() === 'lab' ? 'lab' : 'lecture'}">${s.type}</span>
          <span class="muted" style="margin-left:8px">${s.status}</span>
        </div>
        <div><small class="muted">${s.note || ''}</small></div>
      </div>
    `;
    card.innerHTML = html;
    recentScroll.appendChild(card);
  });
}

/* ---------------------------
   UI interactions
   --------------------------- */
document.addEventListener('DOMContentLoaded', () => {
  loadAttendance();

  // sidebar collapse
  const sidebar = document.getElementById('sidebar');
  const collapseBtn = document.getElementById('collapseBtn');
  collapseBtn?.addEventListener('click', () => sidebar.classList.toggle('collapsed'));

  // nav switching (content sections)
  document.querySelectorAll('.nav-item').forEach(btn => {
    btn.addEventListener('click', (e) => {
      document.querySelectorAll('.nav-item').forEach(n=>n.classList.remove('active'));
      btn.classList.add('active');
      const target = btn.dataset.target;
      if (!target) return;
      // show/hide content sections by id
      document.querySelectorAll('.content').forEach(sec => {
        sec.hidden = sec.id !== target;
      });
    });
  });

  // open/close modal
  const reportBtn = document.getElementById('reportIssueBtn');
  const modal = document.getElementById('issueModal');
  const closeModal = document.getElementById('closeModal');
  const cancelIssue = document.getElementById('cancelIssue');
  const issueForm = document.getElementById('issueForm');

  reportBtn?.addEventListener('click', () => {
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
  });
  closeModal?.addEventListener('click', closeIssueModal);
  cancelIssue?.addEventListener('click', closeIssueModal);
  window.addEventListener('click', (ev) => { if (ev.target === modal) closeIssueModal(); });

  function closeIssueModal(){
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden','true');
  }

  // issue form submit (simulate send)
  issueForm?.addEventListener('submit', (e) => {
    e.preventDefault();
    // collect fields
    const payload = {
      course: document.getElementById('issueCourse').value,
      date: document.getElementById('issueDate').value,
      message: document.getElementById('issueMessage').value
    };

    // show success toast
    showToast('✅ Attendance issue submitted. We will review it.');

    // In Activity 03 you will POST to a server endpoint e.g.
    // fetch('/api/reportIssue', {method:'POST', body: JSON.stringify(payload), headers:{'Content-Type':'application/json'}})

    issueForm.reset();
    closeIssueModal();
  });
});

/* simple toast */
function showToast(text){
  const t = document.createElement('div');
  t.className = 'toast';
  t.textContent = text;
  Object.assign(t.style, {position:'fixed',right:'18px',bottom:'28px',background:'var(--red-1)',color:'#fff',padding:'10px 14px',borderRadius:'8px',boxShadow:'0 6px 18px rgba(0,0,0,0.12)'});
  document.body.appendChild(t);
  setTimeout(()=> t.style.opacity = '0', 2200);
  setTimeout(()=> t.remove(), 2600);
}
