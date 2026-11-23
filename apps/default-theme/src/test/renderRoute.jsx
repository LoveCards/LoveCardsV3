import { renderToStaticMarkup } from 'react-dom/server';
import { MemoryRouter } from 'react-router-dom';
import { AppProviders } from '../app/providers.jsx';
import { EagerAppRoutes } from '../app/router-eager.test.jsx';
import { NavigationLoginProvider } from '../app/NavigationLoginProvider.jsx';

export function renderRoute(path, initialMode = 'light') {
  return renderToStaticMarkup(
    <AppProviders initialMode={initialMode}>
      <MemoryRouter initialEntries={[path]}>
        <NavigationLoginProvider><EagerAppRoutes /></NavigationLoginProvider>
      </MemoryRouter>
    </AppProviders>,
  );
}
