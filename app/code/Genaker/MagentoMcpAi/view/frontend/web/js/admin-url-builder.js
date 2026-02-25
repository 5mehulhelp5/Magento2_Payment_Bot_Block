define(['jquery', 'mage/url'], function($, urlBuilder) {
    'use strict';

    return {
        /**
         * Generate admin URL with security key
         * @param {string} route - The admin route (e.g., 'system_config/edit/section/yotpo')
         * @param {Object} [params] - Optional query parameters
         * @returns {string} - The generated admin URL
         */
        getAdminUrl: function(route, params = {}) {
            // Ensure we're using the adminhtml area
            if (!route.startsWith('adminhtml/')) {
                route = 'adminhtml/' + route;
            }

            // Add security key parameter
            params['form_key'] = window.FORM_KEY;

            // Build the URL
            return urlBuilder.build(route, params);
        }
    };
});
