# Session Update Webhooks & Live Calls Implementation Specification

## 🎯 **Overview**

This specification details the implementation of Cloudonix session update webhook handling and real-time live calls monitoring for the AI Voice SaaS platform.

### **Key Features**
- **Webhook Processing**: Handle Cloudonix session update webhooks with full payload processing
- **Real-time Dashboard**: Live calls monitoring with WebSocket updates
- **Active Call Tracking**: Accurate counting of active calls (ringing + connected status)
- **Data Persistence**: Comprehensive call session storage with metadata
- **Tenant Isolation**: Proper multi-tenant webhook processing and data scoping

### **Business Value**
- **Real-time Visibility**: Live monitoring of active voice calls
- **Operational Insights**: Accurate call metrics for dashboard and reporting
- **Debugging Capability**: Complete webhook payload storage for troubleshooting
- **Scalable Architecture**: WebSocket-based real-time updates for multiple clients

### **⚠️ CRITICAL REQUIREMENTS**

#### **🚨 EXTREMELY IMPORTANT: No Rate Limiting on Session-Update Endpoint**
**Session-update webhooks occur at very high frequency and must be processed immediately without any rate limiting.**

- **Reason**: Cloudonix sends session updates in rapid succession during call lifecycle
- **Impact**: Rate limiting would cause critical call status updates to be dropped or delayed
- **Requirement**: The `/api/session/update` endpoint must have ZERO rate limiting
- **Implementation**: Ensure no middleware applies rate limiting to this specific route
- **Monitoring**: Implement logging but no throttling for webhook processing

**FAILURE TO COMPLY**: Real-time call monitoring will be broken, dashboard will show stale data, and call tracking will be unreliable.

---

## 📋 **Implementation Plan**

### **Phase 1: Database Schema & Models** ✅ [Completed]
**Objective**: Update database schema to support comprehensive call session storage

**Tasks:**
1. **Database Migration**: Add new columns to `call_sessions` table
2. **Model Updates**: Update `CallSession` model with new fields and relationships
3. **Status Enum**: Expand status enum to include `connected` status
4. **Indexes**: Add performance indexes for queries
5. **Migration Testing**: Verify migration runs successfully

**Files to Create/Modify:**
- `backend/database/migrations/2026_01_XX_XXXXXX_update_call_sessions_table.php`
- `backend/app/Models/CallSession.php`

**Success Criteria:**
- ✅ Migration creates all required columns
- ✅ Model fillable array includes new fields
- ✅ Status enum includes 'connected'
- ✅ Indexes improve query performance

---

### **Phase 2: Webhook Processing Engine** ✅ [Completed]
**Objective**: Implement webhook handler for Cloudonix session updates

**⚠️ CRITICAL REQUIREMENT**: Ensure `/api/session/update` endpoint has NO rate limiting - session updates occur at high frequency and must be processed immediately.

**Tasks:**
1. **Controller Updates**: Modify `VoiceApplicationController` to handle Cloudonix webhook format
2. **Tenant Resolution**: Implement domain-based tenant lookup
3. **Data Mapping**: Create webhook-to-database field mapping
4. **Status Mapping**: Implement Cloudonix-to-internal status conversion
5. **Upsert Logic**: Single row per session token with updates
6. **Duration Calculation**: Implement timestamp-based duration calculation
7. **Validation**: Add webhook payload validation
8. **🚨 RATE LIMITING**: Explicitly verify NO rate limiting is applied to session update endpoint

**Files to Create/Modify:**
- `backend/app/Http/Controllers/Api/VoiceApplicationController.php`
- `backend/app/Http/Controllers/Api/AuthController.php` (domain storage verification)

**Success Criteria:**
- ✅ Webhooks processed with proper tenant resolution
- ✅ Call sessions created/updated correctly
- ✅ Status mapping works for all Cloudonix statuses
- ✅ Duration calculated accurately
- ✅ Complete webhook payload stored in metadata
- ✅ 🚨 NO rate limiting applied to `/api/session/update` endpoint (CRITICAL)

---

### **Phase 3: API Endpoints & Business Logic** ✅ [Completed]
**Objective**: Create API endpoints for dashboard data and active call queries

**Tasks:**
1. **Active Calls API**: `GET /api/calls/active` endpoint
2. **Call Statistics API**: `GET /api/calls/stats` endpoint
3. **Controller Logic**: Implement query logic for active calls
4. **Tenant Scoping**: Ensure all queries are tenant-scoped
5. **Performance**: Optimize queries with proper indexing
6. **Response Formatting**: Structure API responses for frontend consumption

**Files to Create/Modify:**
- `backend/app/Http/Controllers/Api/CallController.php` (new)
- `backend/routes/api.php` (add routes)

**Success Criteria:**
- ✅ Active calls API returns correct data
- ✅ Statistics API provides dashboard metrics
- ✅ All queries properly scoped to tenant
- ✅ Response format matches frontend expectations

---

### **Phase 4: WebSocket Real-time Updates** ⏳ [Not Started]
**Objective**: Implement WebSocket broadcasting for real-time call updates

**Tasks:**
1. **Broadcasting Setup**: Configure Laravel Broadcasting with Redis
2. **Event Classes**: Create call update events
3. **Channel Authorization**: Implement tenant-based channel authorization
4. **Broadcast Logic**: Trigger events on call status changes
5. **Connection Management**: Handle WebSocket connections and disconnections
6. **Reconnection Logic**: Implement auto-reconnection for frontend

**Files to Create/Modify:**
- `backend/config/broadcasting.php` (update)
- `backend/app/Events/CallStatusUpdated.php` (new)
- `backend/app/Events/CallEnded.php` (new)
- `backend/routes/channels.php` (update)

**Success Criteria:**
- ✅ WebSocket server configured and running
- ✅ Events broadcast on call updates
- ✅ Frontend receives real-time updates
- ✅ Proper channel authorization

---

### **Phase 5: Frontend Live Calls Component** ⏳ [Not Started]
**Objective**: Build real-time live calls monitoring interface

**Tasks:**
1. **Live Calls Component**: Create table component for active calls
2. **WebSocket Integration**: Connect to WebSocket channels
3. **Real-time Updates**: Update call list on WebSocket events
4. **Status Indicators**: Color-coded status badges
5. **Duration Display**: Show calculated call duration
6. **Responsive Design**: Mobile-friendly table layout

**Files to Create/Modify:**
- `frontend/src/pages/LiveCalls.tsx` (new)
- `frontend/src/components/WebSocketProvider.tsx` (new)
- `frontend/src/App.tsx` (add WebSocket provider)

**Success Criteria:**
- ✅ Live calls table displays correctly
- ✅ Real-time updates work via WebSocket
- ✅ Status badges show proper colors
- ✅ Duration updates in real-time

---

### **Phase 6: Dashboard Integration** ✅ [Completed]
**Objective**: Update dashboard with real-time active calls counter

**Tasks:**
1. **Active Calls Card**: Update to fetch real data from API
2. **WebSocket Subscription**: Connect dashboard to call updates
3. **Counter Updates**: Real-time counter updates
4. **Fallback Logic**: API polling fallback when WebSocket unavailable
5. **Loading States**: Proper loading indicators
6. **Error Handling**: Graceful error states

**Files to Create/Modify:**
- `frontend/src/pages/Dashboard.tsx` (update)
- `frontend/src/hooks/useActiveCalls.ts` (new)

**Success Criteria:**
- ✅ Dashboard shows real active call count
- ✅ Counter updates in real-time
- ✅ Proper loading and error states
- ✅ Fallback to API polling works

---

### **Phase 7: Cleanup & Archival System** ⏳ [Not Started]
**Objective**: Implement 30-day cleanup policy for inactive calls

**Tasks:**
1. **Cleanup Job**: Create scheduled job for call cleanup
2. **Archival Logic**: Move old calls to archive table or soft delete
3. **Performance**: Efficient bulk cleanup operations
4. **Configuration**: Make retention period configurable
5. **Logging**: Log cleanup operations for auditing

**Files to Create/Modify:**
- `backend/app/Jobs/CleanupOldCallSessions.php` (new)
- `backend/app/Console/Commands/CleanupCallSessions.php` (new)
- `backend/config/app.php` (schedule cleanup job)

**Success Criteria:**
- ✅ Inactive calls cleaned up after 30 days
- ✅ Job runs on schedule
- ✅ Performance doesn't impact active operations
- ✅ Cleanup operations logged

---

### **Phase 8: Testing & Validation** ⏳ [Not Started]
**Objective**: Comprehensive testing of the entire system

**Tasks:**
1. **Unit Tests**: Test webhook processing logic
2. **Feature Tests**: Test API endpoints and WebSocket events
3. **Integration Tests**: Test full webhook-to-frontend flow
4. **Performance Tests**: Test with high call volumes
5. **Load Tests**: Test WebSocket connections and broadcasting
6. **Browser Testing**: Test across different browsers

**Files to Create/Modify:**
- `backend/tests/Unit/VoiceApplicationControllerTest.php` (update)
- `backend/tests/Feature/CallApiTest.php` (new)
- `backend/tests/Feature/WebSocketTest.php` (new)

**Success Criteria:**
- ✅ All tests pass
- ✅ Webhook processing validated
- ✅ Real-time updates tested
- ✅ Performance requirements met

---

## 🔧 **Technical Specifications**

### **Database Schema**

#### **call_sessions Table**
```sql
CREATE TABLE call_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id BIGINT UNSIGNED NOT NULL,
    session_id VARCHAR(255) NOT NULL UNIQUE,
    domain VARCHAR(255),
    caller_id VARCHAR(255),
    destination VARCHAR(255),
    direction VARCHAR(255),
    token VARCHAR(255),
    status ENUM('ringing', 'connected', 'answered', 'completed', 'failed', 'busy') DEFAULT 'ringing',
    vapp_server VARCHAR(255),
    call_start_time TIMESTAMP NULL,
    call_answer_time TIMESTAMP NULL,
    answer_time TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    modified_at TIMESTAMP NULL,
    duration_seconds INT NULL,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant_status (tenant_id, status),
    INDEX idx_session_id (session_id),
    INDEX idx_created_at (created_at),
    INDEX idx_domain (domain)
);
```

### **API Endpoints**

#### **GET /api/calls/active**
Returns active calls for the authenticated tenant.

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "session_id": "session_123",
      "domain": "company.cloudonix.com",
      "caller_id": "+1234567890",
      "destination": "+0987654321",
      "direction": "inbound",
      "status": "connected",
      "duration_seconds": 45,
      "call_start_time": "2024-01-15T10:30:00Z",
      "created_at": "2024-01-15T10:30:00Z"
    }
  ],
  "meta": {
    "total": 5,
    "active_count": 5
  }
}
```

#### **GET /api/calls/stats**
Returns call statistics for dashboard.

**Response:**
```json
{
  "active_calls": 5,
  "total_today": 156,
  "avg_duration": 180,
  "completed_today": 145,
  "failed_today": 11
}
```

### **Webhook Payload Processing**

#### **Input Format** (Cloudonix Webhook)
```json
{
  "id": 9844644,
  "domainId": 755,
  "domain": "company.cloudonix.com",
  "subscriberId": null,
  "destination": "destination_number",
  "callerId": "caller_number",
  "direction": "inbound",
  "token": "session_token_123",
  "createdAt": "2024-01-15T10:30:00Z",
  "modifiedAt": "2024-01-15T10:30:25Z",
  "status": "connected",
  "vappServer": "172.24.xxx.xxx",
  "callStartTime": 1705312200000,
  "answerTime": "2024-01-15T10:30:25Z",
  "profile": { ... }
}
```

#### **Status Mapping**
```php
$statusMap = [
    'ringing' => 'ringing',
    'connected' => 'connected',
    'processing' => 'ringing',
    'answer' => 'answered',
    'new' => 'ringing',
    'noanswer' => 'failed',
    'busy' => 'busy',
    'nocredit' => 'failed',
    'cancel' => 'failed',
    'external' => 'failed',
    'error' => 'failed',
];
```

### **WebSocket Events**

#### **CallStatusUpdated**
```javascript
{
  type: 'call.status.updated',
  data: {
    session_id: 'session_123',
    status: 'connected',
    duration_seconds: 25,
    tenant_id: 1
  }
}
```

#### **CallEnded**
```javascript
{
  type: 'call.ended',
  data: {
    session_id: 'session_123',
    final_status: 'completed',
    total_duration: 180,
    tenant_id: 1
  }
}
```

### **Frontend Components**

#### **Live Calls Table**
- Call Start Time, Call Token, From, To, Status, Duration
- 15-second HTTP polling auto-refresh (WebSocket in Phase 4)
- Manual refresh button
- Auto-refresh toggle (enable/disable)
- Status badges with colors and icons
- Real-time duration calculation
- Statistics cards: Active Calls, Completed (24h), Total Count

#### **Dashboard Active Calls Card**
- Real-time counter updates
- WebSocket-driven updates
- Fallback to API polling
- Loading and error states

### **WebSocket Channels**

#### **Private Channel**: `tenant.{tenantId}.calls`
- Authorization: User must belong to tenant
- Events: Call status updates, new calls, ended calls
- Broadcasting: Automatic on call session updates

---

## 📊 **Progress Tracking**

### **Current Status**: Implementation Phase 🔄
- ✅ Specification written
- ✅ Implementation approved - beginning execution

### **Phase Status**:
- **Phase 1**: Database Schema & Models ✅ Completed
- **Phase 2**: Webhook Processing Engine ✅ Completed
- **Phase 3**: API Endpoints & Business Logic ✅ Completed
- **Phase 4**: WebSocket Real-time Updates ⏳ Not Started
- **Phase 5**: Frontend Live Calls Component ✅ [Basic Implementation Complete]
- **Phase 6**: Dashboard Integration ✅ Completed
- **Phase 7**: Cleanup & Archival System ⏳ Not Started
- **Phase 8**: Testing & Validation ⏳ Not Started

### **Completion Criteria**
- [ ] All phases completed successfully
- [ ] Webhook processing tested with real Cloudonix data
- [ ] Real-time dashboard updates working
- [ ] Active call counting accurate
- [ ] WebSocket broadcasting functional
- [ ] 30-day cleanup policy implemented
- [ ] All tests passing
- [ ] Performance requirements met

---

## 🔍 **Risks & Mitigations**

### **High Volume Webhooks**
- **Risk**: High call volumes could overwhelm webhook processing
- **Mitigation**: Queue processing, rate limiting, database optimization

### **WebSocket Connection Issues**
- **Risk**: Network issues causing WebSocket disconnections
- **Mitigation**: Auto-reconnection, fallback to polling, connection pooling

### **Rate Limiting on Session Updates**
- **Risk**: Rate limiting blocks critical high-frequency session updates
- **Mitigation**: ZERO rate limiting on `/api/session/update` endpoint (EXTREMELY IMPORTANT)

### **Tenant Data Isolation**
- **Risk**: Webhooks from wrong tenant affecting data
- **Mitigation**: Strict tenant resolution, validation, audit logging

### **Database Performance**
- **Risk**: Large call session tables impacting performance
- **Mitigation**: Proper indexing, archival policy, query optimization

---

## 📞 **Dependencies**

### **External Systems**
- Cloudonix API for webhook delivery
- Redis for WebSocket broadcasting and caching
- Database for persistent storage

### **Internal Systems**
- Tenant management system
- Authentication system
- Existing API infrastructure

---

## 🎯 **Success Metrics**

- **Webhook Processing**: 100% success rate for valid webhooks
- **Real-time Updates**: <1 second latency for dashboard updates
- **Active Call Accuracy**: 100% accuracy in active call counting
- **System Performance**: Handle 1000+ concurrent calls
- **Data Retention**: 30-day cleanup working correctly

---

*This specification will be updated as implementation progresses. Each phase will be marked complete upon successful testing and validation.*