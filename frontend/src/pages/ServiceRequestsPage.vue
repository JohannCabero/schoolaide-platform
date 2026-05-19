<template>
  <div>
    <h2>Service Requests</h2>

    <!-- filter bar -->
    <div style="margin-bottom: 15px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
      <label>Status:</label>
      <select v-model="statusFilter" @change="fetchRequests()" style="padding: 7px; border: 1px solid #ccc;">
        <option value="">All</option>
        <option value="pending">Pending</option>
        <option value="approved">Approved</option>
        <option value="rejected">Rejected</option>
        <option value="cancelled">Cancelled</option>
      </select>

      <label style="margin-left: 10px;">From:</label>
      <input v-model="dateFrom" type="date" style="padding: 6px; border: 1px solid #ccc;" @change="fetchRequests()" />
      <label>To:</label>
      <input v-model="dateTo" type="date" style="padding: 6px; border: 1px solid #ccc;" @change="fetchRequests()" />

      <button @click="fetchRequests()" style="padding: 7px 14px; cursor: pointer;">Refresh</button>
    </div>

    <!-- Create new request button -->
    <div style="margin-bottom: 15px;">
      <button @click="showCreateForm = !showCreateForm" style="background: #27ae60; color: white; border: none; padding: 8px 16px; cursor: pointer;">
        {{ showCreateForm ? 'Cancel' : '+ New Request' }}
      </button>
    </div>

    <!-- Create form -->
    <div v-if="showCreateForm" style="border: 1px solid #ccc; padding: 15px; margin-bottom: 20px; background: #f9f9f9; max-width: 500px;">
      <h4 style="margin-top: 0;">Create Service Request</h4>

      <div v-if="createError" style="color: red; margin-bottom: 10px;">{{ createError }}</div>

      <div style="margin-bottom: 10px;">
        <label>Student ID <span style="color:red">*</span></label><br>
        <input v-model="createForm.student_id" type="text" style="width: 100%; padding: 7px; border: 1px solid #ccc; box-sizing: border-box;" />
        <small style="color: #666;">Enter the student's ID number</small>
      </div>
      <div style="margin-bottom: 10px;">
        <label>Service Type ID <span style="color:red">*</span></label><br>
        <input v-model="createForm.service_type_id" type="text" style="width: 100%; padding: 7px; border: 1px solid #ccc; box-sizing: border-box;" />
        <small style="color: #666;">Enter the service type ID</small>
      </div>
      <div style="margin-bottom: 10px;">
        <label>Requested Date <span style="color:red">*</span></label><br>
        <input v-model="createForm.requested_date" type="date" style="width: 100%; padding: 7px; border: 1px solid #ccc; box-sizing: border-box;" />
      </div>
      <div style="margin-bottom: 10px;">
        <label>Remarks</label><br>
        <textarea v-model="createForm.remarks" rows="2" style="width: 100%; padding: 7px; border: 1px solid #ccc; box-sizing: border-box;"></textarea>
      </div>
      <button @click="createRequest" :disabled="creating" style="background: #2c3e50; color: white; border: none; padding: 8px 18px; cursor: pointer;">
        {{ creating ? 'Submitting...' : 'Submit Request' }}
      </button>
    </div>

    <!-- error/loading -->
    <div v-if="loading" style="color: #666;">Loading requests...</div>
    <div v-if="errorMsg" style="color: red; margin-bottom: 10px;">{{ errorMsg }}</div>

    <!-- Approve/Reject panel -->
    <div v-if="actionRequest" style="border: 2px solid #2c3e50; padding: 15px; margin-bottom: 20px; background: #f0f4f8; max-width: 500px;">
      <h4 style="margin-top: 0;">{{ actionType === 'approve' ? 'Approve' : 'Reject' }} Request #{{ actionRequest.id }}</h4>
      <p style="margin: 5px 0; font-size: 14px;">Student: {{ actionRequest.student ? actionRequest.student.full_name : 'N/A' }}</p>
      <p style="margin: 5px 0; font-size: 14px;">Service: {{ actionRequest.service_type ? actionRequest.service_type.name : 'N/A' }}</p>

      <div style="margin-top: 10px;">
        <label>Notes:</label><br>
        <textarea v-model="actionNotes" rows="3" style="width: 100%; padding: 7px; border: 1px solid #ccc; box-sizing: border-box; margin-top: 5px;"></textarea>
      </div>
      <div v-if="actionError" style="color: red; margin-top: 8px;">{{ actionError }}</div>
      <div style="margin-top: 10px; display: flex; gap: 10px;">
        <button
          @click="submitAction"
          :disabled="submittingAction"
          :style="actionType === 'approve' ? 'background: #27ae60; color: white; border: none; padding: 8px 18px; cursor: pointer;' : 'background: #e74c3c; color: white; border: none; padding: 8px 18px; cursor: pointer;'"
        >
          {{ submittingAction ? 'Processing...' : (actionType === 'approve' ? 'Confirm Approve' : 'Confirm Reject') }}
        </button>
        <button @click="cancelAction" style="padding: 8px 18px; cursor: pointer;">Cancel</button>
      </div>
    </div>

    <!-- Table -->
    <div v-if="!loading">
      <table border="1" style="width: 100%; border-collapse: collapse; font-size: 14px;">
        <thead style="background: #ecf0f1;">
          <tr>
            <th style="padding: 8px; text-align: left;">ID</th>
            <th style="padding: 8px; text-align: left;">Student</th>
            <th style="padding: 8px; text-align: left;">Service Type</th>
            <th style="padding: 8px; text-align: left;">Requested Date</th>
            <th style="padding: 8px; text-align: left;">Status</th>
            <th style="padding: 8px; text-align: left;">Assigned To</th>
            <th style="padding: 8px; text-align: left;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="requests.length === 0">
            <td colspan="7" style="padding: 12px; text-align: center; color: #666;">No service requests found.</td>
          </tr>
          <tr v-for="req in requests" :key="req.id">
            <td style="padding: 8px;">#{{ req.id }}</td>
            <td style="padding: 8px;">
              {{ req.student ? req.student.full_name : 'N/A' }}<br>
              <small style="color: #666;">{{ req.student ? req.student.student_number : '' }}</small>
            </td>
            <td style="padding: 8px;">{{ req.service_type ? req.service_type.name : 'N/A' }}</td>
            <td style="padding: 8px;">{{ req.requested_date }}</td>
            <td style="padding: 8px;">
              <span :style="statusStyle(req.status)">{{ req.status }}</span>
            </td>
            <td style="padding: 8px;">{{ req.assigned_to ? req.assigned_to.name : '-' }}</td>
            <td style="padding: 8px;">
              <template v-if="req.status === 'pending'">
                <button
                  @click="openAction(req, 'approve')"
                  style="background: #27ae60; color: white; border: none; padding: 4px 10px; cursor: pointer; margin-right: 5px;"
                >
                  Approve
                </button>
                <button
                  @click="openAction(req, 'reject')"
                  style="background: #e74c3c; color: white; border: none; padding: 4px 10px; cursor: pointer;"
                >
                  Reject
                </button>
              </template>
              <span v-else style="color: #aaa; font-size: 13px;">—</span>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="meta.last_page > 1" style="margin-top: 15px; display: flex; gap: 8px; align-items: center;">
        <button :disabled="meta.current_page === 1" @click="changePage(meta.current_page - 1)" style="padding: 5px 10px; cursor: pointer;">Prev</button>
        <span>Page {{ meta.current_page }} of {{ meta.last_page }} &nbsp;({{ meta.total }} total)</span>
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
      requests: [],
      statusFilter: 'pending',
      dateFrom: '',
      dateTo: '',
      loading: false,
      errorMsg: '',
      meta: { current_page: 1, last_page: 1, total: 0 },

      // create form
      showCreateForm: false,
      creating: false,
      createError: '',
      createForm: {
        student_id: '',
        service_type_id: '',
        requested_date: '',
        remarks: ''
      },

      // approve / reject
      actionRequest: null,
      actionType: '',
      actionNotes: '',
      actionError: '',
      submittingAction: false
    }
  },
  created() {
    this.fetchRequests()
  },
  methods: {
    async fetchRequests(page = 1) {
      this.loading = true
      this.errorMsg = ''

      try {
        const params = { page }
        if (this.statusFilter) params.status = this.statusFilter
        if (this.dateFrom) params.date_from = this.dateFrom
        if (this.dateTo) params.date_to = this.dateTo

        const res = await api.get('/service-requests', { params })
        this.requests = res.data.data
        this.meta = res.data.meta
      } catch (err) {
        console.log('fetch requests error', err)
        this.errorMsg = err.response?.status === 401
          ? 'Session expired. Please log in again.'
          : 'Failed to load service requests.'
      } finally {
        this.loading = false
      }
    },
    changePage(page) {
      this.fetchRequests(page)
    },
    async createRequest() {
      this.createError = ''

      if (!this.createForm.student_id || !this.createForm.service_type_id || !this.createForm.requested_date) {
        this.createError = 'Student ID, Service Type ID, and Requested Date are required.'
        return
      }

      this.creating = true
      try {
        await api.post('/service-requests', this.createForm)
        alert('Service request created!')
        this.showCreateForm = false
        this.createForm = { student_id: '', service_type_id: '', requested_date: '', remarks: '' }
        this.fetchRequests()
      } catch (err) {
        console.log('create request error', err)
        this.createError = err.response?.data?.message || 'Failed to create request.'
      } finally {
        this.creating = false
      }
    },
    openAction(req, type) {
      this.actionRequest = req
      this.actionType = type
      this.actionNotes = ''
      this.actionError = ''
    },
    cancelAction() {
      this.actionRequest = null
      this.actionType = ''
      this.actionNotes = ''
      this.actionError = ''
    },
    async submitAction() {
      this.actionError = ''
      this.submittingAction = true

      try {
        const endpoint = '/service-requests/' + this.actionRequest.id + '/' + this.actionType
        await api.post(endpoint, {
          notes: this.actionNotes,
          version: this.actionRequest.version
        })

        alert('Request ' + this.actionType + 'd successfully!')
        this.cancelAction()
        this.fetchRequests(this.meta.current_page)
      } catch (err) {
        console.log('action error', err)
        if (err.response?.status === 409) {
          this.actionError = 'This request was already modified by someone else. Please refresh.'
        } else if (err.response?.status === 422) {
          this.actionError = err.response.data.message || 'Invalid state. Request may have already been processed.'
        } else {
          this.actionError = err.response?.data?.message || 'Failed to process the action.'
        }
      } finally {
        this.submittingAction = false
      }
    },
    statusStyle(status) {
      const map = {
        pending: 'background: #fff3cd; color: #856404; padding: 2px 8px; border-radius: 3px;',
        approved: 'background: #d4edda; color: #155724; padding: 2px 8px; border-radius: 3px;',
        rejected: 'background: #f8d7da; color: #721c24; padding: 2px 8px; border-radius: 3px;',
        cancelled: 'background: #e2e3e5; color: #383d41; padding: 2px 8px; border-radius: 3px;'
      }
      return map[status] || ''
    }
  }
}
</script>
