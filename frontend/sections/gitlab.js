import UserSettingsService from '../services/userSettingsService';
import i18n from '@/i18n';

export default {
    // Check if this section can be rendered and accessed, this param IS OPTIONAL (true by default)
    // NOTICE: this route will not be added to VueRouter AT ALL if this check fails
    // MUST be a function that returns a boolean
    accessCheck: () => true,

    order: 20,

    route: {
        // After processing this route will be named as 'settings.exampleSection'
        name: 'AmazingCat_GitlabIntegration.settings.gitlab',

        // After processing this route can be accessed via URL 'settings/example'
        path: 'gitlab',

        meta: {
            // After render, this section will be labeled as 'Example Section'
            label: 'settings.gitlab.label',

            // Service class to gather the data from API, should be an instance of Resource class
            service: new UserSettingsService(),

            // Renderable fields array
            fields: [
                {
                    displayable: $store => {
                        const gitlabSection = $store.getters['settings/sections'].find(
                            section => section.pathName === 'AmazingCat_GitlabIntegration.settings.gitlab',
                        );
                        return !gitlabSection.data.enabled;
                    },
                    render: h => {
                        return h('at-alert', {
                            props: {
                                message: i18n.t('settings.gitlab.message.integration_disabled'),
                                type: 'warning',
                                showIcon: true,
                            },
                        });
                    },
                },
                {
                    label: 'settings.gitlab.api_key',
                    key: 'api_key',
                    fieldOptions: {
                        type: 'text',
                        placeholder: 'settings.gitlab.api_key',
                        disableAutocomplete: false,
                    },
                },
            ],
        },
    },
};
