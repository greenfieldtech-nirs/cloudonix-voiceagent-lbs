import React, { createContext, useContext, useState, useCallback, ReactNode } from 'react';
import { ToastMessage, ToastContextType } from './types';

const ToastContext = createContext<ToastContextType | undefined>(undefined);

interface ToastProviderProps {
  children: ReactNode;
}

export const ToastProvider: React.FC<ToastProviderProps> = ({ children }) => {
  const [toasts, setToasts] = useState<ToastMessage[]>([]);

  const addToast = useCallback((toast: Omit<ToastMessage, 'id'>) => {
    const id = `toast-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    const newToast: ToastMessage = {
      id,
      ...toast,
    };

    setToasts((prevToasts) => [...prevToasts, newToast]);
  }, []);

  const removeToast = useCallback((id: string) => {
    setToasts((prevToasts) => prevToasts.filter((toast) => toast.id !== id));
  }, []);

  const clearToasts = useCallback(() => {
    setToasts([]);
  }, []);

  const value: ToastContextType = {
    toasts,
    addToast,
    removeToast,
    clearToasts,
  };

  return (
    <ToastContext.Provider value={value}>
      {children}
    </ToastContext.Provider>
  );
};

export const useToast = (): ToastContextType => {
  const context = useContext(ToastContext);
  if (context === undefined) {
    throw new Error('useToast must be used within a ToastProvider');
  }
  return context;
};

// Convenience functions for common toast types
export const useToastHelpers = () => {
  const { addToast } = useToast();

  const success = useCallback((title: string, message?: string) => {
    addToast({
      type: 'success',
      title,
      message,
      duration: 5000,
    });
  }, [addToast]);

  const error = useCallback((title: string, message?: string, details?: string) => {
    addToast({
      type: 'error',
      title,
      message,
      details,
      persistent: true,
    });
  }, [addToast]);

  const warning = useCallback((title: string, message?: string) => {
    addToast({
      type: 'warning',
      title,
      message,
      persistent: true,
    });
  }, [addToast]);

  const info = useCallback((title: string, message?: string) => {
    addToast({
      type: 'info',
      title,
      message,
      persistent: true,
    });
  }, [addToast]);

  return {
    success,
    error,
    warning,
    info,
  };
};