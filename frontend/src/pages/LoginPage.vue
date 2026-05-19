<template>
  <div style="max-width: 380px; margin: 80px auto; border: 1px solid #ccc; padding: 30px; background: #fafafa;">
    <h2 style="margin-top: 0; text-align: center;">Login</h2>

    <div v-if="errorMsg" style="background: #ffe0e0; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 15px; color: #721c24;">
      {{ errorMsg }}
    </div>

    <div style="margin-bottom: 12px;">
      <label>School Slug <span style="color:red">*</span></label><br>
      <input
        v-model="form.tenant"
        type="text"
        placeholder="e.g. greenfield-high"
        style="width: 100%; padding: 8px; box-sizing: border-box; margin-top: 4px; border: 1px solid #ccc;"
      />
    </div>

    <div style="margin-bottom: 12px;">
      <label>Email <span style="color:red">*</span></label><br>
      <input
        v-model="form.email"
        type="email"
        placeholder="admin@example.com"
        style="width: 100%; padding: 8px; box-sizing: border-box; margin-top: 4px; border: 1px solid #ccc;"
      />
    </div>

    <div style="margin-bottom: 20px;">
      <label>Password <span style="color:red">*</span></label><br>
      <input
        v-model="form.password"
        type="password"
        style="width: 100%; padding: 8px; box-sizing: border-box; margin-top: 4px; border: 1px solid #ccc;"
      />
    </div>

    <button
      @click="login"
      :disabled="loading"
      style="width: 100%; padding: 10px; background-color: #2c3e50; color: white; border: none; cursor: pointer; font-size: 15px;"
    >
      {{ loading ? 'Logging in...' : 'Login' }}
    </button>
  </div>
</template>

<script>
import api from '../services/api.js'

export default {
  data() {
    return {
      form: {
        tenant: '',
        email: '',
        password: ''
      },
      errorMsg: '',
      loading: false
    }
  },
  methods: {
    async login() {
      this.errorMsg = ''

      if (!this.form.tenant || !this.form.email || !this.form.password) {
        this.errorMsg = 'All fields are required.'
        return
      }

      this.loading = true

      // set tenant before calling api so the interceptor picks it up
      localStorage.setItem('tenant', this.form.tenant)

      try {
        const res = await api.post('/auth/login', {
          email: this.form.email,
          password: this.form.password
        })

        console.log('login response:', res.data)

        const token = res.data?.data?.token
        const user = res.data?.data?.user

        if (!token) {
          this.errorMsg = 'Login failed. No token received.'
          localStorage.removeItem('tenant')
          return
        }

        localStorage.setItem('token', token)
        localStorage.setItem('user', JSON.stringify(user))

        this.$router.push('/dashboard')
      } catch (err) {
        console.log('login error:', err)
        localStorage.removeItem('tenant')

        if (err.response?.data?.message) {
          this.errorMsg = err.response.data.message
        } else {
          this.errorMsg = 'Something went wrong. Please try again.'
        }
      } finally {
        this.loading = false
      }
    }
  }
}
</script>
