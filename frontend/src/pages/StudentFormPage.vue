<template>
  <div style="max-width: 600px;">
    <h2>{{ isEditing ? 'Edit Student' : 'Add Student' }}</h2>

    <div v-if="loadingStudent" style="color: #666;">Loading...</div>

    <div v-if="successMsg" style="background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin-bottom: 15px; color: #155724;">
      {{ successMsg }}
    </div>

    <div v-if="errorMsg" style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 15px; color: #721c24;">
      {{ errorMsg }}
    </div>

    <!-- Validation errors from server -->
    <div v-if="validationErrors && Object.keys(validationErrors).length > 0" style="background: #f8d7da; padding: 10px; margin-bottom: 15px; border: 1px solid #f5c6cb;">
      <ul style="margin: 0; padding-left: 20px; color: #721c24;">
        <li v-for="(msgs, field) in validationErrors" :key="field">
          <strong>{{ field }}:</strong> {{ msgs[0] }}
        </li>
      </ul>
    </div>

    <div v-if="!loadingStudent">
      <table style="width: 100%; border-collapse: collapse;">
        <tr>
          <td style="padding: 8px 0; width: 160px; vertical-align: top; padding-top: 12px;">
            <label>Student Number <span style="color:red">*</span></label>
          </td>
          <td style="padding: 8px 0;">
            <input v-model="form.student_number" type="text" style="width: 100%; padding: 8px; border: 1px solid #ccc; box-sizing: border-box;" />
          </td>
        </tr>
        <tr>
          <td style="padding: 8px 0; vertical-align: top; padding-top: 12px;">
            <label>First Name <span style="color:red">*</span></label>
          </td>
          <td style="padding: 8px 0;">
            <input v-model="form.first_name" type="text" style="width: 100%; padding: 8px; border: 1px solid #ccc; box-sizing: border-box;" />
          </td>
        </tr>
        <tr>
          <td style="padding: 8px 0; vertical-align: top; padding-top: 12px;">
            <label>Last Name <span style="color:red">*</span></label>
          </td>
          <td style="padding: 8px 0;">
            <input v-model="form.last_name" type="text" style="width: 100%; padding: 8px; border: 1px solid #ccc; box-sizing: border-box;" />
          </td>
        </tr>
        <tr>
          <td style="padding: 8px 0; vertical-align: top; padding-top: 12px;">
            <label>Email <span style="color:red">*</span></label>
          </td>
          <td style="padding: 8px 0;">
            <input v-model="form.email" type="email" style="width: 100%; padding: 8px; border: 1px solid #ccc; box-sizing: border-box;" />
          </td>
        </tr>
        <tr>
          <td style="padding: 8px 0; vertical-align: top; padding-top: 12px;">
            <label>Phone</label>
          </td>
          <td style="padding: 8px 0;">
            <input v-model="form.phone" type="text" placeholder="09XXXXXXXXX" style="width: 100%; padding: 8px; border: 1px solid #ccc; box-sizing: border-box;" />
          </td>
        </tr>
        <tr>
          <td style="padding: 8px 0; vertical-align: top; padding-top: 12px;">
            <label>Birth Date</label>
          </td>
          <td style="padding: 8px 0;">
            <input v-model="form.birth_date" type="date" style="width: 100%; padding: 8px; border: 1px solid #ccc; box-sizing: border-box;" />
          </td>
        </tr>
        <tr>
          <td style="padding: 8px 0; vertical-align: top; padding-top: 12px;">
            <label>Program</label>
          </td>
          <td style="padding: 8px 0;">
            <input v-model="form.program" type="text" placeholder="e.g. BS Computer Science" style="width: 100%; padding: 8px; border: 1px solid #ccc; box-sizing: border-box;" />
          </td>
        </tr>
        <tr>
          <td style="padding: 8px 0; vertical-align: top; padding-top: 12px;">
            <label>Year Level</label>
          </td>
          <td style="padding: 8px 0;">
            <input v-model="form.year_level" type="text" min="1" max="6" style="width: 100%; padding: 8px; border: 1px solid #ccc; box-sizing: border-box;" />
          </td>
        </tr>
        <tr>
          <td style="padding: 8px 0; vertical-align: top; padding-top: 12px;">
            <label>Status <span style="color:red">*</span></label>
          </td>
          <td style="padding: 8px 0;">
            <select v-model="form.status" style="width: 100%; padding: 8px; border: 1px solid #ccc;">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="graduated">Graduated</option>
              <option value="suspended">Suspended</option>
            </select>
          </td>
        </tr>
      </table>

      <div style="margin-top: 20px; display: flex; gap: 10px;">
        <button
          @click="save"
          :disabled="saving"
          style="background: #2c3e50; color: white; border: none; padding: 9px 20px; cursor: pointer;"
        >
          {{ saving ? 'Saving...' : (isEditing ? 'Update Student' : 'Create Student') }}
        </button>
        <button @click="$router.push('/students')" style="padding: 9px 20px; cursor: pointer;">
          Cancel
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import api from '../services/api.js'

export default {
  data() {
    return {
      form: {
        student_number: '',
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        birth_date: '',
        program: '',
        year_level: '',
        status: 'active'
      },
      isEditing: false,
      loadingStudent: false,
      saving: false,
      successMsg: '',
      errorMsg: '',
      validationErrors: {}
    }
  },
  created() {
    if (this.$route.params.id) {
      this.isEditing = true
      this.loadStudent(this.$route.params.id)
    }
  },
  methods: {
    async loadStudent(id) {
      this.loadingStudent = true
      try {
        const res = await api.get('/students/' + id)
        const s = res.data.data
        this.form.student_number = s.student_number
        this.form.first_name = s.first_name
        this.form.last_name = s.last_name
        this.form.email = s.email
        this.form.phone = s.phone || ''
        this.form.birth_date = s.birth_date || ''
        this.form.program = s.program || ''
        this.form.year_level = s.year_level || ''
        this.form.status = s.status
      } catch (err) {
        console.log('load student error', err)
        this.errorMsg = 'Could not load student data.'
      } finally {
        this.loadingStudent = false
      }
    },
    async save() {
      this.errorMsg = ''
      this.successMsg = ''
      this.validationErrors = {}

      if (!this.form.student_number || !this.form.first_name || !this.form.last_name || !this.form.email) {
        this.errorMsg = 'Student number, first name, last name, and email are required.'
        return
      }

      this.saving = true

      try {
        if (this.isEditing) {
          await api.patch('/students/' + this.$route.params.id, this.form)
          this.successMsg = 'Student updated successfully!'
        } else {
          await api.post('/students', this.form)
          this.successMsg = 'Student created successfully!'
          this.resetForm()
        }
      } catch (err) {
        console.log('save student error', err)
        if (err.response?.status === 422) {
          this.validationErrors = err.response.data.errors || {}
          this.errorMsg = err.response.data.message || 'Validation failed.'
        } else {
          this.errorMsg = err.response?.data?.message || 'Failed to save student.'
        }
      } finally {
        this.saving = false
      }
    },
    resetForm() {
      this.form = {
        student_number: '',
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        birth_date: '',
        program: '',
        year_level: '',
        status: 'active'
      }
    }
  }
}
</script>
