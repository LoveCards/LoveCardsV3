const boot = document.getElementById('lc-bootstrap');
window.__fixtureBootstrap = boot ? JSON.parse(boot.textContent || '{}') : null;
