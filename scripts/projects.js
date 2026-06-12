(function () {
  window.addEventListener("sectionsLoaded", () => {
    const stack = document.getElementById("cardsStack");
    const dots = document.getElementById("projectsDots");
    const counter = document.getElementById("projectsCounterCurrent");
    const counterTotal = document.querySelector(".projects-counter-total");
    if (!stack) return;

    const cards = Array.from(stack.querySelectorAll(".project-card"));
    const total = cards.length;
    let current = 0;

    if (counterTotal) {
      counterTotal.textContent = `/ ${String(total).padStart(2, "0")}`;
    }

    function setActive(index) {
      cards.forEach((card, i) => card.classList.toggle("is-active", i === index));

      if (dots) {
        dots.querySelectorAll(".projects-dot").forEach((dot, i) => {
          dot.classList.toggle("active", i === index);
        });
      }

      if (counter) {
        counter.textContent = String(index + 1).padStart(2, "0");
        counter.classList.remove("counter-pop");
        void counter.offsetWidth;
        counter.classList.add("counter-pop");
      }
    }

    function advance() {
      current = (current + 1) % total;
      setActive(current);
    }

    if (dots && dots.children.length === 0) {
      cards.forEach((_, i) => {
        const dot = document.createElement("button");
        dot.type = "button";
        dot.className = "projects-dot";
        dot.setAttribute("data-dot", String(i));
        dot.addEventListener("click", () => {
          current = i;
          setActive(current);
        });
        dots.appendChild(dot);
      });
    }

    setActive(0);
    let timer = setInterval(advance, 3500);

    stack.addEventListener("mouseenter", () => clearInterval(timer));
    stack.addEventListener("mouseleave", () => {
      timer = setInterval(advance, 3500);
    });
  });
})();
