<template>
  <div>
    <div style="display: flex; justify-content: space-between; align-items: center;">
      <h2 style="margin: 0;">Students</h2>
      <router-link to="/students/add">
        <button style="background: #27ae60; color: white; border: none; padding: 8px 16px; cursor: pointer;">
          + Add Student
        </button>
      </router-link>
    </div>

    <!-- Search and filter -->
    <div style="margin: 15px 0; display: flex; gap: 10px; flex-wrap: wrap;">
      <input
        v-model="search"
        type="text"
        placeholder="Search by name, email, student number..."
        style="padding: 7px; border: 1px solid #ccc; width: 280px;"
        @keyup.enter="fetchStudents"
      />
      <select v-model="statusFilter" style="padding: 7px; border: 1px solid #ccc;">
        <option value="">All Statuses</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
        <option value="graduated">Graduated</option>
        <option value="suspended">Suspended</option>
      </select>
      <button @click="fetchStudents" style="padding: 7px 14px; cursor: pointer;">Search</button>
    </div>

    <!-- Loading -->
    <div v-if="loading" style="color: #666;">Loading students...</div>

    <!-- Error -->
    <div v-if="errorMsg" style="color: red; margin-bottom: 10px;">{{ errorMsg }}</div>

    <!-- Table -->
    <div v-if="!loading">
      <table border="1" style="width: 100%; border-collapse: collapse; font-size: 14px;">
        <thead style="background: #ecf0f1;">
          <tr>
            <th style="padding: 8px; text-align: left;">Student No.</th>
            <th style="padding: 8px; text-align: left;">Name</th>
            <th style="padding: 8px; text-align: left;">Email</th>
            <th style="padding: 8px; text-align: left;">Program</th>
            <th style="padding: 8px; text-align: left;">Status</th>
            <th style="padding: 8px; text-align: left;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="students.length === 0">
            <td colspan="6" style="padding: 12px; text-align: center; color: #666;">No students found.</td>
          </tr>
          <tr v-for="student in students" :key="student.id">
            <td style="padding: 8px;">{{ student.student_number }}</td>
            <td style="padding: 8px;">{{ student.first_name }} {{ student.last_name }}</td>
            <td style="padding: 8px;">{{ student.email }}</td>
            <td style="padding: 8px;">{{ student.program }}</td>
            <td style="padding: 8px;">
              <span :style="statusBadge(student.status)">{{ student.status }}</span>
            </td>
            <td style="padding: 8px;">
              <router-link :to="'/students/' + student.id + '/edit'">
                <button style="margin-right: 5px; cursor: pointer;">Edit</button>
              </router-link>
              <button @click="deleteStudent(student)" style="background: #e74c3c; color: white; border: none; padding: 4px 10px; cursor: pointer;">
                Delete
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="meta.last_page > 1" style="margin-top: 15px; display: flex; gap: 8px; align-items: center;">
        <button :disabled="meta.current_page === 1" @click="changePage(meta.current_page - 1)" style="padding: 5px 10px; cursor: pointer;">Prev</button>
        <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
        <button :disabled="meta.current_page === meta.last_page" @click="changePage(meta.current_page + 1)" style="padding: 5px 10px; cursor: pointer;">Next</button>
      </div>
    </div>
  </div>
</template>

<script>
import api from '../services/api.js'

export default {
  data() {
    return {
      students: [],
      search: '',
      statusFilter: '',
      loading: false,
      errorMsg: '',
      meta: {
        current_page: 1,
        last_page: 1,
        total: 0,
        per_page: 15
      }
    }
  },
  created() {
    this.fetchStudents()
  },
  methods: {
    async fetchStudents(page = 1) {
      this.loading = true
      this.errorMsg = ''

      try {
        const params = { page }
        if (this.search) params.search = this.search
        if (this.statusFilter) params.status = this.statusFilter

        const res = await api.get('/students', { params })
        this.students = res.data.data
        this.meta = res.data.meta
      } catch (err) {
        console.log('fetch students error', err)
        this.errorMsg = err.response?.status === 401
          ? 'Session expired. Please log in again.'
          : 'Failed to load students.'
      } finally {
        this.loading = false
      }
    },
    changePage(page) {
      this.fetchStudents(page)
    },
    async deleteStudent(student) {
      const confirmed = confirm(`Are you sure you want to delete ${student.first_name} ${student.last_name}?`)
      if (!confirmed) return

      try {
        await api.delete('/students/' + student.id)
        alert('Student deleted.')
        this.fetchStudents(this.meta.current_page)
      } catch (err) {
        console.log('delete error', err)
        alert('Failed to delete student.')
      }
    },
    statusBadge(status) {
      const colors = {
        active: 'background: #d4edda; color: #155724; padding: 2px 8px; border-radius: 3px;',
        inactive: 'background: #f8d7da; color: #721c24; padding: 2px 8px; border-radius: 3px;',
        graduated: 'background: #cce5ff; color: #004085; padding: 2px 8px; border-radius: 3px;',
        suspended: 'background: #fff3cd; color: #856404; padding: 2px 8px; border-radius: 3px;'
      }
      return colors[status] || ''
    }
  }
}
</script>
