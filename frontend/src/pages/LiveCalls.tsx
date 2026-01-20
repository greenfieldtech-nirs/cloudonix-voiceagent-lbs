import React, { useState, useEffect, useCallback } from 'react';
import { AdminLayout } from '../components/AdminLayout';
import { useToastHelpers } from '../components/toast';
import {
  Phone,
  PhoneOff,
  PhoneCall,
  RefreshCw,
  Play,
  Pause,
  CheckCircle,
  XCircle,
  Clock
} from 'lucide-react';

interface CallSession {
  id: number;
  session_id: string;
  token: string;
  caller_id: string;
  destination: string;
  status: 'ringing' | 'connected' | 'answered' | 'completed' | 'failed' | 'busy';
  call_start_time: string;
  duration_seconds: number;
}

interface CallStats {
  active_calls: number;
  completed_today: number;
  total_calls: number;
}

export const LiveCalls: React.FC = () => {
  const { error } = useToastHelpers();
  const [callStats, setCallStats] = useState<CallStats>({
    active_calls: 0,
    completed_today: 0,
    total_calls: 0
  });
  const [activeCalls, setActiveCalls] = useState<CallSession[]>([]);
  const [loading, setLoading] = useState(true);
  const [autoRefresh, setAutoRefresh] = useState(true);
  const [lastRefresh, setLastRefresh] = useState<Date>(new Date());

  // Fetch data from APIs
  const fetchData = useCallback(async () => {
    try {
      // Fetch call statistics
      const statsResponse = await fetch('http://localhost:8000/api/calls/statistics', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'Accept': 'application/json',
        },
      });

      if (!statsResponse.ok) {
        throw new Error('Failed to fetch call statistics');
      }

      const callStatsData = await statsResponse.json();

      // Fetch active calls
      const activeResponse = await fetch('http://localhost:8000/api/calls/active', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'Accept': 'application/json',
        },
      });

      if (!activeResponse.ok) {
        throw new Error('Failed to fetch active calls');
      }

      const activeCallsData = await activeResponse.json();

      // Update state
      setCallStats({
        active_calls: callStatsData.active_calls || 0,
        completed_today: callStatsData.completed_today || 0,
        total_calls: (callStatsData.total_today || 0) + (callStatsData.active_calls || 0)
      });

      setActiveCalls(activeCallsData.data || []);
      setLastRefresh(new Date());

    } catch (err) {
      console.error('Failed to fetch data:', err);
      error(
        'Failed to Load Live Calls',
        'Unable to fetch call data. Please check your connection.',
        err instanceof Error ? err.message : 'Unknown error'
      );
    } finally {
      setLoading(false);
    }
  }, [error]);

  // Initial load and auto-refresh
  useEffect(() => {
    fetchData();

    let interval: NodeJS.Timeout;
    if (autoRefresh) {
      interval = setInterval(fetchData, 15000); // Refresh every 15 seconds
    }

    return () => {
      if (interval) clearInterval(interval);
    };
  }, [autoRefresh, error, fetchData]);

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

  // Get status color and icon
  const getStatusInfo = (status: string) => {
    switch (status) {
      case 'ringing':
        return { color: 'text-yellow-600 bg-yellow-100', icon: Phone, label: 'Ringing' };
      case 'connected':
        return { color: 'text-green-600 bg-green-100', icon: PhoneCall, label: 'Connected' };
      case 'answered':
        return { color: 'text-blue-600 bg-blue-100', icon: PhoneCall, label: 'Answered' };
      case 'completed':
        return { color: 'text-green-600 bg-green-100', icon: CheckCircle, label: 'Completed' };
      case 'failed':
        return { color: 'text-red-600 bg-red-100', icon: XCircle, label: 'Failed' };
      case 'busy':
        return { color: 'text-orange-600 bg-orange-100', icon: PhoneOff, label: 'Busy' };
      default:
        return { color: 'text-gray-600 bg-gray-100', icon: Phone, label: status };
    }
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
            <h1 className="text-2xl font-bold text-gray-900">Live Calls</h1>
            <p className="text-gray-600 mt-1">
              Real-time call monitoring and statistics
            </p>
          </div>
          <div className="flex items-center space-x-3">
            <span className="text-sm text-gray-500">
              Last updated: {lastRefresh.toLocaleTimeString()}
            </span>
            <button
              onClick={fetchData}
              className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            >
              <RefreshCw className="w-4 h-4 mr-2" />
              Refresh
            </button>
            <button
              onClick={() => setAutoRefresh(!autoRefresh)}
              className={`inline-flex items-center px-4 py-2 border rounded-md text-sm font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 ${
                autoRefresh
                  ? 'border-green-300 text-green-700 bg-green-50 hover:bg-green-100'
                  : 'border-gray-300 text-gray-700 bg-white hover:bg-gray-50'
              }`}
            >
              {autoRefresh ? (
                <>
                  <Pause className="w-4 h-4 mr-2" />
                  Pause Auto-refresh
                </>
              ) : (
                <>
                  <Play className="w-4 h-4 mr-2" />
                  Resume Auto-refresh
                </>
              )}
            </button>
          </div>
        </div>

        {/* Stats Cards */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <StatCard
            title="Active Calls"
            value={callStats.active_calls}
            icon={PhoneCall}
            color="text-blue-600"
          />
          <StatCard
            title="Completed Calls (24h)"
            value={callStats.completed_today}
            icon={CheckCircle}
            color="text-green-600"
          />
          <StatCard
            title="Total Calls Count"
            value={callStats.total_calls}
            icon={Phone}
            color="text-purple-600"
          />
        </div>

        {/* Active Calls Table */}
        <div className="bg-white shadow-sm rounded-lg border border-gray-200">
          <div className="px-6 py-4 border-b border-gray-200">
            <h3 className="text-lg font-medium text-gray-900">Active Calls</h3>
            <p className="text-sm text-gray-600 mt-1">
              Currently active voice calls in the system
            </p>
          </div>

          <div className="overflow-x-auto">
            {loading ? (
              <div className="p-8 text-center">
                <RefreshCw className="w-8 h-8 animate-spin mx-auto text-gray-400" />
                <p className="text-gray-500 mt-2">Loading active calls...</p>
              </div>
            ) : activeCalls.length === 0 ? (
              <div className="p-8 text-center">
                <Phone className="w-12 h-12 text-gray-400 mx-auto" />
                <p className="text-gray-500 mt-2">No active calls at the moment</p>
              </div>
            ) : (
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Call Start Time
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Call Token
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      From
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      To
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Status
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Duration
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {activeCalls.map((call) => {
                    const statusInfo = getStatusInfo(call.status);
                    const StatusIcon = statusInfo.icon;

                    return (
                      <tr key={call.id} className="hover:bg-gray-50">
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                          {call.call_start_time ? formatDateTime(call.call_start_time) : 'N/A'}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900">
                          {call.token || call.session_id}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                          {call.caller_id || 'Unknown'}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                          {call.destination || 'Unknown'}
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap">
                          <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusInfo.color}`}>
                            <StatusIcon className="w-3 h-3 mr-1" />
                            {statusInfo.label}
                          </span>
                        </td>
                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                          <div className="flex items-center">
                            <Clock className="w-4 h-4 text-gray-400 mr-1" />
                            {formatDuration(call.duration_seconds || 0)}
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            )}
          </div>
        </div>
      </div>
    </AdminLayout>
  );
};