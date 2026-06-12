(function() {
  window.addEventListener("sectionsLoaded", () => {
    const wrapper = document.querySelector(".marquee-wrapper");
    const grid = document.querySelector(".courses-grid");
    const cards = document.querySelectorAll(".course-card");
    if (!wrapper || !grid) return;

    cards.forEach((card) => {
      card.addEventListener("mouseenter", () => {
        const viewportCenter = window.innerWidth / 2;
        const cardRect = card.getBoundingClientRect();
        const cardCenter = cardRect.left + cardRect.width / 2;
        wrapper.style.animationPlayState = "paused";
        grid.style.transform = `translateX(${viewportCenter - cardCenter}px)`;
      });

      card.addEventListener("mouseleave", () => {
        wrapper.style.animationPlayState = "running";
        grid.style.transform = "";
      });
    });
  });
})();
