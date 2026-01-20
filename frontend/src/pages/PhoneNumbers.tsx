import React from 'react';
import { AdminLayout } from '../components/AdminLayout';

export const PhoneNumbers: React.FC = () => {
  return (
    <AdminLayout>
      <div className="space-y-8">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Phone Numbers</h1>
          <p className="text-gray-600 mt-1">Manage phone numbers for your voice services.</p>
        </div>

        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-8">
          <div className="text-center">
            <div className="text-6xl mb-4">📱</div>
            <h2 className="text-xl font-semibold text-gray-900 mb-2">Phone Number Management</h2>
            <p className="text-gray-600 mb-6">This feature is coming soon. You'll be able to provision, configure, and monitor phone numbers.</p>
            <button className="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
              Coming Soon
            </button>
          </div>
        </div>
      </div>
    </AdminLayout>
  );
};