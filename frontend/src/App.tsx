import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { LandingPage } from './pages/LandingPage';
import { Login } from './pages/Login';
import { Register } from './pages/Register';
import { Dashboard } from './pages/Dashboard';
import { LiveCalls } from './pages/LiveCalls';
import { CallLogs } from './pages/CallLogs';
import { PhoneNumbers } from './pages/PhoneNumbers';
import { Profile } from './pages/Profile';
import { SettingsPage } from './pages/Settings';
import { ToastProvider } from './components/toast';
import { ToastContainerWrapper } from './components/toast/ToastContainerWrapper';

// Simple auth check
const isAuthenticated = () => {
  return localStorage.getItem('token') !== null;
};

// Protected route component
const ProtectedRoute: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  return isAuthenticated() ? <>{children}</> : <Navigate to="/login" replace />;
};

function App() {
  return (
    <ToastProvider>
      <Router>
        <Routes>
          {/* Public routes */}
          <Route path="/" element={<LandingPage />} />
          <Route path="/login" element={<Login />} />
          <Route path="/register" element={<Register />} />

          {/* Protected admin routes */}
          <Route path="/admin" element={<ProtectedRoute><Dashboard /></ProtectedRoute>} />
          <Route path="/admin/phone-numbers" element={<ProtectedRoute><PhoneNumbers /></ProtectedRoute>} />
          <Route path="/admin/live-calls" element={<ProtectedRoute><LiveCalls /></ProtectedRoute>} />
          <Route path="/admin/call-logs" element={<ProtectedRoute><CallLogs /></ProtectedRoute>} />
          <Route path="/admin/profile" element={<ProtectedRoute><Profile /></ProtectedRoute>} />
          <Route path="/admin/settings" element={<ProtectedRoute><SettingsPage /></ProtectedRoute>} />

          {/* Catch all - redirect to landing page */}
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
        <ToastContainerWrapper />
      </Router>
    </ToastProvider>
  );
}

export default App;
