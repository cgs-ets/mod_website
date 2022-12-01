const { defineConfig } = require("cypress");

module.exports = defineConfig({
  e2e: {
    setupNodeEvents(on, config) {
    },
    baseUrl: 'http://moodle4.local', // Local instance
    experimentalSessionAndOrigin: true, // Allow login remember
    watchForFileChanges: false, // Do not auto re-run tests every time code changes.
    env: {
      username: 'admin',
      password: 'Password1!',
    }
  },
});
