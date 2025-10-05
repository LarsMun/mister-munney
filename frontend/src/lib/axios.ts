// src/lib/axios.ts
import axios from 'axios';

export const api = axios.create({
    baseURL: 'http://localhost:8686/api', // 🔁 pas aan indien nodig
});