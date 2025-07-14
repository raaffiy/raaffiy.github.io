
document.getElementById('confetti-button').addEventListener('click', () => {
    const confettiCanvas = document.getElementById('confetti-canvas');
    const myConfetti = confetti.create(confettiCanvas, {
        resize: true,
        useWorker: true
    });

    myConfetti({
        particleCount: 150,
        spread: 180,
        origin: { y: 0.6 }
    });

    const modal = document.getElementById("myModal");
    const modalImg = document.getElementById("img01");
    modal.style.display = "flex";
    modalImg.src = "foto.png";

    const span = document.getElementsByClassName("close")[0];

    span.onclick = function() {
        modal.style.display = "none";
    }
});
