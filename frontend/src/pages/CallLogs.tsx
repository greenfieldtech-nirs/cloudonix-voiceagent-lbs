import React, { useState, useEffect, useCallback } from 'react';
import { AdminLayout } from '../components/AdminLayout';
import { useToastHelpers } from '../components/toast';
import {
  Phone,
  PhoneOff,
  PhoneCall,
  RefreshCw,
  CheckCircle,
  XCircle,
  Clock,
  Filter
} from 'lucide-react';

interface CdrRecord {
  id: number;
  call_id: string;
  session_token: string;
  from_number: string;
  to_number: string;
  direction: string;
  disposition: 'ANSWER' | 'BUSY' | 'CANCEL' | 'FAILED' | 'CONGESTION' | 'NOANSWER';
  start_time: string;
  answer_time: string | null;
  end_time: string | null;
  duration_seconds: number | null;
  billsec: number | null;
  domain: string;
  created_at: string;
}

interface PaginationMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
  from: number | null;
  to: number | null;
}

interface CdrResponse {
  data: CdrRecord[];
  meta: PaginationMeta;
  filters_applied: Record<string, any>;
}

export const CallLogs: React.FC = () => {
  const { error } = useToastHelpers();

  const [cdrRecords, setCdrRecords] = useState<CdrRecord[]>([]);
  const [pagination, setPagination] = useState<PaginationMeta | null>(null);
  const [loading, setLoading] = useState(true);
  const [lastRefresh, setLastRefresh] = useState<Date>(new Date());

  // Filter states
  const [filters, setFilters] = useState({
    from: '',
    to: '',
    disposition: '',
    start_date: '',
    end_date: '',
    token: '',
  });
  const [showFilters, setShowFilters] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);
  const [perPage, setPerPage] = useState(50);

  // Fetch data from CDR API
  const fetchData = useCallback(async () => {
    try {
      // Build query parameters
      const params = new URLSearchParams({
        page: currentPage.toString(),
        per_page: perPage.toString(),
      });

      // Add filters
      Object.entries(filters).forEach(([key, value]) => {
        if (value) {
          params.append(key, value);
        }
      });

      // Fetch CDR records
      const response = await fetch(`http://localhost:8000/api/cdr?${params}`, {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'Accept': 'application/json',
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch CDR records');
      }

      const cdrResponse: CdrResponse = await response.json();

      // Update state
      setCdrRecords(cdrResponse.data);
      setPagination(cdrResponse.meta);
      setLastRefresh(new Date());



    } catch (err) {
      console.error('Failed to fetch CDR data:', err);
      error(
        'Failed to Load Call Logs',
        'Unable to fetch call log data. Please check your connection.',
        err instanceof Error ? err.message : 'Unknown error'
      );
    } finally {
      setLoading(false);
    }
  }, [error, currentPage, perPage, filters]);

  // Initial load
  useEffect(() => {
    fetchData();
  }, [fetchData]);

  // Get disposition color and icon
  const getDispositionInfo = (disposition: string) => {
    switch (disposition) {
      case 'ANSWER':
        return { color: 'text-green-600 bg-green-100', icon: CheckCircle, label: 'Answered' };
      case 'BUSY':
        return { color: 'text-orange-600 bg-orange-100', icon: PhoneOff, label: 'Busy' };
      case 'CANCEL':
        return { color: 'text-yellow-600 bg-yellow-100', icon: XCircle, label: 'Cancelled' };
      case 'FAILED':
        return { color: 'text-red-600 bg-red-100', icon: XCircle, label: 'Failed' };
      case 'CONGESTION':
        return { color: 'text-purple-600 bg-purple-100', icon: PhoneOff, label: 'Congestion' };
      case 'NOANSWER':
        return { color: 'text-gray-600 bg-gray-100', icon: Phone, label: 'No Answer' };
      default:
        return { color: 'text-gray-600 bg-gray-100', icon: Phone, label: disposition };
    }
  };

  // Format duration
  const formatDuration = (seconds: number): string => {
    if (seconds < 60) {
      return `${seconds}s`;
    }
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;
    return `${minutes}m ${remainingSeconds}s`;
  };

  // Format date/time
  const formatDateTime = (dateString: string): string => {
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit'
    });
  };

  const StatCard: React.FC<{
    title: string;
    value: number;
    icon: React.ComponentType<any>;
    color: string;
  }> = ({ title, value, icon: Icon, color }) => (
    <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm font-medium text-gray-600">{title}</p>
          <p className={`text-3xl font-bold ${color} mt-2`}>{value.toLocaleString()}</p>
        </div>
        <div className={`p-3 rounded-full ${color.replace('text-', 'bg-').replace('600', '100')}`}>
          <Icon className={`w-6 h-6 ${color}`} />
        </div>
      </div>
    </div>
  );

  return (
    <AdminLayout>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Call Logs</h1>
            <p className="text-gray-600 mt-1">
              Historical call records and CDR data
            </p>
          </div>
          <div className="flex items-center space-x-3">
            <span className="text-sm text-gray-500">
              Last updated: {lastRefresh.toLocaleTimeString()}
            </span>
            <button
              onClick={() => setShowFilters(!showFilters)}
              className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            >
              <Filter className="w-4 h-4 mr-2" />
              Filters
            </button>
            <button
              onClick={fetchData}
              className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            >
              <RefreshCw className="w-4 h-4 mr-2" />
              Refresh
            </button>
          </div>
        </div>

        {/* Filters Panel */}
        {showFilters && (
          <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">From Number</label>
                <input
                  type="text"
                  value={filters.from}
                  onChange={(e) => setFilters(prev => ({ ...prev, from: e.target.value }))}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  placeholder="Search from number..."
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">To Number</label>
                <input
                  type="text"
                  value={filters.to}
                  onChange={(e) => setFilters(prev => ({ ...prev, to: e.target.value }))}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  placeholder="Search to number..."
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Disposition</label>
                <select
                  value={filters.disposition}
                  onChange={(e) => setFilters(prev => ({ ...prev, disposition: e.target.value }))}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                >
                  <option value="">All Dispositions</option>
                  <option value="ANSWER">Answered</option>
                  <option value="BUSY">Busy</option>
                  <option value="CANCEL">Cancelled</option>
                  <option value="FAILED">Failed</option>
                  <option value="CONGESTION">Congestion</option>
                  <option value="NOANSWER">No Answer</option>
                </select>
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Session Token</label>
                <input
                  type="text"
                  value={filters.token}
                  onChange={(e) => setFilters(prev => ({ ...prev, token: e.target.value }))}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  placeholder="Search token..."
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input
                  type="date"
                  value={filters.start_date}
                  onChange={(e) => setFilters(prev => ({ ...prev, start_date: e.target.value }))}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input
                  type="date"
                  value={filters.end_date}
                  onChange={(e) => setFilters(prev => ({ ...prev, end_date: e.target.value }))}
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                />
              </div>
              <div className="md:col-span-2 flex items-end space-x-2">
                <button
                  onClick={() => {
                    setFilters({ from: '', to: '', disposition: '', start_date: '', end_date: '', token: '' });
                    setCurrentPage(1);
                  }}
                  className="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                  Clear Filters
                </button>
                <button
                  onClick={() => {
                    setCurrentPage(1);
                    fetchData();
                  }}
                  className="px-4 py-2 bg-indigo-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                  Apply Filters
                </button>
              </div>
            </div>
          </div>
        )}

        {/* Stats Cards */}
        <div className="grid grid-cols-6 gap-4">
          <StatCard
            title="Total Call Records"
            value={pagination?.total || 0}
            icon={PhoneCall}
            color="text-blue-600"
          />
          <StatCard
            title="Total Records Today"
            value={cdrRecords.filter(record =>
              record.created_at.startsWith(new Date().toISOString().split('T')[0])
            ).length}
            icon={Phone}
            color="text-indigo-600"
          />
          <StatCard
            title="Answered Today"
            value={cdrRecords.filter(record =>
              record.created_at.startsWith(new Date().toISOString().split('T')[0]) &&
              record.disposition === 'ANSWER'
            ).length}
            icon={CheckCircle}
            color="text-green-600"
          />
          <StatCard
            title="Not Answered Today"
            value={cdrRecords.filter(record =>
              record.created_at.startsWith(new Date().toISOString().split('T')[0]) &&
              record.disposition !== 'ANSWER'
            ).length}
            icon={XCircle}
            color="text-red-600"
          />
          <StatCard
            title="Avg Duration Today (Answered)"
            value={(() => {
              const todayAnswered = cdrRecords.filter(record =>
                record.created_at.startsWith(new Date().toISOString().split('T')[0]) &&
                record.disposition === 'ANSWER' &&
                record.duration_seconds
              );
              if (todayAnswered.length === 0) return 0;
              const totalDuration = todayAnswered.reduce((sum, r) => sum + (r.duration_seconds || 0), 0);
              return Math.round(totalDuration / todayAnswered.length);
            })()}
            icon={Clock}
            color="text-purple-600"
          />
          <StatCard
            title="Success Ratio (60 min)"
            value={(() => {
              const sixtyMinutesAgo = new Date(Date.now() - 60 * 60 * 1000);
              const recentCalls = cdrRecords.filter(record =>
                new Date(record.created_at) >= sixtyMinutesAgo
              );
              if (recentCalls.length === 0) return 0;
              const successfulCalls = recentCalls.filter(record =>
                record.disposition === 'ANSWER' || record.disposition === 'BUSY'
              ).length;
              return Math.round((successfulCalls / recentCalls.length) * 100);
            })()}
            icon={CheckCircle}
            color="text-emerald-600"
          />
        </div>

        {/* CDR Records Table */}
        <div className="bg-white shadow-sm rounded-lg border border-gray-200">
          <div className="px-6 py-4 border-b border-gray-200">
            <div className="flex items-center justify-between">
              <div>
                <h3 className="text-lg font-medium text-gray-900">Call Records</h3>
                <p className="text-sm text-gray-600 mt-1">
                  Historical call detail records (CDR)
                </p>
              </div>
              {pagination && (
                <div className="text-sm text-gray-500">
                  Showing {pagination.from}-{pagination.to} of {pagination.total} records
                </div>
              )}
            </div>
          </div>

          <div className="overflow-x-auto">
            {loading ? (
              <div className="p-8 text-center">
                <RefreshCw className="w-8 h-8 animate-spin mx-auto text-gray-400" />
                <p className="text-gray-500 mt-2">Loading call records...</p>
              </div>
            ) : cdrRecords.length === 0 ? (
              <div className="p-8 text-center">
                <Phone className="w-12 h-12 text-gray-400 mx-auto" />
                <p className="text-gray-500 mt-2">No call records found</p>
              </div>
            ) : (
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Start Time
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Call ID
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      From
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      To
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Disposition
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Duration
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Billable
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {cdrRecords.map((record) => {
                    const dispositionInfo = getDispositionInfo(record.disposition);
                    const DispositionIcon = dispositionInfo.icon;

                    return (
                      <tr key={record.id} className="hover:bg-gray-50">
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                          {record.start_time ? formatDateTime(record.start_time) : 'N/A'}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">
                          {record.call_id}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                          {record.from_number || 'Unknown'}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                          {record.to_number || 'Unknown'}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${dispositionInfo.color}`}>
                            <DispositionIcon className="w-3 h-3 mr-1" />
                            {dispositionInfo.label}
                          </span>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                          <div className="flex items-center">
                            <Clock className="w-4 h-4 text-gray-400 mr-1" />
                            {record.duration_seconds ? formatDuration(record.duration_seconds) : 'N/A'}
                          </div>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                          {record.billsec ? formatDuration(record.billsec) : 'N/A'}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            )}
          </div>

          {/* Pagination */}
          {pagination && pagination.last_page > 1 && (
            <div className="px-6 py-4 border-t border-gray-200 flex items-center justify-between">
              <div className="flex items-center space-x-2">
                <label className="text-sm text-gray-700">Per page:</label>
                <select
                  value={perPage}
                  onChange={(e) => {
                    setPerPage(Number(e.target.value));
                    setCurrentPage(1);
                  }}
                  className="border border-gray-300 rounded-md text-sm px-2 py-1 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                  <option value="25">25</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
                  <option value="200">200</option>
                </select>
              </div>

              <div className="flex items-center space-x-2">
                <button
                  onClick={() => setCurrentPage(prev => Math.max(1, prev - 1))}
                  disabled={currentPage === 1}
                  className="px-3 py-1 border border-gray-300 rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                >
                  Previous
                </button>

                <span className="text-sm text-gray-700">
                  Page {pagination.current_page} of {pagination.last_page}
                </span>

                <button
                  onClick={() => setCurrentPage(prev => Math.min(pagination.last_page, prev + 1))}
                  disabled={currentPage === pagination.last_page}
                  className="px-3 py-1 border border-gray-300 rounded-md text-sm disabled:opacity-50 disabled:cursor-not-allowed hover:bg-gray-50"
                >
                  Next
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </AdminLayout>
  );
};