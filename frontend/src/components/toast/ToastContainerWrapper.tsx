import React from 'react';
import { ToastContainer } from './ToastContainer';
import { useToast } from './ToastContext';

export const ToastContainerWrapper: React.FC = () => {
  const { toasts, removeToast } = useToast();

  return <ToastContainer toasts={toasts} onRemoveToast={removeToast} />;
};