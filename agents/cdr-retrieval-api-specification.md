# CDR Retrieval API System Specification

## 🎯 **Overview**

This specification details the implementation of a comprehensive CDR (Call Detail Record) retrieval and management system for the AI Voice SaaS platform. The system provides advanced querying, filtering, and pagination capabilities for historical call records.

### **Key Features**
- **Dedicated CDR Storage**: Separate `cdr_logs` table for optimized CDR data management
- **Advanced Filtering**: Multi-parameter filtering (From, To, Date/Time Range, Disposition, Token)
- **Pagination Support**: Efficient pagination for large datasets
- **Complete CDR Data**: Full CDR payload storage with structured and raw data
- **Real-time Integration**: Seamless integration with existing webhook system
- **Performance Optimized**: Proper indexing and query optimization

### **Business Value**
- **Call Analytics**: Comprehensive historical call data for analysis and reporting
- **Troubleshooting**: Full CDR data for debugging call issues
- **Compliance**: Audit trail of all call activities with 7-year retention
- **Data Management**: 90-day active storage with automatic archival
- **Export Capabilities**: CSV export and historical file downloads
- **Quality Monitoring**: QoS data stored in metadata for call analysis

---

## 📋 **Implementation Plan**

### **Phase 1: Database Schema & Migration** ✅ [Completed]
**Objective**: Create dedicated CDR table with proper indexing and relationships

**Database Schema:**
```sql
CREATE TABLE cdr_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,

    -- Core CDR Fields
    call_id VARCHAR(255) NOT NULL,
    session_token VARCHAR(255),
    from_number VARCHAR(255),
    to_number VARCHAR(255),
    direction ENUM('inbound', 'outbound'),
    disposition ENUM('ANSWER', 'BUSY', 'CANCEL', 'FAILED', 'CONGESTION', 'NOANSWER'),

    -- Timing Fields
    start_time TIMESTAMP,
    answer_time TIMESTAMP,
    end_time TIMESTAMP,
    duration_seconds INT,
    billsec INT,

    -- Extended CDR Fields
    domain VARCHAR(255),
    subscriber VARCHAR(255),
    cx_trunk_id VARCHAR(255),
    application VARCHAR(255),
    route VARCHAR(255),
    vapp_server VARCHAR(255),

    -- QoS Data (stored in raw_cdr metadata)
    -- No separate financial fields required

    -- Complete Raw CDR (for debugging and future extensibility)
    raw_cdr JSON,

    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Performance Indexes
    INDEX idx_tenant_call_id (tenant_id, call_id),
    INDEX idx_tenant_disposition (tenant_id, disposition),
    INDEX idx_tenant_start_time (tenant_id, start_time),
    INDEX idx_tenant_from_to (tenant_id, from_number, to_number(10)),
    INDEX idx_session_token (session_token),
    INDEX idx_domain (domain),
    INDEX idx_created_at (created_at),

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);
```

**Migration Tasks:**
1. Create `cdr_logs` table migration with all columns and indexes
2. Add foreign key constraints
3. Ensure proper character set and collation for international data
4. Test migration rollback capability

**Files to Create/Modify:**
- `backend/database/migrations/2026_01_XX_XXXXXX_create_cdr_logs_table.php`
- `backend/app/Models/CdrLog.php` (new Eloquent model)

---

### **Phase 2: CdrController & API Endpoints** ✅ [Completed]
**Objective**: Create dedicated CDR controller with advanced filtering and pagination

**Controller Structure:**
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CdrLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CdrController extends Controller
{
    /**
     * Get paginated CDR records with filtering
     */
    public function index(Request $request): JsonResponse
    {
        // Validation, filtering, pagination logic
    }

    /**
     * Get single CDR record
     */
    public function show(string $id): JsonResponse
    {
        // Single record retrieval
    }

    /**
     * Store CDR record (for testing/manual entry)
     */
    public function store(Request $request): JsonResponse
    {
        // Manual CDR entry (optional)
    }
}
```

**API Endpoints:**
```php
// Protected routes (auth:sanctum required)
Route::prefix('cdr')->group(function () {
    Route::get('/', [CdrController::class, 'index']);
    Route::get('/{id}', [CdrController::class, 'show']);
    Route::post('/', [CdrController::class, 'store']); // Optional for testing
});
```

**Request Parameters:**
```php
// GET /api/cdr
[
    'from' => 'string',           // Filter by caller number
    'to' => 'string',             // Filter by destination number
    'disposition' => 'string',    // Filter by call disposition
    'token' => 'string',          // Filter by session token
    'start_date' => 'date',       // Date range start (Y-m-d)
    'end_date' => 'date',         // Date range end (Y-m-d)
    'start_time' => 'time',       // Time range start (H:i)
    'end_time' => 'time',         // Time range end (H:i)
    'page' => 'integer',          // Pagination page (default: 1)
    'per_page' => 'integer',      // Records per page (default: 50, max: 200)
    'sort_by' => 'string',        // Sort field (default: start_time)
    'sort_order' => 'string',     // Sort direction (asc|desc, default: desc)
]
```

**Validation Rules:**
```php
$validator = Validator::make($request->all(), [
    'from' => 'nullable|string|max:255',
    'to' => 'nullable|string|max:255',
    'disposition' => ['nullable', Rule::in(['ANSWER', 'BUSY', 'CANCEL', 'FAILED', 'CONGESTION', 'NOANSWER'])],
    'token' => 'nullable|string|max:255',
    'start_date' => 'nullable|date|before_or_equal:end_date',
    'end_date' => 'nullable|date|after_or_equal:start_date',
    'start_time' => 'nullable|date_format:H:i',
    'end_time' => 'nullable|date_format:H:i',
    'page' => 'nullable|integer|min:1',
    'per_page' => 'nullable|integer|min:1|max:200',
    'sort_by' => 'nullable|string|in:id,call_id,start_time,duration_seconds,disposition',
    'sort_order' => 'nullable|string|in:asc,desc',
]);
```

---

### **Phase 3: Webhook Integration & Data Mapping** 🔄 [In Progress]
**Objective**: Update CDR webhook processing to store in dedicated table

**Status Mapping Logic:**
```php
// Cloudonix disposition → Standardized disposition
$dispositionMap = [
    'CONNECTED' => 'ANSWER',
    'ANSWERED' => 'ANSWER',
    'ANSWER' => 'ANSWER',
    'BUSY' => 'BUSY',
    'CANCEL' => 'CANCEL',
    'FAILED' => 'FAILED',
    'CONGESTION' => 'CONGESTION',
    'NOANSWER' => 'NOANSWER',
    'NO ANSWER' => 'NOANSWER',
    // Additional mappings can be added as needed
];
```

**Data Mapping from Webhook:**
```php
$cdrData = [
    'tenant_id' => $tenant->id,
    'call_id' => $webhookData['call_id'],
    'session_token' => $webhookData['session']['token'] ?? null,
    'from_number' => $webhookData['from'],
    'to_number' => $webhookData['to'],
    'direction' => $webhookData['session']['direction'] ?? 'inbound',
    'disposition' => $dispositionMap[$webhookData['disposition']] ?? 'FAILED',
    'start_time' => isset($webhookData['session']['callStartTime'])
        ? \Carbon\Carbon::createFromTimestampMs($webhookData['session']['callStartTime'])
        : null,
    'answer_time' => isset($webhookData['session']['callAnswerTime'])
        ? \Carbon\Carbon::createFromTimestampMs($webhookData['session']['callAnswerTime'])
        : null,
    'end_time' => isset($webhookData['session']['callEndTime'])
        ? \Carbon\Carbon::createFromTimestampMs($webhookData['session']['callEndTime'])
        : null,
    'duration_seconds' => $webhookData['duration'] ?? null,
    'billsec' => $webhookData['billsec'] ?? null,
    'domain' => $webhookData['domain'] ?? null,
    'subscriber' => $webhookData['subscriber'] ?? null,
    'cx_trunk_id' => $webhookData['cx_trunk_id'] ?? null,
    'vapp_server' => $webhookData['vapp_server'] ?? $webhookData['session']['vappServer'] ?? null,
    'raw_cdr' => $request->all(), // Complete webhook payload (includes QoS data)
];
```

**Duplicate Prevention:**
```php
// Upsert logic to prevent duplicates
CdrLog::updateOrCreate(
    [
        'tenant_id' => $cdrData['tenant_id'],
        'call_id' => $cdrData['call_id']
    ],
    $cdrData
);
```

---

### **Phase 4: Frontend Call Logs Integration** ⏳ [Not Started]
**Objective**: Update Call Logs page to use real CDR API with advanced filtering

**UI Enhancements:**
- **Filter Panel**: Expandable filter section with all filter options
- **Date/Time Pickers**: Calendar widgets for date ranges, time inputs for time ranges
- **Pagination Controls**: Page navigation with configurable page sizes
- **Export Options**: CSV download capability and historical file downloads
- **Manual Refresh**: Sufficient for this use case (no auto-refresh needed)

**Filter UI Components:**
```jsx
// Advanced Filter Panel
<div className="bg-white p-4 rounded-lg border border-gray-200 mb-6">
  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
    {/* From Number Filter */}
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">
        From Number
      </label>
      <input
        type="text"
        className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        placeholder="Enter caller number"
      />
    </div>

    {/* To Number Filter */}
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">
        To Number
      </label>
      <input
        type="text"
        className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        placeholder="Enter destination"
      />
    </div>

    {/* Date Range */}
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">
        Date Range
      </label>
      <div className="flex space-x-2">
        <input
          type="date"
          className="flex-1 border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />
        <span className="text-gray-500 self-center">-</span>
        <input
          type="date"
          className="flex-1 border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />
      </div>
    </div>

    {/* Disposition Filter */}
    <div>
      <label className="block text-sm font-medium text-gray-700 mb-1">
        Disposition
      </label>
      <select className="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
        <option value="">All Dispositions</option>
        <option value="ANSWER">Answered</option>
        <option value="BUSY">Busy</option>
        <option value="CANCEL">Cancelled</option>
        <option value="FAILED">Failed</option>
        <option value="CONGESTION">Congestion</option>
        <option value="NOANSWER">No Answer</option>
      </select>
    </div>
  </div>

  {/* Filter Actions */}
  <div className="flex justify-between items-center mt-4 pt-4 border-t border-gray-200">
    <button className="text-sm text-gray-600 hover:text-gray-800">
      Clear Filters
    </button>
    <div className="flex space-x-3">
      <button className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
        Reset
      </button>
      <button className="px-4 py-2 bg-indigo-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-indigo-700">
        Apply Filters
      </button>
    </div>
  </div>
</div>
```

**Pagination Component:**
```jsx
<div className="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
  <div className="flex items-center justify-between">
    <div className="text-sm text-gray-700">
      Showing <span className="font-medium">{from}</span> to{' '}
      <span className="font-medium">{to}</span> of{' '}
      <span className="font-medium">{total}</span> results
    </div>
    <div className="flex space-x-2">
      <select
        value={perPage}
        onChange={(e) => setPerPage(Number(e.target.value))}
        className="border border-gray-300 rounded-md px-2 py-1 text-sm"
      >
        <option value="25">25 per page</option>
        <option value="50">50 per page</option>
        <option value="100">100 per page</option>
      </select>

      <button
        onClick={() => setPage(page - 1)}
        disabled={page === 1}
        className="px-3 py-1 border border-gray-300 rounded-md text-sm disabled:opacity-50"
      >
        Previous
      </button>

      <span className="px-3 py-1 text-sm">
        Page {page} of {lastPage}
      </span>

      <button
        onClick={() => setPage(page + 1)}
        disabled={page === lastPage}
        className="px-3 py-1 border border-gray-300 rounded-md text-sm disabled:opacity-50"
      >
        Next
      </button>
    </div>
  </div>
</div>
```

---

### **Phase 6: Data Retention & Archival System** ⏳ [Not Started]
**Objective**: Implement 90-day retention with 7-year cold storage archival

**Retention Policy:**
- **Active Storage**: 90 days in `cdr_logs` table for fast querying
- **Archival Process**: Automatic archival to flat files after 90 days
- **Cold Storage**: 7-year retention in monthly flat files
- **File Format**: JSON format, one file per month
- **Storage Location**: Configurable archival directory

**Archival Process:**
```php
// Scheduled job to run daily
class ArchiveOldCdrRecords implements ShouldQueue
{
    public function handle()
    {
        // Find records older than 90 days
        $cutoffDate = now()->subDays(90);

        // Export to monthly JSON files
        $oldRecords = CdrLog::where('created_at', '<', $cutoffDate)
            ->orderBy('created_at')
            ->get()
            ->groupBy(function($record) {
                return $record->created_at->format('Y-m');
            });

        foreach ($oldRecords as $month => $records) {
            $filename = "cdr_logs_{$month}.json";
            Storage::disk('cdr_archive')->put($filename, $records->toJson());
        }

        // Remove archived records from database
        CdrLog::where('created_at', '<', $cutoffDate)->delete();
    }
}
```

### **Phase 7: Export Functionality** ⏳ [Not Started]
**Objective**: Implement CDR export and historical download capabilities

**Export Features:**
- **Current Data Export**: Export filtered CDR results to CSV
- **Historical Downloads**: Download monthly archived CDR files
- **Format Options**: JSON and CSV formats
- **Date Range Selection**: Export specific date ranges
- **Background Processing**: Large exports processed asynchronously

**API Endpoints:**
```php
// Export current filtered results
Route::get('/cdr/export', [CdrController::class, 'export']);

// Download historical monthly files
Route::get('/cdr/archive/{year}/{month}', [CdrController::class, 'downloadArchive']);

// List available archive files
Route::get('/cdr/archives', [CdrController::class, 'listArchives']);
```

### **Phase 8: Testing & Performance Optimization** ⏳ [Not Started]
**Objective**: Comprehensive testing and performance validation

**Testing Strategy:**
1. **Unit Tests**: CdrController methods, data mapping functions
2. **Feature Tests**: API endpoints with various filter combinations
3. **Integration Tests**: Full webhook-to-API-to-frontend flow
4. **Performance Tests**: Query optimization with large datasets
5. **Load Tests**: Concurrent webhook processing

**Performance Benchmarks:**
- Query response time: <500ms for filtered requests
- Pagination efficiency: Consistent performance with large datasets
- Webhook processing: <100ms per CDR record
- Memory usage: Minimal memory footprint

---

## 🔧 **Technical Specifications**

### **API Response Formats**

#### **GET /api/cdr (List with Pagination)**
```json
{
  "data": [
    {
      "id": 1,
      "call_id": "0b515a4c3c683a5c51048b2c3758719f@xxx.xxx.xxx.xxx:5060",
      "session_token": "3caa3541d5f8497e9c79ea30ef689677",
      "from_number": "cdrFromIdentString",
      "to_number": "cdrDestinationIdentString",
      "direction": "inbound",
      "disposition": "ANSWER",
      "start_time": "2024-01-15T10:30:00Z",
      "answer_time": "2024-01-15T10:30:02Z",
      "end_time": "2024-01-15T10:30:33Z",
      "duration_seconds": 33,
      "billsec": 31,
      "domain": "nullDomain",
      "vapp_server": "172.24.xxx.xxx",
      "qos_data": {
        "rtt": 0,
        "rx_mos": "NaN",
        "tx_mos": 4.4,
        "ssrc": "1909495585"
      },
      "created_at": "2024-01-15T10:30:33Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 50,
    "total": 1250,
    "last_page": 25,
    "from": 1,
    "to": 50
  },
  "filters_applied": {
    "disposition": "ANSWER",
    "start_date": "2024-01-01",
    "end_date": "2024-01-31"
  }
}
```

#### **GET /api/cdr/{id} (Single Record)**
```json
{
  "data": {
    "id": 1,
    "call_id": "...",
    // ... all CDR fields
    "raw_cdr": {
      // Complete original webhook payload
    }
  }
}
```

### **Error Response Format**
```json
{
  "message": "Validation failed",
  "errors": {
    "start_date": ["The start date must be a valid date"],
    "disposition": ["The selected disposition is invalid"]
  }
}
```

---

## 📊 **Data Architecture**

### **Storage Strategy**
- **Structured Data**: Core CDR fields in dedicated columns for fast queries
- **Flexible Data**: QoS and raw CDR stored as JSON for extensibility
- **Indexing Strategy**: Composite indexes for common query patterns
- **Partitioning**: Future consideration for large-scale deployments

### **Query Optimization**
- **Index Usage**: Automatic query planner optimization
- **Pagination**: Cursor-based pagination for performance
- **Filtering**: Efficient WHERE clause construction
- **Sorting**: Multiple sort options with proper indexing

---

## 🔒 **Security & Compliance**

### **Data Protection**
- **Tenant Isolation**: All queries scoped to authenticated tenant
- **PII Handling**: Proper sanitization of phone numbers and identifiers
- **Audit Trail**: Complete logging of CDR access and modifications
- **Retention Policies**: Configurable data retention periods

### **API Security**
- **Authentication**: Sanctum token required for all endpoints
- **Authorization**: Tenant-based data access control
- **Rate Limiting**: Future consideration for API protection
- **Input Validation**: Comprehensive validation of all parameters

---

## 📈 **Monitoring & Analytics**

### **Key Metrics**
- **API Performance**: Response times, throughput, error rates
- **Data Volume**: CDR records processed, storage growth
- **Query Patterns**: Most common filters and search terms
- **System Health**: Database performance, webhook processing latency

### **Logging Strategy**
- **API Access**: All CDR queries logged with user context
- **Performance Metrics**: Query execution times and resource usage
- **Error Tracking**: Failed queries and webhook processing errors
- **Audit Trail**: Complete history of data access and modifications

---

## 🚀 **Success Criteria**

- ✅ **Webhook Processing**: CDR data correctly stored in dedicated table
- ✅ **API Filtering**: All filter parameters work (From, To, Date/Time Range, Disposition, Token)
- ✅ **Pagination**: Efficient pagination with configurable page sizes
- ✅ **Performance**: Sub-second query response times for filtered requests
- ✅ **Data Integrity**: No duplicates, complete CDR records with proper tenant isolation
- ✅ **UI Integration**: Call Logs page fully functional with real data and advanced filtering
- ✅ **Data Retention**: 90-day active storage with automatic archival to monthly files
- ✅ **Export Functionality**: CSV export and historical file downloads working
- ✅ **Security**: Proper authentication, authorization, and data isolation
- ✅ **Scalability**: System handles large CDR volumes with good performance

---

## 📋 **Implementation Checklist**

### **Phase 1: Database Schema & Migration**
- [ ] Create cdr_logs table migration (without financial fields, QoS in metadata)
- [ ] Add all required columns and indexes
- [ ] Create CdrLog Eloquent model
- [ ] Test migration rollback
- [ ] Verify foreign key constraints

### **Phase 2: CdrController & API Endpoints**
- [ ] Create CdrController class
- [ ] Implement index method with filtering and pagination
- [ ] Implement show method for single records
- [ ] Implement export method for CSV downloads
- [ ] Add API routes to routes/api.php
- [ ] Test API endpoints with various parameters

### **Phase 3: Webhook Integration & Data Mapping**
- [ ] Update VoiceApplicationController handleCdrCallback method
- [ ] Implement disposition status mapping (ANSWER, BUSY, CANCEL, FAILED, CONGESTION, NOANSWER)
- [ ] Store QoS data in raw_cdr metadata
- [ ] Add duplicate prevention logic
- [ ] Test webhook processing with sample data
- [ ] Verify data storage in cdr_logs table

### **Phase 4: Frontend Call Logs Integration**
- [ ] Update CallLogs component to use real CDR API
- [ ] Implement advanced filtering UI (manual refresh only)
- [ ] Add pagination controls
- [ ] Add export functionality
- [ ] Add loading states and error handling
- [ ] Test with real CDR data

### **Phase 5: Data Retention & Archival System**
- [ ] Create ArchiveOldCdrRecords job for 90-day retention
- [ ] Implement monthly JSON archival files
- [ ] Set up scheduled job execution
- [ ] Test archival process
- [ ] Verify 7-year cold storage structure

### **Phase 6: Historical Download System**
- [ ] Implement listArchives method for archive file listing
- [ ] Implement downloadArchive method for monthly file downloads
- [ ] Add archive file management
- [ ] Test historical file downloads
- [ ] Verify file integrity and security

### **Phase 7: Export Functionality Enhancement**
- [ ] Enhance export method for large datasets
- [ ] Add background processing for exports
- [ ] Implement CSV format with all fields
- [ ] Add export progress tracking
- [ ] Test export functionality with various filters

### **Phase 8: Testing & Performance Optimization**
- [ ] Write unit tests for CdrController
- [ ] Test all filter combinations and edge cases
- [ ] Performance test with large datasets
- [ ] Load test webhook processing
- [ ] Validate data integrity and security
- [ ] Test archival, export, and download features

---

## 📝 **Specification Updates Summary**

**Updated based on user feedback:**
- ✅ **QoS Data**: Stored in raw_cdr metadata (not separate column)
- ✅ **Financial Fields**: Removed (not needed at this time)
- ✅ **Auto-refresh**: Manual refresh only (no auto-refresh needed)
- ✅ **Data Retention**: 90-day active storage, 7-year cold storage in monthly files
- ✅ **Export Functionality**: Required with historical monthly downloads
- ✅ **Disposition Status**: CONGESTION added to mapping

**Added New Phases:**
- **Phase 5**: Data Retention & Archival System (90-day retention)
- **Phase 6**: Historical Download System (monthly file downloads)
- **Phase 7**: Export Functionality Enhancement (CSV exports)

---

*This specification provides a complete roadmap for implementing a robust CDR retrieval system with advanced filtering, pagination, data retention, and export capabilities.*