document.addEventListener("DOMContentLoaded", () => {
  const exportBtn = document.getElementById("bcob-export-toggle");
  const exportMenu = document.getElementById("bcob-export-menu");
  const importBtn = document.getElementById("bcob-import-btn");
  const importFile = document.getElementById("bcob-import-file");

  function showLoader(text = "Please wait...") {
    let overlay = document.getElementById("bcob-loader");

    if (!overlay) {
      overlay = document.createElement("div");

      overlay.id = "bcob-loader";

      overlay.innerHTML = `
                <div class="bcob-loader-card">
                    <i class="ri-loader-4-line bcob-spin"></i>
                    <div>${text}</div>
                </div>
            `;

      document.body.appendChild(overlay);
    } else {
      overlay.querySelector("div div").textContent = text;
      overlay.style.display = "flex";
    }
  }

  function hideLoader() {
    const overlay = document.getElementById("bcob-loader");

    if (overlay) {
      overlay.style.display = "none";
    }
  }

  if (exportBtn && exportMenu) {
    exportBtn.addEventListener("click", (e) => {
      e.preventDefault();
      exportMenu.classList.toggle("is-open");
    });

    document.addEventListener("click", (e) => {
      if (!e.target.closest(".bcob-export")) {
        exportMenu.classList.remove("is-open");
      }
    });
  }

  document.querySelectorAll(".bcob-export-form").forEach((item) => {
    item.addEventListener("click", (e) => {
      e.preventDefault();

      showLoader("Preparing export...");

      const formData = new FormData();

      formData.append("action", "bcob_export_form");
      formData.append("nonce", BCOB_IMPORT.nonce);
      formData.append("form_id", item.dataset.id);

      fetch(BCOB_IMPORT.ajax, {
        method: "POST",
        body: formData,
      })
        .then((response) => response.blob())
        .then((blob) => {
          const url = URL.createObjectURL(blob);

          const a = document.createElement("a");

          a.href = url;
          a.download = "form.json";

          document.body.appendChild(a);

          a.click();

          a.remove();

          URL.revokeObjectURL(url);

          hideLoader();
        })
        .catch(() => {
          hideLoader();
          alert("Export failed.");
        });
    });
  });

  if (importBtn) {
    importBtn.addEventListener("click", () => {
      importFile.click();
    });
  }

  if (importFile) {
    importFile.addEventListener("change", () => {
      if (!importFile.files.length) {
        return;
      }

      showLoader("Importing form...");

      const formData = new FormData();

      formData.append("action", "bcob_import_form");
      formData.append("nonce", BCOB_IMPORT.nonce);
      formData.append("file", importFile.files[0]);

      fetch(BCOB_IMPORT.ajax, {
        method: "POST",
        body: formData,
      })
        .then((r) => r.json())
        .then((r) => {
          if (!r.success) {
            hideLoader();

            alert(r.data || "Import failed.");

            return;
          }

          showLoader("Import completed. Refreshing...");

          setTimeout(() => {
            location.reload();
          }, 800);
        })
        .catch(() => {
          hideLoader();

          alert("Import failed.");
        });
    });
  }
});
