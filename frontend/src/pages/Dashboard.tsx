import React, { useState, useEffect } from 'react';
import { AdminLayout } from '../components/AdminLayout';
import { useToastHelpers } from '../components/toast';

type ChangeType = 'positive' | 'negative' | 'neutral';

interface Stat {
  title: string;
  value: string;
  change: string;
  changeType: ChangeType;
  icon: string;
}

export const Dashboard: React.FC = () => {
  const { error } = useToastHelpers();
  const [stats, setStats] = useState<Stat[]>([]);
  const [loading, setLoading] = useState(true);

  // Fetch dashboard data
  useEffect(() => {
    const fetchDashboardData = async () => {
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

        const callStats = await statsResponse.json();

        // Create stats array with real data
        const statsData: Stat[] = [
          {
            title: 'Active Calls',
            value: callStats.active_calls?.toString() || '0',
            change: '+0', // TODO: Calculate change from previous period
            changeType: 'neutral',
            icon: '📞',
          },
          {
            title: 'Phone Numbers',
            value: '156', // TODO: Fetch from API
            change: '+8',
            changeType: 'positive',
            icon: '📱',
          },
          {
            title: 'Total Call Logs',
            value: callStats.total_today?.toString() || '0',
            change: `+${callStats.total_today || 0}`,
            changeType: 'positive',
            icon: '📋',
          },
          {
            title: 'Avg Call Duration',
            value: formatDuration(callStats.avg_duration || 0),
            change: '+0s', // TODO: Calculate change
            changeType: 'neutral',
            icon: '⏱️',
          },
        ];

        setStats(statsData);
      } catch (err) {
        console.error('Failed to fetch dashboard data:', err);
        error(
          'Dashboard Error',
          'Failed to load dashboard data. Please refresh the page.',
          err instanceof Error ? err.message : 'Unknown error'
        );

        // Fallback to basic stats
        setStats([
          {
            title: 'Active Calls',
            value: '0',
            change: '+0',
            changeType: 'neutral',
            icon: '📞',
          },
          {
            title: 'Phone Numbers',
            value: '156',
            change: '+8',
            changeType: 'positive',
            icon: '📱',
          },
          {
            title: 'Total Call Logs',
            value: '0',
            change: '+0',
            changeType: 'neutral',
            icon: '📋',
          },
          {
            title: 'Avg Call Duration',
            value: '0s',
            change: '+0s',
            changeType: 'neutral',
            icon: '⏱️',
          },
        ]);
      } finally {
        setLoading(false);
      }
    };

    fetchDashboardData();
  }, [error]);

  // Format duration in seconds to readable format
  const formatDuration = (seconds: number): string => {
    if (seconds < 60) {
      return `${Math.round(seconds)}s`;
    }
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = Math.round(seconds % 60);
    return `${minutes}m ${remainingSeconds}s`;
  };

  const recentActivity = [
    { id: 1, action: 'Call completed successfully', time: '2 minutes ago', type: 'call' },
    { id: 2, action: 'New phone number added', time: '5 minutes ago', type: 'phone' },
    { id: 3, action: 'Call log generated', time: '12 minutes ago', type: 'log' },
    { id: 4, action: 'Live call answered', time: '18 minutes ago', type: 'call' },
    { id: 5, action: 'Call failed to connect', time: '1 hour ago', type: 'error' },
  ];

  return (
    <AdminLayout>
      <div className="space-y-8">
        {/* Welcome section */}
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
          <p className="text-gray-600 mt-1">Welcome back! Here's what's happening with your voice services.</p>
        </div>

        {/* Stats grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          {loading ? (
            // Loading skeletons
            Array.from({ length: 4 }).map((_, index) => (
              <div key={index} className="bg-white rounded-lg shadow-sm border border-gray-200 p-6 animate-pulse">
                <div className="flex items-center justify-between">
                  <div className="flex-1">
                    <div className="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
                    <div className="h-8 bg-gray-200 rounded w-1/2"></div>
                  </div>
                  <div className="w-12 h-12 bg-gray-200 rounded-full"></div>
                </div>
                <div className="mt-4 flex items-center">
                  <div className="h-4 bg-gray-200 rounded w-16"></div>
                  <div className="h-4 bg-gray-200 rounded w-24 ml-2"></div>
                </div>
              </div>
            ))
          ) : (
            stats.map((stat) => (
              <div key={stat.title} className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium text-gray-600">{stat.title}</p>
                    <p className="text-3xl font-bold text-gray-900 mt-2">{stat.value}</p>
                  </div>
                  <div className="text-3xl">{stat.icon}</div>
                </div>
                <div className="mt-4 flex items-center">
                  <span className={`text-sm font-medium ${
                    stat.changeType === 'positive' ? 'text-green-600' :
                    stat.changeType === 'negative' ? 'text-red-600' :
                    stat.changeType === 'neutral' ? 'text-gray-600' :
                    'text-gray-600'
                  }`}>
                    {stat.change}
                  </span>
                  <span className="text-sm text-gray-500 ml-2">from last month</span>
                </div>
              </div>
            ))
          )}
        </div>

        {/* Recent activity */}
        <div className="bg-white rounded-lg shadow-sm border border-gray-200">
          <div className="px-6 py-4 border-b border-gray-200">
            <h2 className="text-lg font-semibold text-gray-900">Recent Activity</h2>
          </div>
          <div className="divide-y divide-gray-200">
            {recentActivity.map((activity) => (
              <div key={activity.id} className="px-6 py-4 flex items-center justify-between">
                <div className="flex items-center">
                   <div className={`w-2 h-2 rounded-full mr-4 ${
                     activity.type === 'call' ? 'bg-green-500' :
                     activity.type === 'phone' ? 'bg-purple-500' :
                     activity.type === 'log' ? 'bg-blue-500' :
                     'bg-red-500'
                   }`}></div>
                  <span className="text-sm text-gray-900">{activity.action}</span>
                </div>
                <span className="text-sm text-gray-500">{activity.time}</span>
              </div>
            ))}
          </div>
        </div>


      </div>
    </AdminLayout>
  );
};