$(function () {
    let prevScrollpos = window.pageYOffset;
    // MENU
    $('.navbar-collapse a').on('click',function(){
        $(".navbar-collapse").collapse('hide');
    });
    // AOS ANIMATION
    AOS.init({
        disable: 'mobile',
        duration: 800,
        anchorPlacement: 'center-bottom',
        once: true,
        offset: 120,
        easing: 'ease-in-out'
    });

    // Hide/Show navbar on scroll
    window.onscroll = function() {
        let currentScrollPos = window.pageYOffset;

        if (prevScrollpos > currentScrollPos) {
            document.querySelector(".navbar").style.top = "0";
        } else {
            document.querySelector(".navbar").style.top = "-100px"; // Adjust this value based on your navbar height
        }
        
        prevScrollpos = currentScrollPos;
    }
    // SMOOTHSCROLL NAVBAR
    $('.navbar a, .hero-text a').on('click', function(event) {
        var $anchor = $(this);
        $('html, body').stop().animate({
            scrollTop: $($anchor.attr('href')).offset().top - 49
        }, 1000);
        event.preventDefault();
    });
});