<template>
  <div>
    <nav style="background-color: #2c3e50; padding: 10px 20px; display: flex; align-items: center; gap: 20px;">
      <span style="color: white; font-weight: bold; font-size: 18px;">SchoolAide</span>

      <template v-if="isLoggedIn">
        <router-link to="/dashboard" style="color: #ecf0f1; text-decoration: none;">Dashboard</router-link>
        <router-link to="/students" style="color: #ecf0f1; text-decoration: none;">Students</router-link>
        <router-link to="/service-requests" style="color: #ecf0f1; text-decoration: none;">Service Requests</router-link>
        <router-link to="/imports" style="color: #ecf0f1; text-decoration: none;">Imports</router-link>
        <span style="margin-left: auto; color: #bdc3c7; font-size: 14px;">{{ userName }}</span>
        <button @click="logout" style="background: #e74c3c; color: white; border: none; padding: 6px 14px; cursor: pointer;">
          Logout
        </button>
      </template>
    </nav>

    <div v-if="sessionExpired" style="background: #f8d7da; border-bottom: 1px solid #f5c6cb; padding: 12px 20px; text-align: center; color: #721c24;">
      Your session has expired.
      <router-link to="/login" @click="sessionExpired = false" style="color: #721c24; font-weight: bold; margin-left: 8px;">Log in again</router-link>
    </div>

    <div style="padding: 20px; max-width: 1100px; margin: 0 auto;">
      <router-view />
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      isLoggedIn: !!localStorage.getItem('token'),
      userName: this.resolveUserName(),
      sessionExpired: false
    }
  },
  mounted() {
    window.addEventListener('auth:logout', this.handleSessionExpired)
  },
  beforeUnmount() {
    window.removeEventListener('auth:logout', this.handleSessionExpired)
  },
  methods: {
    resolveUserName() {
      try {
        const user = localStorage.getItem('user')
        return user ? JSON.parse(user).name : ''
      } catch {
        return ''
      }
    },
    handleSessionExpired() {
      this.isLoggedIn = false
      this.userName = ''
      this.sessionExpired = true
    },
    logout() {
      localStorage.removeItem('token')
      localStorage.removeItem('tenant')
      localStorage.removeItem('user')
      this.isLoggedIn = false
      this.userName = ''
      this.$router.push('/login')
    }
  }
}
</script>
