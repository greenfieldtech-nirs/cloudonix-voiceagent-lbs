import React, { useState, useRef } from 'react';
import { Info } from 'lucide-react';

interface TooltipProps {
  content: string;
  className?: string;
}

export const Tooltip: React.FC<TooltipProps> = ({ content, className = '' }) => {
  const [isVisible, setIsVisible] = useState(false);
  const timeoutRef = useRef<NodeJS.Timeout | null>(null);

  const showTooltip = () => {
    if (timeoutRef.current) clearTimeout(timeoutRef.current);
    setIsVisible(true);
  };

  const hideTooltip = () => {
    timeoutRef.current = setTimeout(() => setIsVisible(false), 150);
  };

  const handleMouseEnter = () => showTooltip();
  const handleMouseLeave = () => hideTooltip();
  const handleFocus = () => showTooltip();
  const handleBlur = () => hideTooltip();

  return (
    <div className={`relative inline-block ${className}`}>
      <button
        type="button"
        className="inline-flex items-center justify-center w-4 h-4 text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 rounded-full"
        onMouseEnter={handleMouseEnter}
        onMouseLeave={handleMouseLeave}
        onFocus={handleFocus}
        onBlur={handleBlur}
        aria-label="Help information"
      >
        <Info className="w-3.5 h-3.5" />
      </button>

      {isVisible && (
        <div
          className="absolute z-50 w-64 px-3 py-2 text-sm text-white bg-gray-900 rounded-lg shadow-lg transform -translate-x-1/2 left-1/2 bottom-full mb-2"
          role="tooltip"
        >
          {content}
          <div
            className="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900"
          />
        </div>
      )}
    </div>
  );
};