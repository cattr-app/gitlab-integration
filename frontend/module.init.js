export const ModuleConfig = {
    enabled: true,
    routerPrefix: 'settings',
    loadOrder: 30,
    moduleName: 'AmazingCat_GitlabIntegration',
};

export function init(context, router) {
    const requireSection = require.context('.', true, /^(?!.*(service|module)).*\.js$/);
    const sections = requireSection
        .keys()
        .map(fn => requireSection(fn).default)
        .map(section => {
            if (typeof section === 'function') {
                return section(context, router);
            }
            return section;
        });

    sections.forEach(section => {
        if (section.hasOwnProperty('scope') && section.scope === 'company') {
            context.addCompanySection(section);
        } else {
            context.addSettingsSection(section);
        }
    });

    context.addLocalizationData({
        en: require('./locales/en'),
        ru: require('./locales/ru'),
    });

    return context;
}
