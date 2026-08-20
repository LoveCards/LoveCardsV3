import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './app/App.jsx';
import { AppProviders } from './app/providers.jsx';
import { applyThemeRuntimeConfig } from './app/runtime-context.js';
import './styles/index.css';

applyThemeRuntimeConfig();

createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <AppProviders>
      <App />
    </AppProviders>
  </React.StrictMode>,
);
