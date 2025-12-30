import API from './API';

export default {
    async getUserPolicyStatus() {
        const response = await API.get('/v1/user-status');
        return response;
    },
    async updateUserPolicy() {
        const response = await API.post('/v1/policy-agreement');
        return response;
    },
}
