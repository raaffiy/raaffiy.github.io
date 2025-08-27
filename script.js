document.addEventListener('DOMContentLoaded', () => {
    const card = document.querySelector('.id-card');

    // Initial state (optional, can be set in CSS)
    card.style.transform = 'translateY(20px) scale(0.98)';
    card.style.opacity = '0';

    // Animate in
    setTimeout(() => {
        card.style.transition = 'transform 0.6s cubic-bezier(0.23, 1, 0.32, 1), opacity 0.6s ease';
        card.style.transform = 'translateY(0) scale(1)';
        card.style.opacity = '1';
    }, 100);

    // Optional: Add mouse move effect for a bit of flair
    const container = document.querySelector('.card-container');
    container.addEventListener('mousemove', (e) => {
        const x = (window.innerWidth / 2 - e.clientX) / 45; // Reduced sensitivity
        const y = (window.innerHeight / 2 - e.clientY) / 45; // Reduced sensitivity
        card.style.transform = `rotateY(${-x}deg) rotateX(${y}deg) scale(1)`;
    });

    container.addEventListener('mouseleave', () => {
        card.style.transform = 'rotateY(0) rotateX(0) scale(1)';
    });
});