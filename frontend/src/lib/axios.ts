import axios from 'axios';

const axiosInstance = axios.create({
    baseURL: import.meta.env.VITE_API_URL || 'http://localhost:18787/api',
    headers: {
        'Content-Type': 'application/json',
    },
});

// Add JWT token to all requests
axiosInstance.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('munney_jwt_token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// Handle 401 responses (token expired or invalid)
axiosInstance.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            // Only clear token if it exists (avoid infinite loops)
            const hadToken = localStorage.getItem('munney_jwt_token');
            if (hadToken) {
                localStorage.removeItem('munney_jwt_token');
                localStorage.removeItem('munney_user');
                // Reload page to show login screen (only if we had a token)
                window.location.href = '/';
            }
        }
        return Promise.reject(error);
    }
);

export default axiosInstance;
