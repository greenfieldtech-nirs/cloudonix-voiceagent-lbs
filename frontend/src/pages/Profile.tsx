import React, { useState, useEffect } from 'react';
import { AdminLayout } from '../components/AdminLayout';
import { User, Mail, Lock, Camera, Save, Building, MapPin, Phone, Smartphone } from 'lucide-react';

// Country data with flags (moved outside component to avoid useEffect dependency warnings)
const allCountries = [
  { code: 'US', name: 'United States', flag: '🇺🇸' },
  { code: 'CA', name: 'Canada', flag: '🇨🇦' },
  { code: 'GB', name: 'United Kingdom', flag: '🇬🇧' },
  { code: 'DE', name: 'Germany', flag: '🇩🇪' },
  { code: 'FR', name: 'France', flag: '🇫🇷' },
  { code: 'IT', name: 'Italy', flag: '🇮🇹' },
  { code: 'ES', name: 'Spain', flag: '🇪🇸' },
  { code: 'NL', name: 'Netherlands', flag: '🇳🇱' },
  { code: 'BE', name: 'Belgium', flag: '🇧🇪' },
  { code: 'CH', name: 'Switzerland', flag: '🇨🇭' },
  { code: 'AT', name: 'Austria', flag: '🇦🇹' },
  { code: 'SE', name: 'Sweden', flag: '🇸🇪' },
  { code: 'NO', name: 'Norway', flag: '🇳🇴' },
  { code: 'DK', name: 'Denmark', flag: '🇩🇰' },
  { code: 'FI', name: 'Finland', flag: '🇫🇮' },
  { code: 'PT', name: 'Portugal', flag: '🇵🇹' },
  { code: 'IE', name: 'Ireland', flag: '🇮🇪' },
  { code: 'LU', name: 'Luxembourg', flag: '🇱🇺' },
  { code: 'MT', name: 'Malta', flag: '🇲🇹' },
  { code: 'CY', name: 'Cyprus', flag: '🇨🇾' },
  { code: 'GR', name: 'Greece', flag: '🇬🇷' },
  { code: 'SI', name: 'Slovenia', flag: '🇸🇮' },
  { code: 'HR', name: 'Croatia', flag: '🇭🇷' },
  { code: 'RO', name: 'Romania', flag: '🇷🇴' },
  { code: 'BG', name: 'Bulgaria', flag: '🇧🇬' },
  { code: 'HU', name: 'Hungary', flag: '🇭🇺' },
  { code: 'CZ', name: 'Czech Republic', flag: '🇨🇿' },
  { code: 'SK', name: 'Slovakia', flag: '🇸🇰' },
  { code: 'PL', name: 'Poland', flag: '🇵🇱' },
  { code: 'EE', name: 'Estonia', flag: '🇪🇪' },
  { code: 'LV', name: 'Latvia', flag: '🇱🇻' },
  { code: 'LT', name: 'Lithuania', flag: '🇱🇹' },
  { code: 'AU', name: 'Australia', flag: '🇦🇺' },
  { code: 'NZ', name: 'New Zealand', flag: '🇳🇿' },
  { code: 'JP', name: 'Japan', flag: '🇯🇵' },
  { code: 'KR', name: 'South Korea', flag: '🇰🇷' },
  { code: 'SG', name: 'Singapore', flag: '🇸🇬' },
  { code: 'HK', name: 'Hong Kong', flag: '🇭🇰' },
  { code: 'TW', name: 'Taiwan', flag: '🇹🇼' },
  { code: 'CN', name: 'China', flag: '🇨🇳' },
  { code: 'IN', name: 'India', flag: '🇮🇳' },
  { code: 'BR', name: 'Brazil', flag: '🇧🇷' },
  { code: 'MX', name: 'Mexico', flag: '🇲🇽' },
  { code: 'AR', name: 'Argentina', flag: '🇦🇷' },
  { code: 'CL', name: 'Chile', flag: '🇨🇱' },
  { code: 'CO', name: 'Colombia', flag: '🇨🇴' },
  { code: 'PE', name: 'Peru', flag: '🇵🇪' },
  { code: 'ZA', name: 'South Africa', flag: '🇿🇦' },
  { code: 'IL', name: 'Israel', flag: '🇮🇱' },
  { code: 'AE', name: 'United Arab Emirates', flag: '🇦🇪' },
  { code: 'SA', name: 'Saudi Arabia', flag: '🇸🇦' },
  { code: 'TR', name: 'Turkey', flag: '🇹🇷' },
  { code: 'RU', name: 'Russia', flag: '🇷🇺' },
];

export const Profile: React.FC = () => {
  const [formData, setFormData] = useState({
    name: 'Admin User',
    email: 'admin@voiceflow.com',
    company_name: '',
    address: '',
    country: '',
    phone: '',
    mobile: '',
    current_password: '',
    password: '',
    password_confirmation: '',
  });
  const [profileImage, setProfileImage] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [activeTab, setActiveTab] = useState<'profile' | 'password'>('profile');
  const [countries, setCountries] = useState<{code: string, name: string, flag: string}[]>([]);
  const [countrySearch, setCountrySearch] = useState('');
  const [showCountryDropdown, setShowCountryDropdown] = useState(false);

  // Load profile data on component mount
  React.useEffect(() => {
    const loadProfile = async () => {
      try {
        const response = await fetch('http://localhost:8000/api/profile', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Accept': 'application/json',
          },
        });

        if (response.ok) {
          const data = await response.json();
          const user = data.user;
          setFormData(prev => ({
            ...prev,
            name: user.name || '',
            email: user.email || '',
            company_name: user.company_name || '',
            address: user.address || '',
            country: user.country || '',
            phone: user.phone || '',
            mobile: user.mobile || '',
          }));
        }
      } catch (error) {
        console.error('Failed to load profile:', error);
      }
    };

    loadProfile();
  }, []);

  // E.164 phone number validation
  const validateE164 = (phone: string): boolean => {
    const e164Regex = /^\+[1-9]\d{1,14}$/;
    return e164Regex.test(phone);
  };

  // Filter countries based on search
  useEffect(() => {
    if (countrySearch.trim() === '') {
      setCountries(allCountries); // Show all countries by default
    } else {
      const filtered = allCountries.filter(country =>
        country.name.toLowerCase().includes(countrySearch.toLowerCase()) ||
        country.code.toLowerCase().includes(countrySearch.toLowerCase())
      );
      setCountries(filtered); // Show all filtered results
    }
  }, [countrySearch]); // allCountries is a constant, so no need to include it

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setFormData({
      ...formData,
      [e.target.name]: e.target.value
    });
  };

  const handleCountrySelect = (country: {code: string, name: string, flag: string}) => {
    setFormData({
      ...formData,
      country: country.code
    });
    setShowCountryDropdown(false);
    setCountrySearch('');
  };

  const handleImageUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = (e) => {
        setProfileImage(e.target?.result as string);
      };
      reader.readAsDataURL(file);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      const endpoint = activeTab === 'password' ? '/api/profile/password' : '/api/profile';
      const payload = activeTab === 'password'
        ? {
            current_password: formData.current_password,
            password: formData.password,
            password_confirmation: formData.password_confirmation,
          }
        : {
            name: formData.name,
            email: formData.email,
            company_name: formData.company_name,
            address: formData.address,
            country: formData.country,
            phone: formData.phone,
            mobile: formData.mobile,
          };

      const response = await fetch(`http://localhost:8000${endpoint}`, {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'Accept': 'application/json',
        },
        body: JSON.stringify(payload),
      });

      const data = await response.json();

      if (response.ok) {
        alert('Profile updated successfully!');
        // Update local storage with new user data if profile was updated
        if (activeTab === 'profile') {
          localStorage.setItem('user', JSON.stringify(data.user));
        }
        // Clear password fields after successful password change
        if (activeTab === 'password') {
          setFormData(prev => ({
            ...prev,
            current_password: '',
            password: '',
            password_confirmation: '',
          }));
        }
      } else {
        alert(data.message || 'Failed to update profile');
      }
    } catch (err) {
      alert('Network error. Please try again.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <AdminLayout>
      <div className="max-w-4xl mx-auto">
        {/* Header */}
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900">Profile Settings</h1>
          <p className="text-gray-600 mt-2">Manage your account information and preferences</p>
        </div>

        {/* Tabs */}
        <div className="mb-6">
          <div className="border-b border-gray-200">
            <nav className="-mb-px flex space-x-8">
              <button
                onClick={() => setActiveTab('profile')}
                className={`py-2 px-1 border-b-2 font-medium text-sm ${
                  activeTab === 'profile'
                    ? 'border-indigo-500 text-indigo-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                }`}
              >
                Profile Information
              </button>
              <button
                onClick={() => setActiveTab('password')}
                className={`py-2 px-1 border-b-2 font-medium text-sm ${
                  activeTab === 'password'
                    ? 'border-indigo-500 text-indigo-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                }`}
              >
                Change Password
              </button>
            </nav>
          </div>
        </div>

        <form onSubmit={handleSubmit}>
          {activeTab === 'profile' && (
            <div className="bg-white shadow-sm rounded-lg border border-gray-200">
              <div className="px-6 py-4 border-b border-gray-200">
                <h2 className="text-lg font-medium text-gray-900">Profile Information</h2>
                <p className="mt-1 text-sm text-gray-600">Update your personal information and profile picture</p>
              </div>

              <div className="px-6 py-6 space-y-6">
                {/* Profile Picture */}
                <div className="flex items-center space-x-6">
                  <div className="relative">
                    <div className="w-24 h-24 bg-gray-200 rounded-full flex items-center justify-center overflow-hidden">
                      {profileImage ? (
                        <img src={profileImage} alt="Profile" className="w-full h-full object-cover" />
                      ) : (
                        <User className="w-12 h-12 text-gray-400" />
                      )}
                    </div>
                    <label className="absolute bottom-0 right-0 bg-indigo-600 text-white p-2 rounded-full cursor-pointer hover:bg-indigo-700 transition-colors">
                      <Camera className="w-4 h-4" />
                      <input
                        type="file"
                        accept="image/*"
                        onChange={handleImageUpload}
                        className="hidden"
                      />
                    </label>
                  </div>
                  <div>
                    <h3 className="text-sm font-medium text-gray-900">Profile Picture</h3>
                    <p className="text-sm text-gray-500">JPG, GIF or PNG. Max size 2MB.</p>
                  </div>
                </div>

                {/* Name and Email Row */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  {/* Name */}
                  <div>
                    <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">
                      Full Name
                    </label>
                    <div className="relative">
                      <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <User className="h-5 w-5 text-gray-400" />
                      </div>
                      <input
                        type="text"
                        name="name"
                        id="name"
                        value={formData.name}
                        onChange={handleInputChange}
                        className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="Enter your full name"
                      />
                    </div>
                  </div>

                  {/* Email - Readonly */}
                  <div>
                    <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1">
                      Email Address
                    </label>
                    <div className="relative">
                      <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <Mail className="h-5 w-5 text-gray-400" />
                      </div>
                      <input
                        type="email"
                        name="email"
                        id="email"
                        value={formData.email}
                        readOnly
                        className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-500 cursor-not-allowed"
                      />
                    </div>
                  </div>
                </div>

                {/* Company Name and Country Row */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  {/* Company Name */}
                  <div>
                    <label htmlFor="company_name" className="block text-sm font-medium text-gray-700 mb-1">
                      Company Name
                    </label>
                    <div className="relative">
                      <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <Building className="h-5 w-5 text-gray-400" />
                      </div>
                      <input
                        type="text"
                        name="company_name"
                        id="company_name"
                        value={formData.company_name}
                        onChange={handleInputChange}
                        className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="Enter your company name"
                      />
                    </div>
                  </div>

                  {/* Country */}
                  <div>
                    <label htmlFor="country" className="block text-sm font-medium text-gray-700 mb-1">
                      Country
                    </label>
                    <div className="relative">
                      <input
                        type="text"
                        value={countrySearch}
                        onChange={(e) => {
                          setCountrySearch(e.target.value);
                          setShowCountryDropdown(true);
                        }}
                        onFocus={() => setShowCountryDropdown(true)}
                        onBlur={() => setTimeout(() => setShowCountryDropdown(false), 200)}
                        className="block w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="Search and select your country"
                      />
                      {formData.country && (
                        <div className="absolute right-3 top-2.5 text-sm text-gray-500 flex items-center">
                          <span className="mr-2">{allCountries.find(c => c.code === formData.country)?.flag}</span>
                          {allCountries.find(c => c.code === formData.country)?.name}
                        </div>
                      )}
                      {showCountryDropdown && (
                        <div className="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto">
                          {countries.map((country) => (
                            <button
                              key={country.code}
                              type="button"
                              onClick={() => handleCountrySelect(country)}
                              className="w-full px-3 py-2 text-left hover:bg-gray-50 focus:bg-gray-50 focus:outline-none flex items-center"
                            >
                              <span className="text-lg mr-3">{country.flag}</span>
                              {country.name}
                            </button>
                          ))}
                          {countries.length === 0 && (
                            <div className="px-3 py-2 text-gray-500">No countries found</div>
                          )}
                        </div>
                      )}
                    </div>
                  </div>
                </div>

                {/* Address - Full Width */}
                <div>
                  <label htmlFor="address" className="block text-sm font-medium text-gray-700 mb-1">
                    Address
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <MapPin className="h-5 w-5 text-gray-400" />
                    </div>
                    <input
                      type="text"
                      name="address"
                      id="address"
                      value={formData.address}
                      onChange={handleInputChange}
                      className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                      placeholder="Enter your address"
                    />
                  </div>
                </div>

                {/* Phone and Mobile Row */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  {/* Phone */}
                  <div>
                    <label htmlFor="phone" className="block text-sm font-medium text-gray-700 mb-1">
                      Phone
                    </label>
                    <div className="relative">
                      <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <Phone className="h-5 w-5 text-gray-400" />
                      </div>
                      <input
                        type="tel"
                        name="phone"
                        id="phone"
                        value={formData.phone}
                        onChange={handleInputChange}
                        className={`block w-full pl-10 pr-3 py-2 border rounded-md focus:ring-indigo-500 focus:border-indigo-500 ${
                          formData.phone && !validateE164(formData.phone) ? 'border-red-300' : 'border-gray-300'
                        }`}
                        placeholder="+1234567890"
                      />
                    </div>
                    {formData.phone && !validateE164(formData.phone) && (
                      <p className="mt-1 text-sm text-red-600">Please enter a valid phone number in E.164 format (e.g., +1234567890)</p>
                    )}
                    <p className="mt-1 text-sm text-gray-500">Enter phone number in E.164 format (+country code)</p>
                  </div>

                  {/* Mobile */}
                  <div>
                    <label htmlFor="mobile" className="block text-sm font-medium text-gray-700 mb-1">
                      Mobile
                    </label>
                    <div className="relative">
                      <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <Smartphone className="h-5 w-5 text-gray-400" />
                      </div>
                      <input
                        type="tel"
                        name="mobile"
                        id="mobile"
                        value={formData.mobile}
                        onChange={handleInputChange}
                        className={`block w-full pl-10 pr-3 py-2 border rounded-md focus:ring-indigo-500 focus:border-indigo-500 ${
                          formData.mobile && !validateE164(formData.mobile) ? 'border-red-300' : 'border-gray-300'
                        }`}
                        placeholder="+1234567890"
                      />
                    </div>
                    {formData.mobile && !validateE164(formData.mobile) && (
                      <p className="mt-1 text-sm text-red-600">Please enter a valid mobile number in E.164 format (e.g., +1234567890)</p>
                    )}
                    <p className="mt-1 text-sm text-gray-500">Enter mobile number in E.164 format (+country code)</p>
                  </div>
                </div>
              </div>
            </div>
          )}

          {activeTab === 'password' && (
            <div className="bg-white shadow-sm rounded-lg border border-gray-200">
              <div className="px-6 py-4 border-b border-gray-200">
                <h2 className="text-lg font-medium text-gray-900">Change Password</h2>
                <p className="mt-1 text-sm text-gray-600">Update your password to keep your account secure</p>
              </div>

              <div className="px-6 py-6 space-y-6">
                {/* Current Password */}
                <div>
                  <label htmlFor="current_password" className="block text-sm font-medium text-gray-700 mb-1">
                    Current Password
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <Lock className="h-5 w-5 text-gray-400" />
                    </div>
                    <input
                      type="password"
                      name="current_password"
                      id="current_password"
                      value={formData.current_password}
                      onChange={handleInputChange}
                      className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                      placeholder="Enter current password"
                    />
                  </div>
                </div>

                {/* New Password */}
                <div>
                  <label htmlFor="password" className="block text-sm font-medium text-gray-700 mb-1">
                    New Password
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <Lock className="h-5 w-5 text-gray-400" />
                    </div>
                    <input
                      type="password"
                      name="password"
                      id="password"
                      value={formData.password}
                      onChange={handleInputChange}
                      className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                      placeholder="Enter new password"
                    />
                  </div>
                </div>

                {/* Confirm Password */}
                <div>
                  <label htmlFor="password_confirmation" className="block text-sm font-medium text-gray-700 mb-1">
                    Confirm New Password
                  </label>
                  <div className="relative">
                    <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                      <Lock className="h-5 w-5 text-gray-400" />
                    </div>
                    <input
                      type="password"
                      name="password_confirmation"
                      id="password_confirmation"
                      value={formData.password_confirmation}
                      onChange={handleInputChange}
                      className="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                      placeholder="Confirm new password"
                    />
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Submit Button */}
          <div className="mt-6 flex justify-end">
            <button
              type="submit"
              disabled={loading}
              className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
            >
              <Save className="w-4 h-4 mr-2" />
              {loading ? 'Saving...' : 'Save Changes'}
            </button>
          </div>
        </form>
      </div>
    </AdminLayout>
  );
};