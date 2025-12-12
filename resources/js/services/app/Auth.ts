import API from './API';

export default {
    async getUser() {
        const response = await API.get('/v1/user');
        return response;
    },
}
