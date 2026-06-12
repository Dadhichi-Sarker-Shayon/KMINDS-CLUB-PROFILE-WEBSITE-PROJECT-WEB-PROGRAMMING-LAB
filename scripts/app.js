const observer = new IntersectionObserver((entries) => {
  entries.forEach((entry) => {
    if (entry.isIntersecting) entry.target.classList.add("active");
  });
}, { threshold: 0.1 });

function initReveal() {
  document.querySelectorAll(".reveal").forEach((el) => observer.observe(el));
}

function initJoinForm() {
  const joinForm = document.querySelector("#join-form");
  const joinStatus = document.querySelector("#join-status");

  if (!joinForm || !joinStatus || joinForm.dataset.bound === "true") return;
  joinForm.dataset.bound = "true";

  joinForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    joinStatus.textContent = "Submitting...";
    joinStatus.className = "join-status join-status--info";

    const submitButton = joinForm.querySelector("button[type='submit']");
    if (submitButton) submitButton.disabled = true;

    try {
      const formData = new FormData(joinForm);
      const response = await fetch("api/join.php", {
        method: "POST",
        body: formData,
      });

      const contentType = response.headers.get("content-type") || "";
      const payload = contentType.includes("application/json")
        ? await response.json()
        : { success: false, message: "Unexpected response from server." };

      if (!response.ok || !payload.success) {
        joinStatus.textContent = payload.message || "Submission failed. Please try again.";
        joinStatus.className = "join-status join-status--error";
        return;
      }

      window.location.href = "join-success.html";
    } catch (error) {
      console.error(error);
      joinStatus.textContent = "Network error. Please try again.";
      joinStatus.className = "join-status join-status--error";
    } finally {
      if (submitButton) submitButton.disabled = false;
    }
  });
}

window.addEventListener("sectionsLoaded", () => {
  initReveal();
  initJoinForm();
});

window.addEventListener("DOMContentLoaded", () => {
  initReveal();
  initJoinForm();
});
