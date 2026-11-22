document.addEventListener("DOMContentLoaded", () => {
  const tabs = document.querySelectorAll(".bcob-settings-tab");
  const panels = document.querySelectorAll(".bcob-settings-panel");

  if (!tabs.length) {
    return;
  }

  const activate = (id) => {
    tabs.forEach((tab) => {
      tab.classList.toggle("active", tab.dataset.tab === id);
    });

    panels.forEach((panel) => {
      panel.classList.toggle("active", panel.id === id);
    });
  };

  tabs.forEach((tab) => {
    tab.addEventListener("click", (e) => {
      e.preventDefault();

      const id = tab.dataset.tab;

      activate(id);

      const url = new URL(window.location);

      url.searchParams.set("tab", id);

      window.history.replaceState({}, "", url);
    });
  });

  const active = document.querySelector(".bcob-settings-tab.active");

  if (active) {
    activate(active.dataset.tab);
  } else {
    activate(tabs[0].dataset.tab);
  }
});

$(document).on("click", ".password-toggle-icon", function () {
  const $icon = $(this);
  const $input = $icon.siblings("input.password-toggle");

  if (!$input.length) return;

  const isPassword = $input.attr("type") === "password";

  $input.attr("type", isPassword ? "text" : "password");

  $icon
    .toggleClass("ri-eye-line", !isPassword)
    .toggleClass("ri-eye-off-line", isPassword);
});

$(function () {
  $(".password-toggle").each(function () {
    const $input = $(this);

    if ($input.siblings(".password-toggle-icon").length) return;

    $input.after(
      '<i class="ri-eye-line password-toggle-icon" aria-hidden="true"></i>',
    );
  });
});
