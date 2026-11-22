document.addEventListener("DOMContentLoaded", () => {
  const note = document.getElementById("admin_note");

  if (note) {
    const counter = document.createElement("div");
    counter.className = "bcob-note-counter";
    counter.style.marginTop = "8px";
    counter.style.fontSize = "12px";
    counter.style.color = "#667085";

    note.after(counter);

    const updateCounter = () => {
      counter.textContent = `${note.value.length} characters`;
    };

    updateCounter();

    note.addEventListener("input", updateCounter);
  }

  document.querySelectorAll('input[type="number"]').forEach((input) => {
    input.addEventListener("blur", () => {
      if (input.value === "") {
        return;
      }

      if (parseFloat(input.value) < 0) {
        input.value = 0;
      }
    });
  });

  const generateBtn = document.getElementById("bcob-generate-coupon");

  if (generateBtn) {
    generateBtn.addEventListener("click", () => {
      const input = document.getElementById("coupon_code");

      const chars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";

      let code = "";

      for (let i = 0; i < 10; i++) {
        code += chars.charAt(Math.floor(Math.random() * chars.length));
      }

      input.value = code;
    });
  }
});
