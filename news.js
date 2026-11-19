
document.addEventListener("DOMContentLoaded", async () => {
    const newsGrid = document.getElementById("news-grid");

    if (!newsGrid) return;

    try {
        const response = await fetch("/api/posts.php");

        if (!response.ok) {
            throw new Error("Błąd pobierania aktualności.");
        }

        const data = await response.json();

        if (!data.success || !Array.isArray(data.posts)) {
            throw new Error("Nieprawidłowe dane API.");
        }

        newsGrid.replaceChildren();

        if (data.posts.length === 0) {
            const message = document.createElement("p");
            message.className = "news-message";
            message.textContent = "Brak aktualności.";

            newsGrid.appendChild(message);
            return;
        }

        data.posts.forEach((post) => {
            // Główny kafelek
            const card = document.createElement("article");
            card.className = "news-card";

            // Zdjęcie po lewej
            if (post.image_path) {
                const imageWrapper = document.createElement("div");
                imageWrapper.className = "news-image-wrapper";

                const image = document.createElement("img");
                image.className = "news-image";
                image.src = post.image_path;
                image.alt = post.title || "Zdjęcie aktualności";
                image.loading = "lazy";

                imageWrapper.appendChild(image);
                card.appendChild(imageWrapper);
            }

            // Kontener tekstu po prawej
            const content = document.createElement("div");
            content.className = "news-card-content";

            // Data publikacji
            if (post.published_at) {
                const published = new Date(
                    post.published_at.replace(" ", "T")
                );

                if (!Number.isNaN(published.getTime())) {
                    const date = document.createElement("time");
                    date.className = "news-date";
                    date.dateTime = published.toISOString();

                    date.textContent = published.toLocaleDateString(
                        "pl-PL",
                        {
                            day: "numeric",
                            month: "long",
                            year: "numeric"
                        }
                    );

                    content.appendChild(date);
                }
            }

            // Tytuł
            const title = document.createElement("h3");
            title.className = "news-title";
            title.textContent = post.title || "Aktualność";

            content.appendChild(title);

            // Pełna treść
            const description = document.createElement("div");
            description.className = "news-description";
            description.textContent = post.content || "";

            content.appendChild(description);

            // Dodanie treści do kafelka
            card.appendChild(content);

            // Dodanie kafelka do sekcji
            newsGrid.appendChild(card);
        });

    } catch (error) {
        console.error("Błąd aktualności:", error);

        const message = document.createElement("p");
        message.className = "news-message";
        message.textContent =
            "Nie udało się załadować aktualności.";

        newsGrid.replaceChildren(message);
    }
});
