# Message Toaster Component System

## Overview
A comprehensive toast notification system that replaces simple JavaScript pop-ups with elegant, floating notifications from the bottom-right corner of the screen.

## Features

### 🎯 Core Functionality
- **Floating Notifications**: Toasts appear from bottom-right corner
- **Auto-dismiss**: Success notifications disappear after 5 seconds
- **Persistent Errors**: Error notifications remain visible until manually closed
- **Expandable Details**: Error toasts include foldable details section
- **Smooth Animations**: Entrance and exit animations with proper timing

### 🎨 Visual Design
- **Color-coded**: Different colors for success (green), error (red), warning (yellow), info (blue)
- **Icons**: Appropriate Lucide icons for each toast type
- **Responsive**: Works on all screen sizes
- **Accessible**: Proper focus management and keyboard navigation

## Components

### 1. Toast Types & Interfaces
```typescript
type ToastType = 'success' | 'error' | 'warning' | 'info';

interface ToastMessage {
  id: string;
  type: ToastType;
  title: string;
  message?: string;
  details?: string;        // Foldable details for errors
  duration?: number;       // Auto-dismiss duration (default: 3000ms for success)
  persistent?: boolean;    // Override auto-dismiss behavior
}
```

### 2. ToastProvider & Context
- **Global State**: Manages toast queue across the entire application
- **Easy Integration**: Simple hook-based API for adding/removing toasts
- **Convenience Functions**: Pre-configured success/error/warning/info functions

### 3. Toast Component
- **Individual Toast Rendering**: Handles animation, styling, and interaction
- **Details Toggle**: Expandable section for additional error information
- **Auto-dismiss Logic**: Timer-based dismissal for success toasts
- **Manual Close**: X button for user-initiated dismissal

### 4. ToastContainer
- **Positioning**: Fixed positioning in bottom-right corner
- **Stack Management**: Multiple toasts stack vertically
- **Z-index**: High z-index to appear above other content

## Usage Examples

### Basic Usage
```typescript
import { useToastHelpers } from '../components/toast';

const MyComponent = () => {
  const { success, error, warning, info } = useToastHelpers();

  const handleSuccess = () => {
    success('Operation Completed', 'Your data has been saved successfully.');
  };

  const handleError = () => {
    error(
      'Save Failed',
      'Unable to save your data.',
      'Error details: Invalid API key format'
    );
  };

  return (
    <button onClick={handleSuccess}>Save</button>
    <button onClick={handleError}>Fail Save</button>
  );
};
```

### Advanced Usage
```typescript
import { useToast } from '../components/toast';

const MyComponent = () => {
  const { addToast } = useToast();

  const handleCustomToast = () => {
    addToast({
      type: 'warning',
      title: 'Custom Warning',
      message: 'This is a custom warning message',
      persistent: true, // Won't auto-dismiss
      duration: 5000,   // Custom duration if not persistent
    });
  };
};
```

## Integration

### App-level Setup
The toast system is integrated at the App.tsx level:
- `ToastProvider` wraps the entire application
- `ToastContainerWrapper` renders the toast container

### Settings Page Integration
The Settings page has been updated to use toasts instead of `alert()`:
- Success: Auto-dismissing green toast (5 seconds)
- Errors: Persistent red toast with expandable details section
- Inline Tooltips: Help information displayed as tooltips next to form fields

## Technical Details

### Animation System
- **Entrance**: Slide in from right with opacity fade-in
- **Exit**: Slide out to right with opacity fade-out
- **Timing**: 300ms transition duration
- **Auto-dismiss**: Success toasts after 5 seconds
- **Staggering**: Smooth stacking of multiple toasts

### Dimensions
- **Width**: Minimum 350px, maximum 28rem (448px)
- **Position**: Fixed bottom-right corner with 1rem margin
- **Z-index**: 50 to appear above other content

### Error Handling
- **Details Section**: Monospace font for technical error details
- **Expandable**: Click to show/hide additional information
- **Persistent**: Error toasts don't auto-dismiss

### Performance
- **Efficient Re-renders**: Uses React context for state management
- **Minimal DOM**: Only renders visible toasts
- **Cleanup**: Proper timer cleanup to prevent memory leaks

## Files Created/Modified

### New Files
- `src/components/toast/types.ts` - TypeScript interfaces
- `src/components/toast/Toast.tsx` - Individual toast component
- `src/components/toast/ToastContainer.tsx` - Toast container
- `src/components/toast/ToastContainerWrapper.tsx` - Context-aware wrapper
- `src/components/toast/ToastContext.tsx` - Context and hooks
- `src/components/toast/index.ts` - Barrel exports
- `src/components/Tooltip.tsx` - Reusable tooltip component

### Modified Files
- `src/App.tsx` - Added ToastProvider and ToastContainerWrapper
- `src/pages/Settings.tsx` - Replaced alerts with toast notifications and added inline tooltips

## Accessibility
- **Keyboard Navigation**: Proper focus management
- **Screen Readers**: Semantic markup and ARIA labels
- **Color Contrast**: High contrast colors for readability
- **Motion Preferences**: Respects user motion preferences

## Browser Support
- **Modern Browsers**: Chrome, Firefox, Safari, Edge
- **CSS Grid/Flexbox**: Uses modern layout techniques
- **ES6+ Features**: Arrow functions, destructuring, etc.

## Future Enhancements
- **Position Options**: Top-left, top-right, bottom-left positioning
- **Toast History**: Keep dismissed toasts in a history panel
- **Sound Notifications**: Optional audio cues
- **Progress Indicators**: For long-running operations
- **Toast Actions**: Buttons within toasts for user actions