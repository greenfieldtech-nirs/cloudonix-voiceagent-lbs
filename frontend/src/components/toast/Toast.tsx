import React, { useState, useEffect, useCallback } from 'react';
import { X, ChevronDown, ChevronUp, CheckCircle, XCircle, AlertCircle, Info } from 'lucide-react';
import { ToastMessage, ToastType } from './types';

interface ToastProps {
  toast: ToastMessage;
  onClose: (id: string) => void;
}

export const Toast: React.FC<ToastProps> = ({ toast, onClose }) => {
  const [showDetails, setShowDetails] = useState(false);
  const [isVisible, setIsVisible] = useState(false);
  const [isExiting, setIsExiting] = useState(false);

  useEffect(() => {
    // Trigger entrance animation
    const timer = setTimeout(() => setIsVisible(true), 10);
    return () => clearTimeout(timer);
  }, []);

  const handleCloseCallback = useCallback(() => {
    setIsExiting(true);
    setTimeout(() => onClose(toast.id), 300); // Wait for exit animation
  }, [onClose, toast.id]);

  useEffect(() => {
    // Auto-dismiss for success messages after 3 seconds
    if (toast.type === 'success' && !toast.persistent) {
      const duration = toast.duration || 3000;
      const timer = setTimeout(() => {
        handleCloseCallback();
      }, duration);
      return () => clearTimeout(timer);
    }
  }, [toast, handleCloseCallback]);



  const getIcon = (type: ToastType) => {
    switch (type) {
      case 'success':
        return <CheckCircle className="w-5 h-5 text-green-500" />;
      case 'error':
        return <XCircle className="w-5 h-5 text-red-500" />;
      case 'warning':
        return <AlertCircle className="w-5 h-5 text-yellow-500" />;
      case 'info':
        return <Info className="w-5 h-5 text-blue-500" />;
      default:
        return <Info className="w-5 h-5 text-gray-500" />;
    }
  };

  const getBackgroundColor = (type: ToastType) => {
    switch (type) {
      case 'success':
        return 'bg-green-50 border-green-200';
      case 'error':
        return 'bg-red-50 border-red-200';
      case 'warning':
        return 'bg-yellow-50 border-yellow-200';
      case 'info':
        return 'bg-blue-50 border-blue-200';
      default:
        return 'bg-gray-50 border-gray-200';
    }
  };

  const getTextColor = (type: ToastType) => {
    switch (type) {
      case 'success':
        return 'text-green-800';
      case 'error':
        return 'text-red-800';
      case 'warning':
        return 'text-yellow-800';
      case 'info':
        return 'text-blue-800';
      default:
        return 'text-gray-800';
    }
  };

  return (
    <div
      className={`
        min-w-[350px] w-full max-w-md shadow-lg rounded-lg border transition-all duration-300 ease-in-out transform
        ${getBackgroundColor(toast.type)}
        ${isVisible && !isExiting ? 'translate-x-0 opacity-100' : 'translate-x-full opacity-0'}
        ${isExiting ? 'translate-x-full opacity-0' : ''}
      `}
      style={{ transformOrigin: 'bottom right' }}
    >
      <div className="p-4">
        <div className="flex items-start">
          <div className="flex-shrink-0">
            {getIcon(toast.type)}
          </div>
          <div className="ml-3 w-0 flex-1">
            <p className={`text-sm font-medium ${getTextColor(toast.type)}`}>
              {toast.title}
            </p>
            {toast.message && (
              <p className={`mt-1 text-sm ${getTextColor(toast.type)} opacity-75`}>
                {toast.message}
              </p>
            )}
          </div>
          <div className="ml-4 flex-shrink-0 flex">
            {(toast.type === 'error' || toast.persistent) && toast.details && (
              <button
                onClick={() => setShowDetails(!showDetails)}
                className={`mr-2 p-1 rounded-md hover:bg-black hover:bg-opacity-5 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 ${getTextColor(toast.type)}`}
              >
                {showDetails ? (
                  <ChevronUp className="w-4 h-4" />
                ) : (
                  <ChevronDown className="w-4 h-4" />
                )}
              </button>
            )}
            <button
              onClick={handleCloseCallback}
              className={`p-1 rounded-md hover:bg-black hover:bg-opacity-5 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 ${getTextColor(toast.type)}`}
            >
              <X className="w-4 h-4" />
            </button>
          </div>
        </div>

        {/* Details section */}
        {showDetails && toast.details && (
          <div className="mt-3 pt-3 border-t border-gray-200">
            <div className="text-xs text-gray-600 bg-gray-100 p-3 rounded-md font-mono whitespace-pre-wrap">
              {toast.details}
            </div>
          </div>
        )}
      </div>
    </div>
  );
};