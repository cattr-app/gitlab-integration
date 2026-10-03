import axios from '@/config/app';

/**
 * Section service class.
 * Used to fetch data from api for inside DynamicSettings.vue
 * Data is stored inside store -> settings -> sections -> data
 */
export default class UserSettingsService {
    /**
     * API endpoint URL
     * @returns string
     */
    getItemRequestUri() {
        return `integration/gitlab/user-settings`;
    }

    /**
     * Fetch item data from api endpoint
     * @returns {data}
     */
    async getAll() {
        const { data } = await axios.get(this.getItemRequestUri(), { cancelIgnore: true });
        return data.data;
    }

    /**
     * Save item data
     * @param payload
     * @returns {Promise<void>}
     */
    async save(payload) {
        const { data } = await axios.patch(this.getItemRequestUri(), payload);
        return data;
    }
}
