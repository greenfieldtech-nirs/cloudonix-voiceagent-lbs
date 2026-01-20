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
    <div className="dashboard">
      <div className="dashboard-header">
        <h1>Voice Agent Analytics Dashboard</h1>
        <div className="header-controls">
          <div className={`connection-status ${isConnected ? 'connected' : 'disconnected'}`}>
            <span className="status-dot"></span>
            {isConnected ? 'Real-time Connected' : 'Real-time Disconnected'}
          </div>
          <button onClick={fetchDashboardData} disabled={loading}>
            {loading ? 'Refreshing...' : 'Refresh'}
          </button>
        </div>
      </div>

      <div className="metrics-grid">
        <div className="metric-card">
          <h3>Total Calls</h3>
          <div className="metric-value">{data.total_calls.toLocaleString()}</div>
        </div>

        <div className="metric-card">
          <h3>Active Calls</h3>
          <div className="metric-value">{data.active_calls}</div>
        </div>

        <div className="metric-card">
          <h3>Success Rate</h3>
          <div className="metric-value">{data.success_rate.toFixed(1)}%</div>
        </div>

        <div className="metric-card">
          <h3>Avg Duration</h3>
          <div className="metric-value">{Math.round(data.average_duration / 60)}m</div>
        </div>

        <div className="metric-card">
          <h3>Calls Today</h3>
          <div className="metric-value">{data.calls_today}</div>
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

      <style jsx>{`
        .dashboard {
          padding: 20px;
          max-width: 1200px;
          margin: 0 auto;
        }

        .dashboard-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 30px;
        }

        .dashboard-header h1 {
          margin: 0;
          color: #333;
        }

        .metrics-grid {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
          gap: 20px;
          margin-bottom: 30px;
        }

        .metric-card {
          background: white;
          border: 1px solid #e1e5e9;
          border-radius: 8px;
          padding: 20px;
          text-align: center;
        }

        .metric-card h3 {
          margin: 0 0 10px 0;
          color: #666;
          font-size: 14px;
          text-transform: uppercase;
          letter-spacing: 0.5px;
        }

        .metric-value {
          font-size: 32px;
          font-weight: bold;
          color: #333;
        }

        .dashboard-grid {
          display: grid;
          grid-template-columns: 1fr 1fr;
          gap: 30px;
        }

        .dashboard-section {
          background: white;
          border: 1px solid #e1e5e9;
          border-radius: 8px;
          padding: 20px;
        }

        .dashboard-section h2 {
          margin: 0 0 20px 0;
          color: #333;
        }

        .agent-list {
          display: flex;
          flex-direction: column;
          gap: 15px;
        }

        .agent-item {
          display: flex;
          align-items: center;
          gap: 15px;
          padding: 10px;
          border-radius: 6px;
          background: #f8f9fa;
        }

        .agent-rank {
          width: 30px;
          height: 30px;
          border-radius: 50%;
          background: #007bff;
          color: white;
          display: flex;
          align-items: center;
          justify-content: center;
          font-weight: bold;
        }

        .agent-info {
          flex: 1;
        }

        .agent-name {
          font-weight: 600;
          color: #333;
          margin-bottom: 4px;
        }

        .agent-stats {
          font-size: 14px;
          color: #666;
        }

        .trend-list {
          display: flex;
          flex-direction: column;
          gap: 10px;
        }

        .trend-item {
          padding: 10px;
          border-radius: 6px;
          background: #f8f9fa;
        }

        .trend-date {
          font-weight: 600;
          color: #333;
          margin-bottom: 4px;
        }

        .trend-stats {
          font-size: 14px;
          color: #666;
          margin-bottom: 8px;
        }

        .trend-bar {
          height: 8px;
          background: #e9ecef;
          border-radius: 4px;
          overflow: hidden;
        }

        .trend-fill {
          height: 100%;
          background: linear-gradient(90deg, #28a745 0%, #20c997 100%);
          transition: width 0.3s ease;
        }

        .loading {
          text-align: center;
          padding: 50px;
          font-size: 18px;
          color: #666;
        }

        button {
          padding: 8px 16px;
          background: #007bff;
          color: white;
          border: none;
          border-radius: 4px;
          cursor: pointer;
          font-size: 14px;
        }

        button:hover {
          background: #0056b3;
        }

        button:disabled {
          background: #6c757d;
          cursor: not-allowed;
        }

        .header-controls {
          display: flex;
          align-items: center;
          gap: 20px;
        }

        .connection-status {
          display: flex;
          align-items: center;
          gap: 8px;
          font-size: 14px;
          font-weight: 500;
        }

        .connection-status.connected {
          color: #28a745;
        }

        .connection-status.disconnected {
          color: #dc3545;
        }

        .status-dot {
          width: 8px;
          height: 8px;
          border-radius: 50%;
          background: currentColor;
          animation: pulse 2s infinite;
        }

        @keyframes pulse {
          0%, 100% {
            opacity: 1;
          }
          50% {
            opacity: 0.5;
          }
        }
      `}</style>
    </div>
  );
};

export default Dashboard;