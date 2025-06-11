/*!
 * CoreUI config for JoodKitchen Admin
 * Based on CoreUI v5.2.0 (https://coreui.io)
 */

// Color system configuration
window.coreui = window.coreui || {};
window.coreui.Utils = window.coreui.Utils || {};

// Color utilities
window.coreui.Utils.colors = {
  primary: '#321fdb',
  secondary: '#ced2d8',
  success: '#2eb85c',
  info: '#39f',
  warning: '#f9b115',
  danger: '#e55353',
  light: '#ebedef',
  dark: '#636f83'
};

// Chart default colors
window.coreui.Utils.getColors = function() {
  return window.coreui.Utils.colors;
};

// App configuration
window.joodkitchen = window.joodkitchen || {};
window.joodkitchen.config = {
  app: {
    name: 'JoodKitchen Admin',
    version: '1.0.0'
  },
  sidebar: {
    minimize: false,
    unfoldable: false
  },
  theme: {
    mode: 'light'
  }
}; 