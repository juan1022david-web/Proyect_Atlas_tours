document.addEventListener("DOMContentLoaded", () => {

  document.body.addEventListener("click", function (e) {

    // Abrir modal
    const openBtn = e.target.closest("[data-modal]");

    if (openBtn) {
      const modalId = openBtn.getAttribute("data-modal");
      const modal = document.querySelector(`[data-modal-id="${modalId}"]`);

      if (modal) {
        modal.classList.add("open");
      }
    }

    // Cerrar modal
    const closeBtn = e.target.closest(".close-modal");

    if (closeBtn) {
      closeBtn.closest(".modal").classList.remove("open");
    }

    // Cerrar dando click fuera del contenido
    if (e.target.classList.contains("modal")) {
      e.target.classList.remove("open");
    }

  });

});