import { apiRequest } from './api';

export async function fetchAdmins() {
    return await apiRequest('/users', {
        method: 'GET',
        auth: true
    });
}

export async function createAdmin(email, password) {
    return await apiRequest('/users', {
        method: 'POST',
        body: { email, password },
        auth: true
    });
}

export async function deleteAdmin(id) {
    return await apiRequest(`/users/${id}`, {
        method: 'DELETE',
        auth: true
    });
}
