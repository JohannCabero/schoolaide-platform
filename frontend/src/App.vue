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

    <div style="padding: 20px; max-width: 1100px; margin: 0 auto;">
      <router-view />
    </div>
  </div>
</template>

<script>
export default {
  computed: {
    isLoggedIn() {
      return !!localStorage.getItem('token')
    },
    userName() {
      const user = localStorage.getItem('user')
      if (user) {
        return JSON.parse(user).name
      }
      return ''
    }
  },
  methods: {
    logout() {
      localStorage.removeItem('token')
      localStorage.removeItem('tenant')
      localStorage.removeItem('user')
      this.$router.push('/login')
    }
  }
}
</script>
