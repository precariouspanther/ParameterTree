//Unit tests have failed, show a toast.
const notifier = require('node-notifier');
const path = require('path');
notifier.notify({
    'title': 'Git commit rejected',
    'message': 'Your unit tests have failed! Please check and try again.'
});
