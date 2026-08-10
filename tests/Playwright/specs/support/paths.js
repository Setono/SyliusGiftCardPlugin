const path = require('node:path');

/**
 * Absolute so the setup project and the admin project agree on the file regardless of the working
 * directory Playwright happens to be invoked from.
 */
const ADMIN_STORAGE_STATE = path.join(__dirname, '..', '..', '.auth', 'admin.json');

module.exports = { ADMIN_STORAGE_STATE };
