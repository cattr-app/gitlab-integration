import GitlabSettingsService from '../../services/gitlabSettingsService';
import { store } from '@/store';
import { hasRole } from '@/utils/user';

export default {
    // Check if this section can be rendered and accessed, this param IS OPTIONAL (true by default)
    // NOTICE: this route will not be added to VueRouter AT ALL if this check fails
    // MUST be a function that returns a boolean
    accessCheck: async () => hasRole(store.getters['user/user'], 'admin'),

    scope: 'company',

    order: 30,

    route: {
        // After processing this route will be named as 'settings.exampleSection'
        name: 'AmazingCat_GitlabIntegration.company.gitlab',

        // After processing this route can be accessed via URL 'settings/example'
        path: '/company/gitlab',

        order: 3,

        meta: {
            // After render, this section will be labeled as 'Example Section'
            label: 'settings.gitlab.label',

            // Service class to gather the data from API, should be an instance of Resource class
            service: new GitlabSettingsService(),

            // Renderable fields array
            fields: [
                {
                    label: 'settings.gitlab.enable',
                    key: 'enabled',
                    fieldOptions: {
                        type: 'checkbox',
                    },
                },
                {
                    label: 'settings.gitlab.url',
                    key: 'url',
                    fieldOptions: {
                        type: 'input',
                        placeholder: 'https://git.example.com',
                    },
                },
                {
                    label: 'settings.gitlab.period.label',
                    key: 'time_sync_period',
                    fieldOptions: {
                        type: 'select',
                        options: [
                            {
                                value: '0',
                                label: 'settings.gitlab.period.never',
                            },
                            {
                                value: '5',
                                label: 'settings.gitlab.period.five_minutes',
                            },
                            {
                                value: '30',
                                label: 'settings.gitlab.period.thirty_minutes',
                            },
                            {
                                value: '60',
                                label: 'settings.gitlab.period.hourly',
                            },
                            {
                                value: '1440',
                                label: 'settings.gitlab.period.daily',
                            },
                        ],
                    },
                },
            ],
        },
    },
};
