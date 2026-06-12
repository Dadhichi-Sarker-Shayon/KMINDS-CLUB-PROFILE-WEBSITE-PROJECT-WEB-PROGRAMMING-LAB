(async function loadSectionsAndStartApp() {
  const placeholders = Array.from(document.querySelectorAll("[data-include]"));

  await Promise.all(
    placeholders.map(async (el) => {
      const sectionName = el.getAttribute("data-include");
      if (!sectionName) return;

      try {
        const res = await fetch(`section/${sectionName}.html`);
        if (!res.ok) throw new Error(`Failed to load ${sectionName}.html`);
        const html = await res.text();
        if (!html.trim()) throw new Error(`Empty content for ${sectionName}.html`);

        el.style.opacity = "0";
        el.style.transition = "opacity 0.6s ease-out";
        el.innerHTML = html;

        // Execute inline scripts from loaded sections
        el.querySelectorAll("script").forEach((oldScript) => {
          const newScript = document.createElement("script");
          if (oldScript.src) {
            newScript.src = oldScript.src;
          } else {
            newScript.textContent = oldScript.textContent;
          }
          oldScript.parentNode.replaceChild(newScript, oldScript);
        });

        setTimeout(() => {
          el.style.opacity = "1";
        }, 50);
      } catch (err) {
        console.error(err);
        el.innerHTML =
          '<section style="padding:10rem 4rem;"><p style="color:var(--cyan);">Could not load: ' +
          sectionName +
          ". Run via a local server (e.g., Live Server).</p></section>";
      }
    })
  );

  window.dispatchEvent(new CustomEvent("sectionsLoaded"));
})();
