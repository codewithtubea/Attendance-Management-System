// util to format date nicely
function friendlyDate(d){
  const dt = new Date(d);
  return dt.toLocaleDateString(undefined, { month:'short', day:'numeric', year:'numeric' });
}

document.addEventListener('DOMContentLoaded', () => {
  // sidebar collapse
  const sidebar = document.getElementById('sidebar');
  const collapseBtn = document.getElementById('collapseBtn');
  
  if (collapseBtn && sidebar) {
    collapseBtn.addEventListener('click', function() {
      sidebar.classList.toggle('collapsed');
      
      // Update collapse button arrow direction
      if (sidebar.classList.contains('collapsed')) {
        collapseBtn.innerHTML = '▶';
        collapseBtn.setAttribute('aria-label', 'Expand sidebar');
      } else {
        collapseBtn.innerHTML = '◀';
        collapseBtn.setAttribute('aria-label', 'Collapse sidebar');
      }
    });
  }

  // REMOVE THIS CONFLICTING NAV SWITCHING CODE - COMMENT IT OUT OR DELETE
  /*
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
  */

  // open/close modal - FOR AVAILABLE COURSES
  const reportBtn = document.getElementById('reportIssueBtn');
  const modal = document.getElementById('coursesModal'); // Changed from issueModal to coursesModal
  const closeModal = document.getElementById('closeCoursesModal');
  
  if (reportBtn && modal) {
    reportBtn.addEventListener('click', () => {
      modal.style.display = 'flex';
      modal.setAttribute('aria-hidden', 'false');
    });
  }
  
  if (closeModal) {
    closeModal.addEventListener('click', closeCoursesModal);
  }
  
  window.addEventListener('click', (ev) => { 
    if (ev.target === modal) closeCoursesModal(); 
  });

  function closeCoursesModal(){
    if (modal) {
      modal.style.display = 'none';
      modal.setAttribute('aria-hidden','true');
    }
  }

  // REMOVE THE OLD ISSUE FORM CODE - COMMENT IT OUT OR DELETE
  /*
  const cancelIssue = document.getElementById('cancelIssue');
  const issueForm = document.getElementById('issueForm');

  cancelIssue?.addEventListener('click', closeIssueModal);

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

    issueForm.reset();
    closeIssueModal();
  });
  */
});

/* simple toast */
function showToast(text){
  const t = document.createElement('div');
  t.className = 'toast';
  t.textContent = text;
  Object.assign(t.style, {
    position:'fixed',
    right:'18px',
    bottom:'28px',
    background:'var(--red-1)',
    color:'#fff',
    padding:'10px 14px',
    borderRadius:'8px',
    boxShadow:'0 6px 18px rgba(0,0,0,0.12)',
    zIndex: 1000
  });
  document.body.appendChild(t);
  setTimeout(() => t.style.opacity = '0', 2200);
  setTimeout(() => t.remove(), 2600);
}