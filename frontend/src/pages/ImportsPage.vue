<template>
  <div>
    <h2>Import Service Requests</h2>

    <!-- Upload form -->
    <div style="border: 1px solid #ccc; padding: 20px; max-width: 480px; background: #f9f9f9; margin-bottom: 30px;">
      <h4 style="margin-top: 0;">Upload Excel File (.xlsx)</h4>
      <p style="font-size: 13px; color: #555; margin-top: 0;">
        The file must have these columns: <strong>Student Number</strong>, <strong>Service Type</strong>, <strong>Requested Date</strong>
      </p>

      <div v-if="uploadSuccess" style="background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin-bottom: 12px; color: #155724;">
        {{ uploadSuccess }}
      </div>
      <div v-if="uploadError" style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 12px; color: #721c24;">
        {{ uploadError }}
      </div>

      <div style="margin-bottom: 15px;">
        <input type="file" ref="fileInput" accept=".xlsx,.xls" @change="onFileChange" />
        <div v-if="selectedFile" style="margin-top: 6px; font-size: 13px; color: #555;">
          Selected: {{ selectedFile.name }} ({{ fileSizeKb }} KB)
        </div>
      </div>

      <button
        @click="uploadFile"
        :disabled="!selectedFile || uploading"
        style="background: #2c3e50; color: white; border: none; padding: 9px 20px; cursor: pointer;"
      >
        {{ uploading ? 'Uploading...' : 'Upload & Process' }}
      </button>
    </div>

    <!-- Import history -->
    <h3>Import History</h3>
    <div v-if="loadingHistory" style="color: #666;">Loading history...</div>
    <div v-if="historyError" style="color: red;">{{ historyError }}</div>

    <div v-if="!loadingHistory">
      <table border="1" style="width: 100%; border-collapse: collapse; font-size: 14px;">
        <thead style="background: #ecf0f1;">
          <tr>
            <th style="padding: 8px; text-align: left;">ID</th>
            <th style="padding: 8px; text-align: left;">Filename</th>
            <th style="padding: 8px; text-align: left;">Status</th>
            <th style="padding: 8px; text-align: left;">Total</th>
            <th style="padding: 8px; text-align: left;">Success</th>
            <th style="padding: 8px; text-align: left;">Skipped</th>
            <th style="padding: 8px; text-align: left;">Started At</th>
            <th style="padding: 8px; text-align: left;">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="imports.length === 0">
            <td colspan="8" style="padding: 12px; text-align: center; color: #666;">No imports yet.</td>
          </tr>
          <tr v-for="imp in imports" :key="imp.id">
            <td style="padding: 8px;">#{{ imp.id }}</td>
            <td style="padding: 8px;">{{ imp.original_filename || imp.filename }}</td>
            <td style="padding: 8px;">
              <span :style="statusStyle(imp.status)">{{ imp.status }}</span>
            </td>
            <td style="padding: 8px;">{{ imp.total_rows ?? '-' }}</td>
            <td style="padding: 8px; color: #27ae60;">{{ imp.successful_rows ?? '-' }}</td>
            <td style="padding: 8px; color: #e74c3c;">{{ imp.skipped_rows ?? '-' }}</td>
            <td style="padding: 8px; font-size: 13px;">{{ imp.started_at || '-' }}</td>
            <td style="padding: 8px;">
              <button @click="viewDetails(imp)" style="padding: 4px 10px; cursor: pointer;">Details</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="meta.last_page > 1" style="margin-top: 15px; display: flex; gap: 8px; align-items: center;">
        <button :disabled="meta.current_page === 1" @click="changePage(meta.current_page - 1)" style="padding: 5px 10px; cursor: pointer;">Prev</button>
        <span>Page {{ meta.current_page }} of {{ meta.last_page }}</span>
        <button :disabled="meta.current_page === meta.last_page" @click="changePage(meta.current_page + 1)" style="padding: 5px 10px; cursor: pointer;">Next</button>
      </div>
    </div>

    <!-- Detail modal (simple inline) -->
    <div v-if="detailImport" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 999;">
      <div style="background: white; padding: 25px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; border-radius: 4px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
          <h3 style="margin: 0;">Import #{{ detailImport.id }} Details</h3>
          <button @click="detailImport = null" style="background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
        </div>

        <p style="margin: 5px 0;"><strong>File:</strong> {{ detailImport.original_filename }}</p>
        <p style="margin: 5px 0;"><strong>Status:</strong> {{ detailImport.status }}</p>
        <p style="margin: 5px 0;"><strong>Total rows:</strong> {{ detailImport.total_rows }}</p>
        <p style="margin: 5px 0;"><strong>Successful:</strong> {{ detailImport.successful_rows }}</p>
        <p style="margin: 5px 0;"><strong>Skipped:</strong> {{ detailImport.skipped_rows }}</p>

        <div v-if="detailImport.summary_json && detailImport.summary_json.length > 0" style="margin-top: 15px;">
          <strong>Skipped rows:</strong>
          <table border="1" style="width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 8px;">
            <thead style="background: #ecf0f1;">
              <tr>
                <th style="padding: 6px;">Line</th>
                <th style="padding: 6px; text-align: left;">Reason</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, i) in detailImport.summary_json" :key="i">
                <td style="padding: 6px; text-align: center;">{{ item.line }}</td>
                <td style="padding: 6px;">{{ item.reason }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="!detailImport.summary_json || detailImport.summary_json.length === 0" style="margin-top: 15px; color: #666;">
          No skipped rows.
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import api from '../services/api.js'

export default {
  data() {
    return {
      // upload
      selectedFile: null,
      uploading: false,
      uploadSuccess: '',
      uploadError: '',

      // history
      imports: [],
      loadingHistory: false,
      historyError: '',
      meta: { current_page: 1, last_page: 1 },

      // detail view
      detailImport: null
    }
  },
  computed: {
    fileSizeKb() {
      if (!this.selectedFile) return 0
      return Math.round(this.selectedFile.size / 1024)
    }
  },
  created() {
    this.fetchHistory()
  },
  methods: {
    onFileChange(e) {
      this.selectedFile = e.target.files[0] || null
      this.uploadSuccess = ''
      this.uploadError = ''
    },
    async uploadFile() {
      if (!this.selectedFile) return

      this.uploadError = ''
      this.uploadSuccess = ''
      this.uploading = true

      const formData = new FormData()
      formData.append('file', this.selectedFile)

      try {
        const res = await api.post('/imports', formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        })

        console.log('upload response', res.data)
        this.uploadSuccess = 'File uploaded! It is now being processed in the background. Refresh the history below to check the status.'
        this.selectedFile = null
        this.$refs.fileInput.value = ''
        this.fetchHistory()
      } catch (err) {
        console.log('upload error', err)
        if (err.response?.status === 422) {
          this.uploadError = err.response.data.message || 'Invalid file.'
        } else if (err.response?.status === 403) {
          this.uploadError = 'You are not allowed to upload files.'
        } else {
          this.uploadError = 'Upload failed. Please try again.'
        }
      } finally {
        this.uploading = false
      }
    },
    async fetchHistory(page = 1) {
      this.loadingHistory = true
      this.historyError = ''

      try {
        const res = await api.get('/imports', { params: { page } })
        this.imports = res.data.data
        this.meta = res.data.meta
      } catch (err) {
        console.log('fetch imports error', err)
        this.historyError = 'Failed to load import history.'
      } finally {
        this.loadingHistory = false
      }
    },
    changePage(page) {
      this.fetchHistory(page)
    },
    async viewDetails(imp) {
      // fetch fresh details
      try {
        const res = await api.get('/imports/' + imp.id)
        this.detailImport = res.data.data
      } catch (err) {
        console.log('detail error', err)
        this.detailImport = imp
      }
    },
    statusStyle(status) {
      const map = {
        pending: 'background: #fff3cd; color: #856404; padding: 2px 8px; border-radius: 3px;',
        processing: 'background: #cce5ff; color: #004085; padding: 2px 8px; border-radius: 3px;',
        completed: 'background: #d4edda; color: #155724; padding: 2px 8px; border-radius: 3px;',
        failed: 'background: #f8d7da; color: #721c24; padding: 2px 8px; border-radius: 3px;'
      }
      return map[status] || ''
    }
  }
}
</script>
