import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

// add token and tenant to every request
api.interceptors.request.use(config => {
  const token = localStorage.getItem('token')
  const tenant = localStorage.getItem('tenant')

  if (token) {
    config.headers['Authorization'] = 'Bearer ' + token
  }

  if (tenant) {
    config.headers['X-Tenant'] = tenant
  }

  return config
})

// if 401 redirect to login
api.interceptors.response.use(
  response => response,
  error => {
    if (error.response && error.response.status === 401) {
      localStorage.removeItem('token')
      localStorage.removeItem('tenant')
      localStorage.removeItem('user')
      window.location.href = '/login'
    }
    return Promise.reject(error)
  }
)

export default api
