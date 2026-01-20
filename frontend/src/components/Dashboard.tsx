import React, { useState, useEffect } from 'react';
import { useRealtimeAnalytics } from '../hooks/useWebSocket';

interface CallTrend {
  date: string;
  total_calls: number;
  successful_calls: number;
  success_rate: number;
}

interface DashboardOverview {
  total_calls: number;
  active_calls: number;
  success_rate: number;
  average_duration: number;
  calls_today: number;
  calls_this_week: number;
  calls_this_month: number;
  top_agents: Array<{
    agent_id: number;
    agent_name: string;
    total_calls: number;
    success_rate: number;
    avg_duration: number;
  }>;
  call_trends: CallTrend[];
}

const Dashboard: React.FC = () => {
  const [loading, setLoading] = useState(false);
  const [data, setData] = useState<DashboardOverview | null>(null);
  const { analytics: realtimeData, isConnected } = useRealtimeAnalytics('1'); // TODO: Get tenant ID from context

  const fetchDashboardData = async () => {
    setLoading(true);
    try {
      const response = await fetch('/api/analytics/overview');
      const result = await response.json();
      setData(result.data);
    } catch (error) {
      console.error('Failed to load dashboard data');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchDashboardData();
  }, []);

  // Update data when real-time analytics are received
  useEffect(() => {
    if (realtimeData && data) {
      // Update specific metrics from real-time data
      if (realtimeData.metrics) {
        setData(prev => prev ? {
          ...prev,
          active_calls: realtimeData.metrics.active_calls || prev.active_calls,
          calls_today: realtimeData.metrics.calls_today || prev.calls_today,
        } : null);
      }
    }
  }, [realtimeData]);

  if (!data) {
    return <div className="loading">Loading dashboard...</div>;
  }

  return (
    <div className="p-5 max-w-6xl mx-auto">
      <div className="flex justify-between items-center mb-8">
        <h1 className="text-3xl font-bold text-gray-900">Voice Agent Analytics Dashboard</h1>
        <div className="flex items-center gap-5">
          <div className={`flex items-center gap-2 text-sm font-medium ${isConnected ? 'text-green-600' : 'text-red-600'}`}>
            <span className="w-2 h-2 rounded-full bg-current animate-pulse"></span>
            {isConnected ? 'Real-time Connected' : 'Real-time Disconnected'}
          </div>
          <button
            onClick={fetchDashboardData}
            disabled={loading}
            className="px-4 py-2 bg-blue-600 text-white border-none rounded cursor-pointer text-sm hover:bg-blue-700 disabled:bg-gray-500 disabled:cursor-not-allowed"
          >
            {loading ? 'Refreshing...' : 'Refresh'}
          </button>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 mb-8">
        <div className="bg-white border border-gray-200 rounded-lg p-5 text-center">
          <h3 className="text-gray-600 text-sm font-medium uppercase tracking-wide mb-2">Total Calls</h3>
          <div className="text-3xl font-bold text-gray-900">{data.total_calls.toLocaleString()}</div>
        </div>

        <div className="bg-white border border-gray-200 rounded-lg p-5 text-center">
          <h3 className="text-gray-600 text-sm font-medium uppercase tracking-wide mb-2">Active Calls</h3>
          <div className="text-3xl font-bold text-gray-900">{data.active_calls}</div>
        </div>

        <div className="bg-white border border-gray-200 rounded-lg p-5 text-center">
          <h3 className="text-gray-600 text-sm font-medium uppercase tracking-wide mb-2">Success Rate</h3>
          <div className="text-3xl font-bold text-gray-900">{data.success_rate.toFixed(1)}%</div>
        </div>

        <div className="bg-white border border-gray-200 rounded-lg p-5 text-center">
          <h3 className="text-gray-600 text-sm font-medium uppercase tracking-wide mb-2">Avg Duration</h3>
          <div className="text-3xl font-bold text-gray-900">{Math.round(data.average_duration / 60)}m</div>
        </div>

        <div className="bg-white border border-gray-200 rounded-lg p-5 text-center">
          <h3 className="text-gray-600 text-sm font-medium uppercase tracking-wide mb-2">Calls Today</h3>
          <div className="text-3xl font-bold text-gray-900">{data.calls_today}</div>
        </div>

        <div className="metric-card">
          <h3>Calls This Week</h3>
          <div className="metric-value">{data.calls_this_week}</div>
        </div>
      </div>

      <div className="dashboard-grid">
        <div className="dashboard-section">
          <h2>Top Agents</h2>
          <div className="agent-list">
            {data.top_agents.slice(0, 5).map((agent, index) => (
              <div key={agent.agent_id} className="agent-item">
                <div className="agent-rank">{index + 1}</div>
                <div className="agent-info">
                  <div className="agent-name">{agent.agent_name}</div>
                  <div className="agent-stats">
                    {agent.total_calls} calls • {agent.success_rate.toFixed(1)}% success
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>

        <div className="dashboard-section">
          <h2>Call Trends (Last 7 Days)</h2>
          <div className="trend-list">
            {data.call_trends.slice(-7).reverse().map((trend) => (
              <div key={trend.date} className="trend-item">
                <div className="trend-date">
                  {new Date(trend.date).toLocaleDateString()}
                </div>
                <div className="trend-stats">
                  {trend.total_calls} calls • {trend.success_rate.toFixed(1)}% success
                </div>
                <div className="trend-bar">
                  <div
                    className="trend-fill"
                    style={{ width: `${trend.success_rate}%` }}
                  ></div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>


    </div>
  );
};

export default Dashboard;