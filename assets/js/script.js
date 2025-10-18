function makeResponsive() {
    const dashboard = document.querySelector('.dashboard-container');
    if(window.innerWidth <= 768){
        dashboard.classList.add('responsive');
    } else {
        dashboard.classList.remove('responsive');
    }
}

// Call on load
window.addEventListener('load', makeResponsive);

// Call on window resize
window.addEventListener('resize', makeResponsive);
