(function () {
  'use strict';

  function initSidebarSearch() {
    var searchInput = document.getElementById('sidebarSearch');
    if (!searchInput) return;

    searchInput.addEventListener('input', function (e) {
      var query = e.target.value.toLowerCase().trim();
      var allLinks = Array.from(document.querySelectorAll('.sidebar .nav-link, .sidebar .nav-sub-link'));

      document.querySelectorAll('.sidebar .nav-item, .sidebar .nav-item-collapse').forEach(function (item) {
        item.style.display = '';
      });
      document.querySelectorAll('.sidebar .nav-section').forEach(function (section) {
        section.style.display = '';
      });

      if (query === '') return;

      var navItemMatches = new Map();

      allLinks.forEach(function (link) {
        var text = link.textContent.toLowerCase();
        var matches = text.includes(query);
        var navItem = link.closest('.nav-item');
        if (!navItem) return;

        if (matches) {
          navItemMatches.set(navItem, true);
          var collapseParent = link.closest('.nav-item-collapse');
          if (collapseParent) {
            navItemMatches.set(collapseParent, true);
            collapseParent.classList.add('open');
          }
        } else if (!navItemMatches.has(navItem)) {
          navItemMatches.set(navItem, false);
        }
      });

      navItemMatches.forEach(function (hasMatch, navItem) {
        if (!hasMatch) {
          navItem.style.display = 'none';
        }
      });

      document.querySelectorAll('.sidebar .nav-section').forEach(function (section) {
        var hasVisible = Array.from(section.querySelectorAll('.nav-item, .nav-item-collapse'))
          .some(function (item) { return item.style.display !== 'none'; });
        if (!hasVisible) {
          section.style.display = 'none';
        }
      });
    });
  }

  function initCollapsibleNav() {
    var navSections = document.querySelector('.sidebar .nav-sections');
    if (!navSections) return;

    navSections.addEventListener('click', function (e) {
      var toggle = e.target.closest('.nav-collapse-toggle');
      if (!toggle) return;

      e.preventDefault();
      var parent = toggle.closest('.nav-item-collapse');
      if (!parent) return;

      parent.classList.toggle('open');
      var isOpen = parent.classList.contains('open');
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
  }

  function initMobileDrawer() {
    var mobileMenuBtn = document.getElementById('mobileMenuBtn');
    var sidebar = document.querySelector('.sidebar');
    if (!mobileMenuBtn || !sidebar) return;

    var sidebarOverlay = document.getElementById('sidebarOverlay');
    if (!sidebarOverlay) {
      var overlay = document.createElement('div');
      overlay.id = 'sidebarOverlay';
      overlay.className = 'sidebar-overlay';
      document.body.appendChild(overlay);
      sidebarOverlay = overlay;
    }

    var mobileQuery = window.matchMedia('(max-width: 1024px)');

    function openSidebar() {
      sidebar.classList.add('open');
      mobileMenuBtn.classList.add('active');
      document.body.classList.add('sidebar-open');
      sidebarOverlay.classList.add('active');
      var sw = document.getElementById('syncWidget');
      if (sw) sw.style.display = 'none';
    }

    function closeSidebar() {
      sidebar.classList.remove('open');
      mobileMenuBtn.classList.remove('active');
      document.body.classList.remove('sidebar-open');
      sidebarOverlay.classList.remove('active');
      var sw = document.getElementById('syncWidget');
      if (sw) sw.style.display = '';
    }

    mobileMenuBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      if (sidebar.classList.contains('open')) {
        closeSidebar();
      } else {
        openSidebar();
      }
    });

    sidebarOverlay.addEventListener('click', function (e) {
      e.stopPropagation();
      closeSidebar();
    });

    sidebar.addEventListener('click', function (e) {
      var link = e.target.closest('.nav-link');
      if (!link || link.classList.contains('nav-collapse-toggle')) return;
      if (!mobileQuery.matches) return;
      closeSidebar();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && sidebar.classList.contains('open')) {
        closeSidebar();
      }
    });

    var resizeTimer;
    window.addEventListener('resize', function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () {
        if (!mobileQuery.matches && sidebar.classList.contains('open')) {
          closeSidebar();
        }
      }, 150);
    });
  }



  function ready(fn) {
    if (document.readyState !== 'loading') {
      fn();
    } else {
      document.addEventListener('DOMContentLoaded', fn);
    }
  }

  ready(function () {
    initSidebarSearch();
    initCollapsibleNav();
    initMobileDrawer();
  });
})();
