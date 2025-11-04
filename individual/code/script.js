// Toggle password visibility
function togglePassword(id, icon) {
  const input = document.getElementById(id);
  if (input.type === "password") {
    input.type = "text";
    icon.textContent = "🙈";
  } else {
    input.type = "password";
    icon.textContent = "👁️";
  }
}

// Handle registration success modal
const registerForm = document.getElementById("register-form");
const modal = document.getElementById("successModal");
const closeModal = document.getElementById("closeModal");

if (registerForm) {
  registerForm.addEventListener("submit", function (e) {
    e.preventDefault(); // prevent actual form submission
    modal.style.display = "flex"; // show modal
  });
}

if (closeModal) {
  closeModal.addEventListener("click", function () {
    modal.style.display = "none";
  });
}

// close modal if click outside it
window.onclick = function (event) {
  if (event.target === modal) {
    modal.style.display = "none";
  }
};
