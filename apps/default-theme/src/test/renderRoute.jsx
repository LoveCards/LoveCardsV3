import { renderToStaticMarkup } from 'react-dom/server';
import { MemoryRouter } from 'react-router-dom';
import { AppProviders } from '../app/providers.jsx';
import { EagerAppRoutes } from '../app/router-eager.test.jsx';

export function renderRoute(path, initialMode = 'light') {
  return renderToStaticMarkup(
    <AppProviders initialMode={initialMode}>
      <MemoryRouter initialEntries={[path]}>
        <EagerAppRoutes />
      </MemoryRouter>
    </AppProviders>,
  );
}
