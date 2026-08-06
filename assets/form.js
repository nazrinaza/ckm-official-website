document.addEventListener("DOMContentLoaded", () => {
  const form = document.querySelector(".enquiry-form");
  if (!form) return;

  const status = form.querySelector(".form-status");
  const button = form.querySelector('button[type="submit"]');

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    button.disabled = true;
    button.firstChild.textContent = "Sedang dihantar… ";
    status.className = "form-status loading";
    status.textContent = "Sedang menghantar permohonan…";

    try {
      const response = await fetch(form.action, {
        method: "POST",
        body: new FormData(form),
        headers: { Accept: "application/json" },
      });
      const result = await response.json();

      if (!response.ok) {
        throw new Error(result.message || "Permohonan tidak dapat dihantar.");
      }

      form.reset();
      status.className = "form-status success";
      status.textContent =
        result.message ||
        "Terima kasih. Permohonan anda telah diterima dan akan disemak.";
    } catch (error) {
      status.className = "form-status error";
      status.textContent =
        error.message || "Berlaku masalah. Sila cuba lagi sebentar.";
    } finally {
      button.disabled = false;
      button.firstChild.textContent = "Mohon Lawatan Tapak Percuma ";
    }
  });
});
