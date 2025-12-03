(function() {
  'use strict';

  function isMobile() {
    return window.innerWidth <= 768;
  }

  function initMobileMenu() {
    const nav = document.querySelector('#block-nrbgg-main-menu');
    if (!nav) return;

    const parentItems = nav.querySelectorAll(':scope > ul > li');

    parentItems.forEach(item => {
      const link = item.querySelector(':scope > a');
      const submenu = item.querySelector('ul');

      if (!link || !submenu) return;

      link.addEventListener('click', function(e) {
        if (isMobile()) {
          const clickX = e.clientX;
          const linkRect = link.getBoundingClientRect();
          const clickedToggleArea = clickX > linkRect.right - 60;
          
          if (clickedToggleArea) {
            e.preventDefault();
            
            parentItems.forEach(otherItem => {
              if (otherItem !== item) {
                otherItem.classList.remove('menu-open');
              }
            });

            item.classList.toggle('menu-open');
          }
        }
      });
    });

    document.addEventListener('click', function(e) {
      if (isMobile() && !nav.contains(e.target)) {
        parentItems.forEach(item => {
          item.classList.remove('menu-open');
        });
      }
    });

    let resizeTimer;
    window.addEventListener('resize', function() {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function() {
        if (!isMobile()) {
          parentItems.forEach(item => {
            item.classList.remove('menu-open');
          });
        }
      }, 250);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMobileMenu);
  } else {
    initMobileMenu();
  }
})();