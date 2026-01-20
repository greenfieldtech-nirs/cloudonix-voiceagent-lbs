import React, { useState, useEffect } from 'react';
import { AdminLayout } from '../components/AdminLayout';
import { Settings, Globe, Key, Server, Save, AlertCircle, Eye, EyeOff } from 'lucide-react';
import { useToastHelpers } from '../components/toast';
import { Tooltip } from '../components/Tooltip';

export const SettingsPage: React.FC = () => {
  const { success, error } = useToastHelpers();
  const [formData, setFormData] = useState({
    cloudonix_domain: '',
    cloudonix_api_key: '',
    voice_app_api_key: '',
    voice_app_endpoint: 'http://localhost:8000',
  });
  const [loading, setLoading] = useState(false);
  const [showApiKey, setShowApiKey] = useState(false);
  const [showVoiceApiKey, setShowVoiceApiKey] = useState(false);

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setFormData({
      ...formData,
      [e.target.name]: e.target.value
    });
  };

  // Computed URLs based on voice app endpoint
  const voiceApplicationEndpoint = `${formData.voice_app_endpoint}/api/voice/application`;
  const sessionUpdateUrl = `${formData.voice_app_endpoint}/api/session/update`;
  const cdrCallbackUrl = `${formData.voice_app_endpoint}/api/session/cdr`;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      const response = await fetch('http://localhost:8000/api/settings', {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'Accept': 'application/json',
        },
        body: JSON.stringify(formData),
      });

      const data = await response.json();

      if (response.ok) {
        success('Settings Updated', 'Your Cloudonix settings have been saved successfully.');
      } else {
        // Extract error details for better error messages
        let errorDetails = '';
        if (data.error) {
          errorDetails = data.error.details || data.error.message || JSON.stringify(data.error, null, 2);
        }

        error(
          data.message || 'Failed to update settings',
          data.error?.suggestion || 'Please check your settings and try again.',
          errorDetails
        );
      }
    } catch (err) {
      error(
        'Network Error',
        'Unable to connect to the server. Please check your internet connection and try again.',
        err instanceof Error ? err.message : 'Unknown network error'
      );
    } finally {
      setLoading(false);
    }
  };

  // Load settings on component mount
  useEffect(() => {
    const loadSettings = async () => {
      try {
        const response = await fetch('http://localhost:8000/api/settings', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Accept': 'application/json',
          },
        });

        if (response.ok) {
          const data = await response.json();
          setFormData(prev => ({
            ...prev,
            cloudonix_domain: data.settings.cloudonix_domain || '',
            cloudonix_api_key: data.settings.cloudonix_api_key || '',
            voice_app_api_key: data.settings.voice_app_api_key || '',
            voice_app_endpoint: data.settings.voice_app_endpoint || 'http://localhost:8000',
          }));
        } else {
          const errorData = await response.json().catch(() => ({}));
          error(
            'Failed to Load Settings',
            'Unable to load your current settings.',
            errorData.message || 'Please refresh the page to try again.'
          );
        }
      } catch (err) {
        console.error('Failed to load settings:', err);
        error(
          'Network Error',
          'Unable to connect to the server to load settings.',
          err instanceof Error ? err.message : 'Unknown error occurred'
        );
      }
    };

    loadSettings();
  }, [error]);

  return (
    <AdminLayout>
      <div className="max-w-4xl mx-auto">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900">Cloudonix Settings</h1>
          <p className="text-gray-600 mt-2">Configure your Cloudonix integration settings</p>
        </div>

        {/* Warning Notice */}
        <div className="mb-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
          <div className="flex">
            <AlertCircle className="h-5 w-5 text-yellow-400 mr-3 mt-0.5" />
            <div>
              <h3 className="text-sm font-medium text-yellow-800">Important</h3>
              <p className="mt-1 text-sm text-yellow-700">
                These settings are critical for your voice services to function properly.
                Please ensure all information is correct before saving.
              </p>
            </div>
          </div>
        </div>

        <form onSubmit={handleSubmit}>
          <div className="bg-white shadow-sm rounded-lg border border-gray-200">
            <div className="px-6 py-4 border-b border-gray-200">
              <div className="flex items-center">
                <Settings className="h-5 w-5 text-indigo-600 mr-3" />
                <h2 className="text-lg font-medium text-gray-900">Cloudonix Configuration</h2>
              </div>
              <p className="mt-1 text-sm text-gray-600 ml-8">
                Configure your Cloudonix integration settings for voice services
              </p>
            </div>

            <div className="px-6 py-6 space-y-6">
              {/* Cloudonix Domain and API Key Row */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* Cloudonix Domain */}
                <div>
                  <div className="flex items-center mb-1">
                    <label htmlFor="cloudonix_domain" className="block text-sm font-medium text-gray-700">
                      Cloudonix Domain name or UUID
                    </label>
                    <Tooltip
                      content="Contact Cloudonix support to get your account domain name or UUID."
                      className="ml-2"
                    />
                  </div>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <Globe className="h-5 w-5 text-gray-400" />
                    </div>
                    <input
                      type="text"
                      name="cloudonix_domain"
                      id="cloudonix_domain"
                      value={formData.cloudonix_domain}
                      onChange={handleInputChange}
                      className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                      placeholder="yourcompany.cloudonix.com or domain-uuid"
                    />
                  </div>
                </div>

                {/* Cloudonix API Key */}
                <div>
                  <div className="flex items-center mb-1">
                    <label htmlFor="cloudonix_api_key" className="block text-sm font-medium text-gray-700">
                      Cloudonix Domain API Key
                    </label>
                    <Tooltip
                      content="Generate an API key from your Cloudonix dashboard under API settings."
                      className="ml-2"
                    />
                  </div>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <Key className="h-5 w-5 text-gray-400" />
                    </div>
                    <input
                      type={showApiKey ? 'text' : 'password'}
                      name="cloudonix_api_key"
                      id="cloudonix_api_key"
                      value={formData.cloudonix_api_key}
                      onChange={handleInputChange}
                      className="block w-full pl-10 pr-12 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                      placeholder="Enter your Cloudonix domain API key"
                    />
                    <button
                      type="button"
                      onClick={() => setShowApiKey(!showApiKey)}
                      className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700"
                    >
                      {showApiKey ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                    </button>
                  </div>
                </div>
              </div>

              {/* Voice Application API Key */}
              <div>
                <label htmlFor="voice_app_api_key" className="block text-sm font-medium text-gray-700 mb-1">
                  Cloudonix Voice Application API Key
                </label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <Key className="h-5 w-5 text-gray-400" />
                  </div>
                  <input
                    type={showVoiceApiKey ? 'text' : 'password'}
                    name="voice_app_api_key"
                    id="voice_app_api_key"
                    value={formData.voice_app_api_key}
                    readOnly
                    className="block w-full pl-10 pr-12 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-600"
                    placeholder="Generated automatically by Cloudonix"
                  />
                  <button
                    type="button"
                    onClick={() => setShowVoiceApiKey(!showVoiceApiKey)}
                    className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700"
                  >
                    {showVoiceApiKey ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                  </button>
                </div>
                <p className="mt-1 text-sm text-gray-500">
                  This API key is automatically generated by Cloudonix when the voice application is created.
                </p>
              </div>

              {/* Application Server Endpoint */}
              <div>
                <div className="flex items-center mb-1">
                  <label htmlFor="voice_app_endpoint" className="block text-sm font-medium text-gray-700">
                    Application Server Endpoint
                  </label>
                  <Tooltip
                    content="This should point to your voice webhook handler endpoint where Cloudonix will send voice events."
                    className="ml-2"
                  />
                </div>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <Server className="h-5 w-5 text-gray-400" />
                  </div>
                  <input
                    type="url"
                    name="voice_app_endpoint"
                    id="voice_app_endpoint"
                    value={formData.voice_app_endpoint}
                    onChange={handleInputChange}
                    className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                    placeholder="https://yourapp.com"
                  />
                </div>
                <p className="mt-2 text-sm text-gray-500">
                  Base URL of your application server for handling voice events
                </p>
              </div>

              {/* Voice Application Endpoint - Readonly */}
              <div>
                <label htmlFor="voice_application_endpoint" className="block text-sm font-medium text-gray-700 mb-1">
                  Voice Application Endpoint
                </label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <Server className="h-5 w-5 text-gray-400" />
                  </div>
                  <input
                    type="url"
                    name="voice_application_endpoint"
                    id="voice_application_endpoint"
                    value={voiceApplicationEndpoint}
                    readOnly
                    className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-500 cursor-not-allowed"
                  />
                </div>
                <p className="mt-2 text-sm text-gray-500">
                  Automatically generated endpoint for voice application registration
                </p>
              </div>

              {/* Session Update URL - Readonly */}
              <div>
                <label htmlFor="session_update_url" className="block text-sm font-medium text-gray-700 mb-1">
                  Cloudonix Domain Session Update URL
                </label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <Server className="h-5 w-5 text-gray-400" />
                  </div>
                  <input
                    type="url"
                    name="session_update_url"
                    id="session_update_url"
                    value={sessionUpdateUrl}
                    readOnly
                    className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-500 cursor-not-allowed"
                  />
                </div>
                <p className="mt-2 text-sm text-gray-500">
                  Automatically generated based on your voice application endpoint
                </p>
              </div>

              {/* CDR Callback URL - Readonly */}
              <div>
                <label htmlFor="cdr_callback_url" className="block text-sm font-medium text-gray-700 mb-1">
                  Cloudonix Domain CDR Callback URL
                </label>
                <div className="relative">
                  <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <Server className="h-5 w-5 text-gray-400" />
                  </div>
                  <input
                    type="url"
                    name="cdr_callback_url"
                    id="cdr_callback_url"
                    value={cdrCallbackUrl}
                    readOnly
                    className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-500 cursor-not-allowed"
                  />
                </div>
                <p className="mt-2 text-sm text-gray-500">
                  Automatically generated based on your voice application endpoint
                </p>
              </div>
            </div>
          </div>

          {/* Submit Button */}
          <div className="mt-8 flex justify-end">
            <button
              type="submit"
              disabled={loading}
              className="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
            >
              <Save className="w-5 h-5 mr-2" />
              {loading ? 'Saving...' : 'Save Settings'}
            </button>
          </div>
        </form>


      </div>
    </AdminLayout>
  );
};