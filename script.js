// --- Universal Modal Toggle Function ---
// Placed in the global scope to be accessible by onclick attributes in PHP files.
function toggleModal() {
    const modal = document.getElementById('login-modal');
    if (modal) {
        modal.classList.toggle('hidden');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Initialize Feather Icons
    feather.replace();

    // --- 1. Universal Page Elements ---
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');

    if (mobileMenuButton && mobileMenu) {
        mobileMenuButton.addEventListener('click', () => mobileMenu.classList.toggle('hidden'));
    }

    // --- 2. Custom Notification System ---
    function showNotification(message, type = 'info') {
        const colors = {
            success: 'bg-green-500',
            danger: 'bg-red-500',
            info: 'bg-indigo-500',
            magic: 'bg-purple-600'
        };
        const notification = document.createElement('div');
        notification.className = `fixed top-20 right-5 p-4 rounded-lg shadow-2xl text-white ${colors[type] || colors.info} z-[100] transition-all duration-500 transform translate-x-full`;
        notification.textContent = message;

        document.body.appendChild(notification);

        // Slide in
        setTimeout(() => notification.classList.remove('translate-x-full'), 10);
        
        // Slide out and remove
        setTimeout(() => {
            notification.classList.add('translate-x-full');
            setTimeout(() => notification.remove(), 500);
        }, 8000);
    }

    // --- 3. "MAGIC" THEME: Custom Cursor ---
    const magicCursor = document.createElement('div');
    magicCursor.className = 'magic-cursor';
    document.body.appendChild(magicCursor);

    let mouseX = 0, mouseY = 0;
    let cursorX = 0, cursorY = 0;
    const delay = 0.1;

    window.addEventListener('mousemove', e => {
        mouseX = e.clientX;
        mouseY = e.clientY;
    });

    function animateCursor() {
        cursorX += (mouseX - cursorX) * delay;
        cursorY += (mouseY - cursorY) * delay;
        magicCursor.style.left = `${cursorX}px`;
        magicCursor.style.top = `${cursorY}px`;
        requestAnimationFrame(animateCursor);
    }
    animateCursor();

    function createSparkle(x, y) {
        const sparkle = document.createElement('div');
        sparkle.className = 'sparkle';
        sparkle.style.left = `${x}px`;
        sparkle.style.top = `${y}px`;
        document.body.appendChild(sparkle);
        setTimeout(() => sparkle.remove(), 1000);
    }

    document.addEventListener('click', e => createSparkle(e.clientX, e.clientY));

    // --- 4. "MAGIC" THEME: Random Events ---
    const randomEvents = [
        "A raven flies overhead, carrying a message...",
        "The air crackles with unseen energy.",
        "You feel a surge of creative energy.",
        "A shooting star streaks across the sky!",
        "A whisper on the wind speaks of forgotten tales.",
        "RUNE_GLOW"
    ];

    function triggerRandomEvent() {
        const event = randomEvents[Math.floor(Math.random() * randomEvents.length)];
        
        if (event !== "RUNE_GLOW") {
            showNotification(event, 'magic');
        } else {
            const allProducts = document.querySelectorAll('.card-hover');
            if (allProducts.length > 0) {
                const randomProduct = allProducts[Math.floor(Math.random() * allProducts.length)];
                randomProduct.classList.add('rune-glow');
                setTimeout(() => randomProduct.classList.remove('rune-glow'), 4000);
            }
        }
    }

    setInterval(triggerRandomEvent, 25000); // Trigger an event every 25 seconds

    // --- 5. "MAGIC" THEME: "Living" Product Cards ---
    const productCards = document.querySelectorAll('.card-hover');
    productCards.forEach(card => {
        const prophecyText = "This item is destined for a journey of great importance...";
        const scryContainer = document.createElement('div');
        scryContainer.className = 'scry-container';
        scryContainer.innerHTML = `
            <button class="scry-button">Scry</button>
            <p class="prophecy-text hidden">${prophecyText}</p>
        `;
        const cardBody = card.querySelector('.p-6');
        if (cardBody) {
          cardBody.appendChild(scryContainer);
        }
    });

    document.body.addEventListener('click', e => {
        if (e.target.classList.contains('scry-button')) {
            const prophecy = e.target.nextElementSibling;
            if (prophecy) {
                prophecy.classList.toggle('hidden');
            }
            e.target.style.display = 'none'; // Hide button after clicking
        }
    });

    // --- 6. Community Design Spotlight Carousel ---
    const spotlight = document.querySelector('[data-spotlight]');
    if (spotlight) {
        const track = spotlight.querySelector('[data-spotlight-track]');
        const prevButton = spotlight.querySelector('.spotlight-prev');
        const nextButton = spotlight.querySelector('.spotlight-next');

        const scrollAmount = () => Math.max(track.clientWidth * 0.8, 200);

        const updateNavState = () => {
            const maxScrollLeft = track.scrollWidth - track.clientWidth;
            if (prevButton) {
                prevButton.disabled = track.scrollLeft <= 5;
            }
            if (nextButton) {
                nextButton.disabled = track.scrollLeft >= maxScrollLeft - 5;
            }
        };

        const scrollByDirection = direction => {
            track.scrollBy({
                left: direction * scrollAmount(),
                behavior: 'smooth'
            });
        };

        if (prevButton) {
            prevButton.addEventListener('click', () => scrollByDirection(-1));
        }

        if (nextButton) {
            nextButton.addEventListener('click', () => scrollByDirection(1));
        }

        track.addEventListener('scroll', updateNavState, { passive: true });
        updateNavState();

        let isDragging = false;
        let startX = 0;
        let startScrollLeft = 0;

        track.addEventListener('mousedown', event => {
            isDragging = true;
            track.classList.add('is-dragging');
            startX = event.pageX;
            startScrollLeft = track.scrollLeft;
        });

        document.addEventListener('mousemove', event => {
            if (!isDragging) {
                return;
            }
            const delta = event.pageX - startX;
            track.scrollLeft = startScrollLeft - delta;
        });

        document.addEventListener('mouseup', () => {
            if (!isDragging) {
                return;
            }
            isDragging = false;
            track.classList.remove('is-dragging');
        });

        track.addEventListener('mouseleave', () => {
            if (!isDragging) {
                return;
            }
            isDragging = false;
            track.classList.remove('is-dragging');
        });

        track.addEventListener('touchstart', event => {
            if (event.touches.length !== 1) {
                return;
            }
            startX = event.touches[0].pageX;
            startScrollLeft = track.scrollLeft;
        }, { passive: true });

        track.addEventListener('touchmove', event => {
            if (event.touches.length !== 1) {
                return;
            }
            const delta = event.touches[0].pageX - startX;
            track.scrollLeft = startScrollLeft - delta;
        }, { passive: true });

        track.addEventListener('wheel', event => {
            if (Math.abs(event.deltaY) <= Math.abs(event.deltaX)) {
                return;
            }
            event.preventDefault();
            track.scrollBy({ left: event.deltaY, behavior: 'smooth' });
        }, { passive: false });
    }

    const inspirationCards = document.querySelectorAll('[data-parallax]');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!reduceMotion && inspirationCards.length > 0) {
        const updateParallax = () => {
            const viewportHeight = window.innerHeight;
            inspirationCards.forEach(card => {
                const rect = card.getBoundingClientRect();
                const progress = Math.min(Math.max((viewportHeight - rect.top) / (viewportHeight + rect.height), 0), 1);
                const translate = (progress - 0.5) * 12;
                card.style.transform = `translateY(${translate.toFixed(2)}px)`;
            });
        };
        updateParallax();
        window.addEventListener('scroll', updateParallax, { passive: true });
        window.addEventListener('resize', updateParallax);
    } else if (reduceMotion) {
        inspirationCards.forEach(card => {
            card.style.transform = 'none';
        });
    }

    document.querySelectorAll('[data-save-trigger]').forEach(button => {
        button.addEventListener('click', () => {
            const parentCard = button.closest('[data-parallax]');
            if (parentCard) {
                parentCard.classList.add('is-saved');
            }
            button.disabled = true;
            button.innerHTML = '<i data-feather="check"></i>Saved to Inspirations';
            feather.replace();
            showNotification('Pinned to your Inspirations shelf. We will surface similar drops soon.', 'success');
        });
    });
});