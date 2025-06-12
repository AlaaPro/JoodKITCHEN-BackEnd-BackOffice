/*!
 * CoreUI config for JoodKitchen Admin
 * Based on CoreUI v5.2.0 (https://coreui.io)
 * Updated with Green-Primary Logo Color Scheme
 */

// Color system configuration
window.coreui = window.coreui || {};
window.coreui.Utils = window.coreui.Utils || {};

// JoodKitchen Logo Color System - Green Primary
window.coreui.Utils.colors = {
  primary: '#a9b73e',     // Green/Lime from logo - NOW PRIMARY
  secondary: '#da3c33',   // Red from logo - NOW SECONDARY
  tertiary: '#c0c4ba',    // Light Gray/Beige from logo
  dark: '#202d5b',        // Dark Navy Blue from logo
  success: '#a9b73e',     // Use logo green for success
  info: '#202d5b',        // Use logo dark blue for info
  warning: '#ffc107',     // Keep standard warning
  danger: '#da3c33',      // Use logo red for danger
  light: '#c0c4ba',       // Use logo beige for light
  
  // Green variations
  'green-light': '#b8c456',
  'green-dark': '#97a338',
  'green-subtle': 'rgba(169, 183, 62, 0.1)'
};

// Chart default colors - using green-primary palette
window.coreui.Utils.getColors = function() {
  return window.coreui.Utils.colors;
};

// Chart color variations for data visualization - Green focused
window.coreui.Utils.chartColors = {
  datasets: [
    '#a9b73e',  // Primary green
    '#da3c33',  // Secondary red
    '#202d5b',  // Dark blue
    '#c0c4ba',  // Light beige
    '#b8c456',  // Light green variation
    '#97a338',  // Dark green variation
    '#c13329',  // Dark red variation
    '#1a2347'   // Dark blue variation
  ]
};

// Gradient definitions for enhanced visuals - Green primary
window.coreui.Utils.gradients = {
  primary: 'linear-gradient(135deg, #a9b73e 0%, #97a338 100%)',
  secondary: 'linear-gradient(135deg, #da3c33 0%, #c13329 100%)',
  dark: 'linear-gradient(135deg, #202d5b 0%, #1a2347 100%)',
  brand: 'linear-gradient(135deg, #a9b73e 0%, #da3c33 50%, #202d5b 100%)',
  sidebar: 'linear-gradient(180deg, #a9b73e 0%, #97a338 100%)',
  header: 'linear-gradient(90deg, #fff 0%, #fafafa 100%)',
  card: 'linear-gradient(135deg, #f8f9fa 0%, rgba(169, 183, 62, 0.05) 100%)'
};

// App configuration
window.joodkitchen = window.joodkitchen || {};
window.joodkitchen.config = {
  app: {
    name: 'JoodKitchen Admin',
    version: '1.0.0',
    theme: 'green-primary-colors'
  },
  sidebar: {
    minimize: false,
    unfoldable: false,
    theme: 'green',
    brandColors: {
      background: window.coreui.Utils.gradients.sidebar,
      text: '#ffffff',
      accent: '#ffffff'
    }
  },
  theme: {
    mode: 'light',
    colors: window.coreui.Utils.colors,
    primary: '#a9b73e', // Green as primary
    secondary: '#da3c33', // Red as secondary
    typography: {
      fontFamily: 'Montserrat, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
      weights: {
        regular: 400,
        semibold: 600,
        bold: 700,
        extrabold: 800
      }
    }
  },
  charts: {
    defaultColors: window.coreui.Utils.chartColors.datasets,
    grid: {
      borderColor: '#c0c4ba'
    },
    tooltip: {
      backgroundColor: '#a9b73e',
      titleColor: '#ffffff',
      bodyColor: '#ffffff'
    }
  }
};

// Apply theme configuration on DOM ready
document.addEventListener('DOMContentLoaded', function() {
  // Set CSS custom properties for dynamic theming
  const root = document.documentElement;
  const colors = window.coreui.Utils.colors;
  
  // Apply green-primary colors to CSS custom properties
  Object.keys(colors).forEach(key => {
    root.style.setProperty(`--cui-${key}`, colors[key]);
  });
  
  // Apply Montserrat font family
  root.style.setProperty('--cui-font-family-base', window.joodkitchen.config.theme.typography.fontFamily);
  
  // Set primary and secondary for CoreUI
  root.style.setProperty('--cui-primary', '#a9b73e');
  root.style.setProperty('--cui-secondary', '#da3c33');
  
  console.log('JoodKitchen Admin theme loaded with green-primary colors:', colors);
  console.log('Primary color (Green):', '#a9b73e');
  console.log('Secondary color (Red):', '#da3c33');
}); 