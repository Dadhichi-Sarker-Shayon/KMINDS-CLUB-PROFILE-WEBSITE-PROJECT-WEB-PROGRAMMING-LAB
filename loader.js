(async function loadSectionsAndStartApp() {
        const placeholders = Array.from(
          document.querySelectorAll("[data-include]"),
        );

        await Promise.all(
          placeholders.map(async (el) => {
            const sectionName = el.getAttribute("data-include");
            if (!sectionName) return;

            try {
              const res = await fetch(`section/${sectionName}.html`);
              if (!res.ok) {
                throw new Error(`Failed to load ${sectionName}.html`);
              }
              const html = await res.text();
              if (!html.trim()) {
                throw new Error(`Empty content for ${sectionName}.html`);
              }
              el.innerHTML = html;
            } catch (err) {
              console.error(err);
              el.innerHTML =
                '<section class="section-centered" style="padding-top: 10rem;"><p style="color: var(--amber);">Could not load section: ' +
                sectionName +
                ". Run via a local server (e.g., Live Server).</p></section>";
            }
          }),
        );
      })();
