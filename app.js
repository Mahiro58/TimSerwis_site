document.addEventListener('DOMContentLoaded', function () {
    const hamburger = document.querySelector('.hamburger');
    const navbarMenu = document.querySelector('.navbar_menu');
    
    hamburger.addEventListener('click', function () {
        hamburger.classList.toggle('active');
        navbarMenu.classList.toggle('active');
    });
});

const swiper = new Swiper('.swiper-container', {
    slidesPerView: 4, // Liczba widocznych slajdów na dużych ekranach
    spaceBetween: 20, // Odstęp między slajdami
    loop: true, // Włącza zapętlone przewijanie
    navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev',
    },
    pagination: {
        el: '.swiper-pagination',
        clickable: true,
    },
    breakpoints: {
        1024: {
            slidesPerView: 4,
        },
        768: {
            slidesPerView: 2,
        },
        480: {
            slidesPerView: 1,
        },
    }
});
