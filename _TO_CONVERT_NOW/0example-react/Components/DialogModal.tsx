import React from 'react';
import Modal from './Modal';

interface DialogModalProps {
  show: boolean;
  maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl';
  closeable?: boolean;
  onClose: () => void;
  title: React.ReactNode;
  content: React.ReactNode;
  footer?: React.ReactNode;
}

const DialogModal: React.FC<DialogModalProps> = ({ show = false, maxWidth = '2xl', closeable = true, onClose, title, content, footer }) => {
  return (
    <Modal show={show} maxWidth={maxWidth} closeable={closeable} onClose={onClose}>
      <div className="px-6 py-4">
        <div className="text-lg font-medium text-gray-900 dark:text-gray-100">{title}</div>
        <div className="mt-4 text-sm text-gray-600 dark:text-gray-400">{content}</div>
      </div>
      <div className="flex flex-row justify-end px-6 py-4 bg-gray-100 dark:bg-gray-800 text-end">{footer}</div>
    </Modal>
  );
};

export default DialogModal;
