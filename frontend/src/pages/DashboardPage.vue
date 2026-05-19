<template>
  <div>
    <h2>Dashboard</h2>
    <p>Welcome back, <strong>{{ user.name }}</strong>!</p>
    <p style="color: #666;">Tenant: {{ tenant }} &nbsp;|&nbsp; Role: {{ userRole }}</p>

    <hr style="margin: 20px 0;" />

    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
      <div style="border: 1px solid #ccc; padding: 20px; min-width: 160px; text-align: center; background: #f9f9f9;">
        <h3 style="margin-top: 0;">Students</h3>
        <router-link to="/students">Go to Students</router-link>
      </div>

      <div style="border: 1px solid #ccc; padding: 20px; min-width: 160px; text-align: center; background: #f9f9f9;">
        <h3 style="margin-top: 0;">Service Requests</h3>
        <router-link to="/service-requests">View Requests</router-link>
      </div>

      <div style="border: 1px solid #ccc; padding: 20px; min-width: 160px; text-align: center; background: #f9f9f9;">
        <h3 style="margin-top: 0;">Imports</h3>
        <router-link to="/imports">Upload Excel</router-link>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      user: { name: '' },
      tenant: '',
      userRole: ''
    }
  },
  created() {
    const userStr = localStorage.getItem('user')
    if (userStr) {
      this.user = JSON.parse(userStr)
      // roles can be an array
      if (this.user.roles && this.user.roles.length > 0) {
        this.userRole = this.user.roles[0]
      }
    }
    this.tenant = localStorage.getItem('tenant') || ''
  }
}
</script>
