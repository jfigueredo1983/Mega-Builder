// Throwaway credentials of the disposable Docker store (tests/e2e/docker-compose.yml).
// Override with env vars to point the suite at another test store.
export const E2E = {
  baseURL: process.env.MB_E2E_BASE_URL || 'http://localhost:8080',
  adminPath: process.env.MB_E2E_ADMIN_PATH || '/admin-dev/',
  email: process.env.MB_E2E_ADMIN_EMAIL || 'admin@megabuilder.test',
  password: process.env.MB_E2E_ADMIN_PASSWORD || 'MbE2eTestOnly!2026',
};

export const VIEWPORTS = [
  { name: 'desktop', width: 1440, height: 900 },
  { name: 'tablet', width: 768, height: 1024 },
  { name: 'mobile', width: 390, height: 844 },
] as const;
