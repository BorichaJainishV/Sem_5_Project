// Entry module: wires up everything
// Comments explain why the modular approach improves maintainability and performance.

import { Designer } from './core/designer.js?v=20251011';

// Boot the app after DOM is ready
window.addEventListener('DOMContentLoaded', async () => {
  try {
    console.log('DOM loaded, initializing 3D designer...');
    const canvas = document.getElementById('renderCanvas');
    if (!canvas) {
      throw new Error('Canvas element not found');
    }
    console.log('Canvas found, creating designer...');
    const designer = new Designer({ canvas });
    window.__designer = designer;
    console.log('Designer created, initializing...');
    await designer.init();
    console.log('Designer initialized successfully');
  } catch (err) {
    console.error('Failed to initialize designer:', err);
    alert('Error initializing 3D designer: ' + err.message);
  }
});
