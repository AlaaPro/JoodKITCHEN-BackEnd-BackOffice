/*!
 * CoreUI main script for JoodKitchen Admin
 * Based on CoreUI v5.2.0 (https://coreui.io)
 */

/* global coreui */

/**
 * --------------------------------------------------------------------------
 * CoreUI main.js
 * Licensed under MIT (https://github.com/coreui/coreui/blob/main/LICENSE)
 * --------------------------------------------------------------------------
 */

(() => {
  'use strict';

  /**
   * Sidebar functionality
   */
  const sidebarToggle = document.querySelector('.sidebar-toggler');
  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', () => {
      document.querySelector('.sidebar').classList.toggle('sidebar-narrow');
    });
  }

  /**
   * Tooltips and Popovers initialization
   */
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-coreui-toggle="tooltip"]'));
  tooltipTriggerList.map((tooltipTriggerEl) => {
    return new coreui.Tooltip(tooltipTriggerEl);
  });

  const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-coreui-toggle="popover"]'));
  popoverTriggerList.map((popoverTriggerEl) => {
    return new coreui.Popover(popoverTriggerEl);
  });

  /**
   * Navigation state management
   */
  const navItems = document.querySelectorAll('.sidebar .nav-link');
  navItems.forEach(item => {
    item.addEventListener('click', function() {
      // Remove active class from all nav items
      navItems.forEach(nav => nav.classList.remove('active'));
      // Add active class to clicked item
      this.classList.add('active');
      
      // Store active navigation in localStorage
      localStorage.setItem('activeNav', this.getAttribute('href') || this.getAttribute('id'));
    });
  });

  /**
   * Restore active navigation on page load
   */
  window.addEventListener('DOMContentLoaded', () => {
    const activeNav = localStorage.getItem('activeNav');
    if (activeNav) {
      const activeItem = document.querySelector(`[href="${activeNav}"], [id="${activeNav}"]`);
      if (activeItem) {
        activeItem.classList.add('active');
      }
    }

    // Mark current page as active based on URL
    const currentPath = window.location.pathname;
    navItems.forEach(item => {
      const href = item.getAttribute('href');
      if (href && currentPath.includes(href)) {
        item.classList.add('active');
      }
    });
  });

  /**
   * Loading states
   */
  window.showLoading = function() {
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) {
      loadingOverlay.style.display = 'flex';
    }
  };

  window.hideLoading = function() {
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) {
      loadingOverlay.style.display = 'none';
    }
  };

  /**
   * Toast notifications
   */
  window.showToast = function(message, type = 'info') {
    const toastContainer = document.getElementById('toast-container');
    if (!toastContainer) return;

    const toastId = 'toast-' + Date.now();
    const toastHtml = `
      <div class="toast" role="alert" aria-live="assertive" aria-atomic="true" id="${toastId}">
        <div class="toast-header">
          <strong class="me-auto text-${type}">JoodKitchen</strong>
          <small class="text-muted">maintenant</small>
          <button type="button" class="btn-close" data-coreui-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
          ${message}
        </div>
      </div>
    `;

    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    const toastElement = document.getElementById(toastId);
    const toast = new coreui.Toast(toastElement);
    toast.show();

    // Remove toast element after it's hidden
    toastElement.addEventListener('hidden.coreui.toast', () => {
      toastElement.remove();
    });
  };

  /**
   * Modal utilities
   */
  window.showModal = function(modalId) {
    const modalElement = document.getElementById(modalId);
    if (modalElement) {
      const modal = new coreui.Modal(modalElement);
      modal.show();
    }
  };

  window.hideModal = function(modalId) {
    const modalElement = document.getElementById(modalId);
    if (modalElement) {
      const modal = coreui.Modal.getInstance(modalElement);
      if (modal) {
        modal.hide();
      }
    }
  };

  /**
   * Initialize CoreUI components
   */
  window.addEventListener('DOMContentLoaded', () => {
    // Initialize sidebar
    const sidebarElement = document.querySelector('.sidebar');
    if (sidebarElement) {
      new coreui.Sidebar(sidebarElement);
    }

    // Initialize navigation
    const navElements = document.querySelectorAll('[data-coreui="navigation"]');
    navElements.forEach(navElement => {
      new coreui.Navigation(navElement);
    });

    // Hide loading on page load
    hideLoading();
  });

})(); 